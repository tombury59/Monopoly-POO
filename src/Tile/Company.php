<?php

class Company extends Tile implements Mortgageable{

    private int $price;
    private ?Player $owner = null;

    private bool $mortgaged = false;

    public function __construct(string $name, Square $position, int $price){
        parent::__construct($name,$position);
        $this->type = TileType::COMPANY;
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

    public  function isOwnable(): bool {
        return true;
    }

    protected function applyEffect(Player $player, Game $game): void {
        $parCarte = $game->consumeCardArrival() === CardEffectType::NEAREST_UTILITY;

        if (!$this->isOwned() || $this->getOwner() === $player || $this->isMortgaged()) {
            return;
        }

        $owner = $this->getOwner();

        if ($parCarte) {
            $toPay = $game->rollForCardRent() * 10;   // card: 10x reroll
        } else {
            $nbCompany = $game->getBoard()->countOwnedByType($owner, TileType::COMPANY, true);
            $multiplier = match ($nbCompany) {
                1 => 4,
                2 => 10,
                default => 0,
            };

            if ($multiplier === 0) {
                return;
            }

            $toPay = $game->getLastDiceTotal() * $multiplier;
        }

        $player->removeMoney($toPay);
        $owner->addMoney($toPay);

        $game->emit(GameEventType::RENT_PAID, ['player' => $player->getName(), 'amount' => $toPay, 'tile' => $this->getName(), 'owner' => $owner->getName()]);
    }

    public function isMortgaged(): bool {
        return $this->mortgaged;
    }

    public function setMortgaged(bool $mortgaged): void {
        $this->mortgaged = $mortgaged;
    }
}