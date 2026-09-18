<?php

use PHPUnit\Framework\TestCase;

final class GameTest extends TestCase
{
    private function setPhase(Game $game, TurnPhase $phase): void
    {
        $ref = new ReflectionProperty(Game::class, 'phase');
        $ref->setValue($game, $phase);
    }

    private function setPendingDebt(Game $game, int $amount): void
    {
        $ref = new ReflectionProperty(Game::class, 'pendingDebt');
        $ref->setValue($game, $amount);
    }

    // --- phase machine ---

    public function testInitialPhase(): void
    {
        $game = new Game(['A', 'B']);
        $game->start();
        $this->assertSame(TurnPhase::AWAITING_ROLL, $game->getCurrentPhase());
        $this->assertContains(PlayerAction::ROLL, $game->getAvailableActions());
    }

    public function testEndTurnAdvancesPlayer(): void
    {
        $game = new Game(['A', 'B']);
        $game->start();
        $this->assertSame('A', $game->getCurrentPlayer()->getName());
        $game->endTurn();
        $this->assertSame('B', $game->getCurrentPlayer()->getName());
        $this->assertSame(TurnPhase::AWAITING_ROLL, $game->getCurrentPhase());
    }

    // --- jail ---

    public function testPayBailLeavesJailAndKeepsPhase(): void
    {
        $game = new Game(['A', 'B']);
        $game->start();
        $a = $game->getCurrentPlayer();
        $a->setInJail(true);
        $before = $a->getMoney();
        $game->payBail();
        $this->assertFalse($a->isInJail());
        $this->assertSame($before - Game::JAIL_BAIL, $a->getMoney());
        $this->assertSame(TurnPhase::AWAITING_ROLL, $game->getCurrentPhase());
    }

    public function testPayBailInsufficientFundsThrows(): void
    {
        $game = new Game(['A', 'B']);
        $game->start();
        $a = $game->getCurrentPlayer();
        $a->removeMoney($a->getMoney() - 10); // keep 10, below bail 50
        $a->setInJail(true);
        $this->expectException(InvalidPlayerActionException::class);
        $game->payBail();
    }

    public function testReleaseWithCard(): void
    {
        $game = new Game(['A', 'B']);
        $game->start();
        $a = $game->getCurrentPlayer();
        $a->addGetOutOfJailCard();
        $a->setInJail(true);
        $game->releaseWithCard();
        $this->assertFalse($a->isInJail());
        $this->assertFalse($a->hasGetOutOfJailCard());
        $this->assertSame(TurnPhase::AWAITING_ROLL, $game->getCurrentPhase());
    }

    public function testReleaseWithCardWithoutCardThrows(): void
    {
        $game = new Game(['A', 'B']);
        $game->start();
        $game->getCurrentPlayer()->setInJail(true);
        $this->expectException(InvalidPlayerActionException::class);
        $game->releaseWithCard();
    }

    // --- buying ---

    public function testBuyCurrentTile(): void
    {
        $game = new Game(['A', 'B']);
        $game->start();
        $a = $game->getCurrentPlayer();
        $prop = tilesByType($game, TileType::PROPERTY)[0];
        $a->setPosition($prop->getPosition());
        $before = $a->getMoney();
        $game->buyCurrentTile($a);
        $this->assertSame($a, $prop->getOwner());
        $this->assertSame($before - $prop->getPrice(), $a->getMoney());
    }

    // --- bankruptcy / end game ---

    public function testBankruptcyEndsGame(): void
    {
        $game = new Game(['A', 'B']);
        $game->start();
        $b = $game->getPlayers()[1];
        $game->declareBankruptcy($b);
        $this->assertTrue($game->isGameOver());
        $this->assertSame('A', $game->getWinner()->getName());
    }

    // --- interactive liquidation (phase forced) ---

    public function testLiquidationActionsOfferBankruptcy(): void
    {
        $game = new Game(['A', 'B']);
        $game->start();
        $this->setPhase($game, TurnPhase::AWAITING_LIQUIDATION);
        $this->assertContains(PlayerAction::DECLARE_BANKRUPTCY, $game->getAvailableActions());
    }

