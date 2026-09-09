<?php

class Player {

    private string $name;
    private int $money;
    private Square $position;
    private bool $inJail = false;

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

}