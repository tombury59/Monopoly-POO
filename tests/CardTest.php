<?php

use PHPUnit\Framework\TestCase;

final class CardTest extends TestCase
{
    private Game $game;
    private Player $a;

    protected function setUp(): void
    {
        $this->game = new Game(['A', 'B']);
        $this->game->start();
        $this->a = $this->game->getPlayers()[0];
    }

    public function testGainMoney(): void
    {
        $before = $this->a->getMoney();
        (new Card('gain', CardEffectType::GAIN_MONEY, 200))->apply($this->a, $this->game);
        $this->assertSame($before + 200, $this->a->getMoney());
    }

    public function testLoseMoney(): void
    {
        $before = $this->a->getMoney();
        (new Card('lose', CardEffectType::LOSE_MONEY, 100))->apply($this->a, $this->game);
        $this->assertSame($before - 100, $this->a->getMoney());
    }

    public function testExitJailCardGranted(): void
    {
        (new Card('exit', CardEffectType::EXIT_JAIL))->apply($this->a, $this->game);
        $this->assertTrue($this->a->hasGetOutOfJailCard());
    }

    public function testGoToJail(): void
    {
        (new Card('jail', CardEffectType::GO_TO_JAIL))->apply($this->a, $this->game);
        $this->assertTrue($this->a->isInJail());
    }

    public function testMoveTo(): void
    {
        (new Card('move', CardEffectType::MOVE_TO, 20))->apply($this->a, $this->game);
        $this->assertSame(20, $this->a->getPosition()->getIndex());
    }

    public function testNearestStationCardChargesDoubleRent(): void
    {
        $b = $this->game->getPlayers()[1];
        $station = tilesByType($this->game, TileType::STATION)[0];
        $station->setOwner($b); // B owns 1 station -> normal 25, via card -> 50
        $this->a->setPosition(new Square(($station->getPosition()->getIndex() + 39) % 40));

        $before = $this->a->getMoney();
        (new Card('nearest station', CardEffectType::NEAREST_STATION))->apply($this->a, $this->game);
        $this->assertSame(50, $before - $this->a->getMoney());
    }
}
