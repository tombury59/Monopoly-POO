<?php

class Station extends Tile {

    private int $price;
    private ?Player $owner = null;

    public function __construct(string $name, Square $position, int $price){
        parent::__construct($name,$position);
        $this->type = TileType::STATION;
        $this->price = $price;
    }

    public function getPrice(): int {
        return $this->price;
    }

    public function getOwner(): ?Player {
        return $this->owner;
    }

    public function setOwner(Player $player): void {
        $this->owner = $player;
    }

    public function isOwned(): bool {
        return $this->owner !== null;
    }

    public function isOwnable(): bool {
        return true;
    }

        protected function applyEffect(Player $player, Game $game): void {
            if($this->isOwned() && $this->getOwner() !== $player){
                $nb = $game->getBoard()->countOwnedByType($this->getOwner(), TileType::STATION);
                $topay = 25 * (2 ** ($nb - 1));
                $player->removeMoney($topay);
                $this->getOwner()->addMoney($topay);
                // TODO: notify
            }
        }
}