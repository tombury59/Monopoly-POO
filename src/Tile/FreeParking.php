<?php

class FreeParking extends Tile {

    public function __construct(string $name, Square $position){
        parent::__construct($name,$position);
        $this->type = TileType::FREE_PARKING;
    }

    protected function applyEffect(Player $player, Game $game): void {
        if (!$game->getRules()->isFreeParkingJackpotEnabled()) {
            return;
        }
        $pot = $game->collectFreeParkingPot();
        if ($pot > 0) {
            $player->addMoney($pot);
            $game->emit(GameEventType::MONEY_GAINED, ['player' => $player->getName(), 'amount' => $pot]);
        }
    }
}