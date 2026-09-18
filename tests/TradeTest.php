<?php

use PHPUnit\Framework\TestCase;

final class TradeTest extends TestCase
{
    private Game $game;
    private Player $a;
    private Player $b;
    /** @var Property[] */
    private array $browns;

    protected function setUp(): void
    {
        $this->game = new Game(['A', 'B']);
        $this->game->start();
        $this->a = $this->game->getPlayers()[0];
        $this->b = $this->game->getPlayers()[1];
        $this->browns = array_values(array_filter(
            tilesByType($this->game, TileType::PROPERTY),
            fn(Property $p) => $p->getColorGroup() === ColorGroup::BROWN
        ));
    }

    public function testSwapTilesTransfersOwnership(): void
    {
        $tileA = $this->browns[0];
        $tileB = $this->browns[1];
        $tileA->setOwner($this->a);
        $tileB->setOwner($this->b);

        $this->game->trade($this->a, $this->b, [$tileA], [$tileB]);

        $this->assertSame($this->b, $tileA->getOwner());
        $this->assertSame($this->a, $tileB->getOwner());
    }

    public function testTradeWithMoney(): void
    {
        $tileB = $this->browns[1];
        $tileB->setOwner($this->b);
        $ma = $this->a->getMoney();
        $mb = $this->b->getMoney();

        // A gives nothing but pays 100 to B for B's tile
        $this->game->trade($this->a, $this->b, [], [$tileB], 100);

        $this->assertSame($this->a, $tileB->getOwner());
        $this->assertSame($ma - 100, $this->a->getMoney());
        $this->assertSame($mb + 100, $this->b->getMoney());
    }

    public function testRejectsTileNotOwned(): void
    {
        $tile = $this->browns[0];
        $tile->setOwner($this->b); // belongs to B, not A
        $this->expectException(InvalidPlayerActionException::class);
        $this->game->trade($this->a, $this->b, [$tile], []);
    }

    public function testRejectsBuiltTile(): void
    {
        // give A the whole brown group so building is legal, then build
        foreach ($this->browns as $p) {
            $p->setOwner($this->a);
        }
        $this->a->setPosition($this->browns[0]->getPosition());
        $this->browns[0]->setBuildLevel(1);

        $this->expectException(InvalidPlayerActionException::class);
        $this->game->trade($this->a, $this->b, [$this->browns[0]], []);
    }

    public function testAtomicityNoPartialTransferOnFailure(): void
    {
        $good = $this->browns[0];
        $built = $this->browns[1];
        $good->setOwner($this->a);
        $built->setOwner($this->a);
        $built->setBuildLevel(1); // makes the trade invalid

        try {
            $this->game->trade($this->a, $this->b, [$good, $built], []);
            $this->fail('should have thrown');
        } catch (InvalidPlayerActionException $e) {
            // nothing transferred
            $this->assertSame($this->a, $good->getOwner(), 'valid tile not moved because trade was rejected');
        }
    }

    public function testGroupCompletionEnablesBuilding(): void
    {
        // A owns brown[0], B owns brown[1]; after trade A owns both -> can build
        $this->browns[0]->setOwner($this->a);
        $this->browns[1]->setOwner($this->b);

        $this->game->trade($this->a, $this->b, [], [$this->browns[1]]);

        $this->assertTrue($this->game->getBoard()->ownsWholeGroup($this->a, ColorGroup::BROWN));
        $this->a->setPosition($this->browns[0]->getPosition());
        $this->game->buildHouse($this->browns[0]); // classic rules: whole group required
        $this->assertSame(1, $this->browns[0]->getBuildLevel());
    }

    public function testProposeTradeOfferedInActionPhase(): void
    {
        $ref = new ReflectionProperty(Game::class, 'phase');
        $ref->setValue($this->game, TurnPhase::AWAITING_ACTION);
        $this->assertContains(PlayerAction::PROPOSE_TRADE, $this->game->getAvailableActions());
    }
}