    public function testSellingHouseSettlesDebtAndEndsTurn(): void
    {
        $game = new Game(['A', 'B'], new GameRules(false, true)); // business tour: sell without group rules
        $game->start();
        $a = $game->getCurrentPlayer();
        $prop = tilesByType($game, TileType::PROPERTY)[0];
        $prop->setOwner($a);
        $prop->setBuildLevel(1);

        $this->setPhase($game, TurnPhase::AWAITING_LIQUIDATION);
        $this->setPendingDebt($game, 10);
        $game->sellHouse($prop);

        $this->assertSame(TurnPhase::TURN_OVER, $game->getCurrentPhase(), 'debt settled -> turn over');
    }

    public function testDeclareBankruptcyInteractiveNeedsLiquidationPhase(): void
    {
        $game = new Game(['A', 'B']);
        $game->start();
        $this->expectException(InvalidPlayerActionException::class);
        $game->declareBankruptcyInteractive();
    }

    // --- Business Tour: buyout ---

    public function testBuyoutRefusedWhenRuleOff(): void
    {
        $game = new Game(['A', 'B']);
        $game->start();
        $a = $game->getPlayers()[0];
        $b = $game->getPlayers()[1];
        $prop = tilesByType($game, TileType::PROPERTY)[0];
        $prop->setOwner($b);
        $this->expectException(InvalidPlayerActionException::class);
        $game->buyoutTile($a, $prop);
    }

    public function testBuyoutTransfersOwnership(): void
    {
        $game = new Game(['A', 'B'], new GameRules(false, true));
        $game->start();
        $a = $game->getPlayers()[0];
        $b = $game->getPlayers()[1];
        $prop = tilesByType($game, TileType::PROPERTY)[0];
        $prop->setOwner($b);
        $price = Game::BUYOUT_MULTIPLIER * $prop->getPrice();

        $ma = $a->getMoney();
        $mb = $b->getMoney();
        $game->buyoutTile($a, $prop);

        $this->assertSame($a, $prop->getOwner());
        $this->assertSame($ma - $price, $a->getMoney());
        $this->assertSame($mb + $price, $b->getMoney());
    }

    // --- Business Tour: building ---

    public function testBuildOnlyOnCurrentTileInBusinessTour(): void
    {
        $game = new Game(['A', 'B'], new GameRules(false, true));
        $game->start();
        $a = $game->getPlayers()[0];
        $props = tilesByType($game, TileType::PROPERTY);
        $here = $props[0];
        $elsewhere = $props[1];
        $here->setOwner($a);
        $elsewhere->setOwner($a);
        $a->setPosition($here->getPosition());

        $game->buildHouse($here);
        $this->assertSame(1, $here->getBuildLevel());

        $this->expectException(InvalidPlayerActionException::class);
        $game->buildHouse($elsewhere); // not standing on it
    }

    public function testBuildRequiresMonopolyInClassicMode(): void
    {
        $game = new Game(['A', 'B']);
        $game->start();
        $a = $game->getPlayers()[0];
        $prop = tilesByType($game, TileType::PROPERTY)[0];
        $prop->setOwner($a); // owns only one of the group
        $this->expectException(InvalidPlayerActionException::class);
        $game->buildHouse($prop);
    }

    // --- Free Parking jackpot ---

    public function testFreeParkingJackpot(): void
    {
        $game = new Game(['A', 'B'], new GameRules(true, false));
        $game->addObserver(new FreeParkingObserver($game));
        $game->start();
        $a = $game->getPlayers()[0];
        $b = $game->getPlayers()[1];

        $tax = tilesByType($game, TileType::TAX)[0];
        $a->setPosition($tax->getPosition());
        $tax->landOn($a, $game);
        $taxAmount = $tax->getAmount();

        $fp = tilesByType($game, TileType::FREE_PARKING)[0];
        $mb = $b->getMoney();
        $b->setPosition($fp->getPosition());
        $fp->landOn($b, $game);

        $this->assertSame($mb + $taxAmount, $b->getMoney(), 'B collects the jackpot');
    }

    public function testFreeParkingDoesNothingWhenRuleOff(): void
    {
        $game = new Game(['A', 'B']); // jackpot off, no observer
        $game->start();
        $a = $game->getPlayers()[0];
        $tax = tilesByType($game, TileType::TAX)[0];
        $a->setPosition($tax->getPosition());
        $tax->landOn($a, $game);

        $fp = tilesByType($game, TileType::FREE_PARKING)[0];
        $before = $a->getMoney();
        $a->setPosition($fp->getPosition());
        $fp->landOn($a, $game);
        $this->assertSame($before, $a->getMoney());
    }
}
