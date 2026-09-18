<?php

use PHPUnit\Framework\TestCase;

final class SquareTest extends TestCase
{
    public function testIndexIsStored(): void
    {
        $this->assertSame(7, (new Square(7))->getIndex());
    }

    public function testNextWrapsForward(): void
    {
        $this->assertSame(3, (new Square(39))->next(4)->getIndex());
    }

    public function testNextHandlesNegativeSteps(): void
    {
        $this->assertSame(38, (new Square(2))->next(-4)->getIndex());
    }

    public function testEquals(): void
    {
        $this->assertTrue((new Square(5))->equals(new Square(5)));
        $this->assertFalse((new Square(5))->equals(new Square(6)));
    }

    public function testKeyRoundTrip(): void
    {
        $this->assertSame(12, Square::fromKey((new Square(12))->toKey())->getIndex());
    }

    public function testRejectsOutOfRangeIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Square(40);
    }
}
