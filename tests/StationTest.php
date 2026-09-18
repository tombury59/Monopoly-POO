<?php

use PHPUnit\Framework\TestCase;

final class StationTest extends TestCase
{
    public function testRentDoublesPerStationOwned(): void
    {
        $game = new Game(['A', 'B']);
        $game->start();
        $a = $game->getPlayers()[0];
        $b = $game->getPlayers()[1];
        $stations = tilesByType($game, TileType::STATION);

        // B owns 1 station -> rent 25
        $stations[0]->setOwner($b);
        $a->setPosition($stations[0]->getPosition());
        $before = $a->getMoney();
        $stations[0]->landOn($a, $game);
        $this->assertSame(25, $before - $a->getMoney());

        // B owns 2 stations -> rent 50
        $stations[1]->setOwner($b);
        $a->setPosition($stations[1]->getPosition());
        $before = $a->getMoney();
        $stations[1]->landOn($a, $game);
        $this->assertSame(50, $before - $a->getMoney());
    }

    public function testNoRentOnOwnStation(): void
    {
        $game = new Game(['A', 'B']);
        $game->start();
        $a = $game->getPlayers()[0];
        $station = tilesByType($game, TileType::STATION)[0];
        $station->setOwner($a);
        $a->setPosition($station->getPosition());
        $before = $a->getMoney();
        $station->landOn($a, $game);
        $this->assertSame(0, $before - $a->getMoney());
    }
}
