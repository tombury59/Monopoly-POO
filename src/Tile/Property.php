<?php

class Property extends Tile {
    private ColorGroup $colorGroup;
    private int $price;
    private int $rent;
    private ?Player $owner = null;

    private int $buildLevel = 0;
    private array $rents = [];
    private int $housePrice = 0;

    private bool $mortgaged = false;

    public function __construct(string $name, Square $position, ColorGroup $colorGroup, int $price, int $rent,array $rents = [], int $housePrice = 0){
        parent::__construct($name,$position);
        $this->type = TileType::PROPERTY;
        $this->colorGroup = $colorGroup;
        $this->price = $price;
        $this->rent = $rent;
        $this->rents = $rents;
        $this->housePrice = $housePrice;
    }

    public function getColorGroup(): ColorGroup {
        return $this->colorGroup;
    }

    public function getPrice(): int {
        return $this->price;
    }

    public function getRent(): int {
        return $this->rents[$this->buildLevel] ?? $this->rent;
    }

    public function getRents(): array {
        return $this->rents;
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

            $amount = $this->getRent();
            if($this->buildLevel === 0 && $game->getBoard()->ownsWholeGroup($this->getOwner(), $this->getColorGroup())){
                $amount *= 2;
            }

            $player->removeMoney($amount);
            $this->getOwner()->addMoney($amount);
            // TODO: notify
        }
    }

    public function getBuildLevel(): int {
        return $this->buildLevel;
    }

    public function setBuildLevel(int $level): void {
        if($level<0 || $level>5){
            throw new InvalidPropertyLevelException("Ce batiment n'est pas disponible");
        }
        $this->buildLevel = $level;
    }

    public function getHousePrice(): int {
        return $this->housePrice;
    }

    public function isMortgaged(): bool {
        return $this->mortgaged;
    }

    public function setMortgaged(bool $mortgaged): void {
        $this->mortgaged = $mortgaged;
    }
}