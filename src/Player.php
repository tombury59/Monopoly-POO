<?php

class Player {

    private string $name;
    private int $money;
    private Square $position;
    private bool $inJail = false;
    private int $turnsInJail = 0;
    private int $getOutOfJailCards = 0;

    public function __construct(string $name, int $startingMoney = 1500){
        $this->name = $name;
        $this->money = $startingMoney;
        $this->position = new Square(0);
    }

    public function getName(): string {
        return $this->name;
    }

    public function getMoney(): int {
        return $this->money;
    }

    public function addMoney(int $amount): void {
        $this->money += $amount;
    }

    public function removeMoney(int $amount): void {
        if($amount>$this->money) throw new InsufficientFundsException("Le joueur n'a pas les fonds nécessaires pour payer la somme.");
        $this->money -= $amount;
    }

    public function getPosition(): Square {
        return $this->position;
    }

    public function setPosition(Square $position): void  {
        $this->position = $position;
    }

    public function isInJail(): bool {
        return $this->inJail;
    }

    public function setInJail(bool $inJail): void {
        $this->inJail = $inJail;
    }

    public function getTurnsInJail(): int {
        return $this->turnsInJail;
    }

    public function addTurnsInJail(): void {
        $this->turnsInJail += 1;
    }

    public function resetTurnsInJail(): void {
        $this->turnsInJail = 0;
    }

    public function addGetOutOfJailCard(): void {
        $this->getOutOfJailCards += 1;
    }

    public function hasGetOutOfJailCard(): bool {
        return $this->getOutOfJailCards > 0;
    }

    public function useGetOutOfJailCard(): void {
        if($this->getOutOfJailCards <= 0) {
            throw new InvalidPlayerActionException("Le joueur n'a plus de carte de sortie de prison.");
        }
        $this->getOutOfJailCards -= 1;
    }

}