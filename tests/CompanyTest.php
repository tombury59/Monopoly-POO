<?php

use PHPUnit\Framework\TestCase;

final class CompanyTest extends TestCase
{
    public function testNoRentWhenUnownedOrOwnOrMortgaged(): void
    {
        $game = new Game(['A', 'B']);
        $game->start();
        $a = $game->getPlayers()[0];
        $b = $game->getPlayers()[1];
        $company = tilesByType($game, TileType::COMPANY)[0];
        $a->setPosition($company->getPosition());

        // unowned
        $before = $a->getMoney();
        $company->landOn($a, $game);
        $this->assertSame(0, $before - $a->getMoney());

        // own
        $company->setOwner($a);
        $before = $a->getMoney();
        $company->landOn($a, $game);
        $this->assertSame(0, $before - $a->getMoney());

        // mortgaged (owned by B)
        $company->setOwner($b);
        $company->setMortgaged(true);
        $before = $a->getMoney();
        $company->landOn($a, $game);
        $this->assertSame(0, $before - $a->getMoney());
    }

    public function testNearestCompanyCardChargesMultipleOfTen(): void
    {
        $game = new Game(['A', 'B']);
        $game->start();
        $a = $game->getPlayers()[0];
        $b = $game->getPlayers()[1];
        $company = tilesByType($game, TileType::COMPANY)[0];
        $company->setOwner($b);
        $a->setPosition(new Square(($company->getPosition()->getIndex() + 39) % 40)); // just before it

        $before = $a->getMoney();
        (new Card('nearest utility', CardEffectType::NEAREST_UTILITY))->apply($a, $game);
        $paid = $before - $a->getMoney();

        $this->assertGreaterThan(0, $paid);
        $this->assertSame(0, $paid % 10, 'rent is 10x a dice roll');
        $this->assertLessThanOrEqual(120, $paid);
    }
}
