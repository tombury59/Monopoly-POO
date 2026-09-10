<?php

class CommunityChest extends Tile {

    public function __construct(string $name, Square $position){
        parent::__construct($name,$position);
        $this->type = TileType::COMMUNITY_CHEST;
    }

    protected function applyEffect(Player $player, Game $game): void {
        // TODO gestion des cartes => effet temporaire
        $player->addMoney(200);
        // TODO: notify
    }
}