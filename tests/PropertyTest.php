<?php

use PHPUnit\Framework\TestCase;

final class PropertyTest extends TestCase
{
    private function make(): Property
    {
        return (new TileFactory())->create(
            TileType::PROPERTY,
            'Test',
            new Square(1),
            ['colorGroup' => ColorGroup::BROWN, 'price' => 60, 'rents' => [2, 10, 30, 90, 160, 250], 'housePrice' => 50]
        );
    }

    public function testRentDependsOnBuildLevel(): void
    {
        $p = $this->make();
        $this->assertSame(2, $p->getRent(), 'bare rent');
        $p->setBuildLevel(3);
        $this->assertSame(90, $p->getRent(), 'rent at 3 houses');
        $p->setBuildLevel(5);
        $this->assertSame(250, $p->getRent(), 'hotel rent');
    }

    public function testOwnershipAndMortgage(): void
    {
        $p = $this->make();
        $owner = new Player('A');
        $this->assertFalse($p->isOwned());
        $p->setOwner($owner);
        $this->assertTrue($p->isOwned());
        $this->assertFalse($p->isMortgaged());
        $p->setMortgaged(true);
        $this->assertTrue($p->isMortgaged());
    }

    public function testBuildLevelBounds(): void
    {
        $this->expectException(InvalidPropertyLevelException::class);
        $this->make()->setBuildLevel(6);
    }
}
