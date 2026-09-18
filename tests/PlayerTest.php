<?php

use PHPUnit\Framework\TestCase;

final class PlayerTest extends TestCase
{
    public function testStartingMoney(): void
    {
        $this->assertSame(1500, (new Player('A'))->getMoney());
    }

    public function testAddAndRemoveMoney(): void
    {
        $p = new Player('A');
        $p->addMoney(500);
        $p->removeMoney(200);
        $this->assertSame(1800, $p->getMoney());
    }

    public function testRemoveMoneyThrowsWithAmount(): void
    {
        $p = new Player('A', 100);
        try {
            $p->removeMoney(150);
            $this->fail('should have thrown');
        } catch (InsufficientFundsException $e) {
            $this->assertSame(150, $e->getAmount());
            $this->assertSame(100, $p->getMoney(), 'balance untouched on failure');
        }
    }

    public function testJailFlags(): void
    {
        $p = new Player('A');
        $this->assertFalse($p->isInJail());
        $p->setInJail(true);
        $p->addTurnsInJail();
        $this->assertTrue($p->isInJail());
        $this->assertSame(1, $p->getTurnsInJail());
        $p->resetTurnsInJail();
        $this->assertSame(0, $p->getTurnsInJail());
    }

    public function testJailCards(): void
    {
        $p = new Player('A');
        $this->assertFalse($p->hasGetOutOfJailCard());
        $p->addGetOutOfJailCard();
        $this->assertTrue($p->hasGetOutOfJailCard());
        $p->useGetOutOfJailCard();
        $this->assertFalse($p->hasGetOutOfJailCard());
    }

    public function testUsingMissingCardThrows(): void
    {
        $this->expectException(InvalidPlayerActionException::class);
        (new Player('A'))->useGetOutOfJailCard();
    }
}
