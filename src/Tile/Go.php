<?php

class Go extends Tile {

    public function __construct(string $name, Square $position){
        parent::__construct($name,$position);
        $this->type = TileType::GO;
    }

    protected function applyEffect(Player $player, Game $game): void {
        $player->addMoney(200);
        $game->emit(GameEventType::MONEY_GAINED, ['player' => $player->getName(), 'amount' => 200]);
    }
}