<?php

class GoToJail extends Tile {

    public function __construct(string $name, Square $position){
        parent::__construct($name,$position);
        $this->type = TileType::GO_TO_JAIL;
    }

    protected function applyEffect(Player $player, Game $game): void {
        $player->setInJail(true);
        $jailSquare = new Square(10);
        $player->setPosition($jailSquare);
    }
}