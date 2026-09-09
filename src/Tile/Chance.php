<?php

class Chance extends Tile {

    public function __construct(string $name, Square $position){
        parent::__construct($name,$position);
        $this->type = TileType::CHANCE;
    }

    protected function applyEffect(Player $player, Game $game): void {
        // TODO gestion des cartes => effet temporaire
        $player->addMoney(200);
    }
}