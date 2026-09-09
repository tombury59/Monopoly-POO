<?php

class Go extends Tile {

    public function __construct(string $name, Square $position){
        parent::__construct($name,$position);
        $this->type = TileType::GO;
    }

    protected function applyEffect(Player $player, Game $game): void {
        // TODO gestion de passer devant / s'arreter pile dessus pour un *2
        $player->addMoney(200);
    }
}