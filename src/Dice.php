<?php

class Dice{
    
    private int $sides;

    public function __construct(int $sides = 6) {
        $this->sides = $sides;
    }

    public function getSides(): int {
        return $this->sides;
    }

    public function roll(): int {
        return random_int(1,$this->getSides());
    }
    // return a array with two values
    public function rollTwo(): array {
        return [
            random_int(1,$this->getSides()),
            random_int(1,$this->getSides())
        ];
    }
}