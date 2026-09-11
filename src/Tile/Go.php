<?php

class Go extends Tile {

    public function __construct(string $name, Square $position){
        parent::__construct($name,$position);
        $this->type = TileType::GO;
    }

    protected function applyEffect(Player $player, Game $game): void {
        $player->addMoney(200);
        // TODO: notify (money_gained)
    }
}