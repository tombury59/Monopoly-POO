<?php

class Board {
    private array $squares;
    
    public function __construct(array $squares) {
        $this->squares = $squares;
    }

    public function getSquares(): array {
        return $this->squares;
    }

    public function getSquare(int $id): Square {
        return $this->squares[$id];
    }
}