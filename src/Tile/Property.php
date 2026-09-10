<?php

class Property extends Tile {
    private ColorGroup $colorGroup;
    private int $price;
    private int $rent;
    private ?Player $owner = null;

    public function __construct(string $name, Square $position, ColorGroup $colorGroup, int $price, int $rent){
        parent::__construct($name,$position);
        $this->type = TileType::PROPERTY;
        $this->colorGroup = $colorGroup;
        $this->price = $price;
        $this->rent = $rent;
    }

    public function getColorGroup(): ColorGroup {
        return $this->colorGroup;
    }

    public function getPrice(): int {
        return $this->price;
    }

    public function getRent(): int {
        return $this->rent;
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

    public  function isOwnable(): bool {
        return true;
    }

    protected function applyEffect(Player $player, Game $game): void {
        if($this->isOwned() && $this->getOwner() !== $player){
            $player->removeMoney($this->getRent()); 
            $this->getOwner()->addMoney($this->getRent());
        }
    }
}