<?php

use PHPUnit\Framework\TestCase;

final class BoardTest extends TestCase
{
    private TileFactory $factory;
    private Board $board;
    private Property $brown1;
    private Property $brown2;
    private Station $stationA;
    private Station $stationB;

    protected function setUp(): void
    {
        $this->factory = new TileFactory();
        $this->board = new Board();

        $this->brown1 = $this->factory->create(TileType::PROPERTY, 'B1', new Square(1), ['colorGroup' => ColorGroup::BROWN, 'price' => 60, 'rents' => [2, 10, 30, 90, 160, 250], 'housePrice' => 50]);
        $this->brown2 = $this->factory->create(TileType::PROPERTY, 'B2', new Square(3), ['colorGroup' => ColorGroup::BROWN, 'price' => 60, 'rents' => [4, 20, 60, 180, 320, 450], 'housePrice' => 50]);
        $this->stationA = $this->factory->create(TileType::STATION, 'S1', new Square(5), ['price' => 200]);
        $this->stationB = $this->factory->create(TileType::STATION, 'S2', new Square(15), ['price' => 200]);

        foreach ([$this->brown1, $this->brown2, $this->stationA, $this->stationB] as $t) {
            $this->board->placeTile($t);
        }
    }

    public function testGetTileAt(): void
    {
        $this->assertSame($this->brown1, $this->board->getTileAt(new Square(1)));
        $this->assertNull($this->board->getTileAt(new Square(2)));
    }

    public function testOwnsWholeGroup(): void
    {
        $a = new Player('A');
        $this->brown1->setOwner($a);
        $this->assertFalse($this->board->ownsWholeGroup($a, ColorGroup::BROWN));
        $this->brown2->setOwner($a);
        $this->assertTrue($this->board->ownsWholeGroup($a, ColorGroup::BROWN));
    }

    public function testCountOwnedByType(): void
    {
        $a = new Player('A');
        $this->stationA->setOwner($a);
        $this->stationB->setOwner($a);
        $this->assertSame(2, $this->board->countOwnedByType($a, TileType::STATION));
        $this->stationB->setMortgaged(true);
        $this->assertSame(1, $this->board->countOwnedByType($a, TileType::STATION, true));
    }

    public function testBuildLevelBoundsInGroup(): void
    {
        $this->brown1->setOwner(new Player('A'));
        $this->brown1->setBuildLevel(2);
        $this->assertSame(0, $this->board->minBuildLevelInGroup(ColorGroup::BROWN));
        $this->assertSame(2, $this->board->maxBuildLevelInGroup(ColorGroup::BROWN));
    }

    public function testGroupHasMortgage(): void
    {
        $this->assertFalse($this->board->groupHasMortgage(ColorGroup::BROWN));
        $this->brown1->setMortgaged(true);
        $this->assertTrue($this->board->groupHasMortgage(ColorGroup::BROWN));
    }

    public function testFindTileByType(): void
    {
        $this->assertSame($this->stationA, $this->board->findTileByType(TileType::STATION));
    }

    public function testFindNearestByType(): void
    {
        $nearest = $this->board->findNearestByType(new Square(6), TileType::STATION);
        $this->assertNotNull($nearest);
        $this->assertSame(15, $nearest->getIndex(), 'nearest station forward from 6 is index 15');
    }
}
