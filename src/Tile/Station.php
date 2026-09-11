<?php

class Station extends Tile implements Mortgageable {

    private int $price;
    private ?Player $owner = null;

    private bool $mortgaged = false;

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

    public function setOwner(?Player $player): void {
        $this->owner = $player;
    }

    public function isOwned(): bool {
        return $this->owner !== null;
    }

    public function isOwnable(): bool {
        return true;
    }

    protected function applyEffect(Player $player, Game $game): void {
        if($this->isOwned() && $this->getOwner() !== $player && !$this->isMortgaged()){
            $nb = $game->getBoard()->countOwnedByType($this->getOwner(), TileType::STATION,true);
            $topay = 25 * (2 ** ($nb - 1));
            $player->removeMoney($topay);

            $owner = $this->getOwner();
            $owner->addMoney($topay);

            $game->emit(GameEventType::RENT_PAID, ['player' => $player->getName(), 'amount' => $topay, 'tile' => $this->getName(), 'owner' => $owner->getName()]);
        }
    }

    public function isMortgaged(): bool {
        return $this->mortgaged;
    }

    public function setMortgaged(bool $mortgaged): void {
        $this->mortgaged = $mortgaged;
    }
}