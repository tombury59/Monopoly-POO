<?php

class FreeParking extends Tile {

    public function __construct(string $name, Square $position){
        parent::__construct($name,$position);
        $this->type = TileType::FREE_PARKING;
    }

    protected function applyEffect(Player $player, Game $game): void {
        // TODO gagner tout l'argent récolté au millieu du plateau
    }
}