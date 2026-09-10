<?php

class Square{

    public const BOARD_SIZE = 40;

    private int $index;

    public function __construct(int $index){
        if($index<0 || $index > self::BOARD_SIZE-1){
            throw new InvalidArgumentException("L'index doit être compris entre 0 et 39, {$index} donné.");
        }
        $this->index = $index;
    }
    
    public function getIndex(): int {
        return $this->index;
    }

    public function equals(Square $other): bool {
        return $other->getIndex() === $this->getIndex();
    }
    
    public function toKey(): string {
        return strval($this->getIndex());
    }

    public static function fromKey(string $key): Square {
        return new Square((int) $key);
    }

    public function next(int $steps): Square {
        return new Square((($this->getIndex() + $steps) % self::BOARD_SIZE + self::BOARD_SIZE) % self::BOARD_SIZE);
    }
}