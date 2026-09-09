<?php

class Jail extends Tile {

    public function __construct(string $name, Square $position){
        parent::__construct($name,$position);
        $this->type = TileType::JAIL;
    }

    protected function applyEffect(Player $player, Game $game): void {
        // zero effect yet
    }
}