<?php

class Tax extends Tile {

    private int $amount;

    public function __construct(string $name, Square $position, int $amount){
        parent::__construct($name,$position);
        $this->type = TileType::TAX;
        $this->amount = $amount;
    }
    
    public function getAmount(): int {
        return $this->amount;
    }

    protected function applyEffect(Player $player, Game $game): void {
        $player->removeMoney($this->getAmount());
        $game->emit(GameEventType::TAX_PAID, ['player' => $player->getName(), 'amount' => $this->getAmount()]);
    }
}