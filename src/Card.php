<?php

class Card{
    private string $description;
    private CardEffectType $effectType;
    private int $value;
    private int $hotelValue;

    public function __construct(string $description, CardEffectType $effectType, int $value = 0,$hotelValue = 0) {
        $this->description = $description;
        $this->effectType = $effectType;
        $this->value = $value;
        $this->hotelValue= $hotelValue;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function apply(Player $player, Game $game): void {
        match ($this->effectType) {
            CardEffectType::GAIN_MONEY => $player->addMoney($this->value),
            CardEffectType::LOSE_MONEY => $this->loseMoney($player, $game),
            CardEffectType::MOVE_TO => $this->moveTo($player, $game, $this->value),
            CardEffectType::MOVE_STEPS => $this->moveSteps($player,$game),
            CardEffectType::GO_TO_JAIL =>$this->goToJail($player,$game),
            CardEffectType::EXIT_JAIL => $this->exitJail($player),
            CardEffectType::PAY_ALL =>$this->payOrReceiveAll($player,$game,true),
            CardEffectType::RECEIVE_ALL =>$this->payOrReceiveAll($player,$game,false),
            CardEffectType::REPAIR_BUILDINGS =>$this->repairBuildings($player, $game),
            CardEffectType::NEAREST_UTILITY => $this->moveToNearest($player, $game, TileType::COMPANY),
            CardEffectType::NEAREST_STATION => $this->moveToNearest($player, $game, TileType::STATION),
        };
    }

    private function moveTo(Player $player, Game $game, int $target): void {
        $c    = $player->getPosition()->getIndex();
        $size = $game->getBoard()->getBoardSize();

        $steps = ($target-$c+$size) % $size;

        $game->resolveMovement($player, $steps);
    }

    private function moveSteps(Player $player,Game $game): void {
        $game->resolveMovement($player, $this->value);

    }

    private function goToJail(Player $player,Game $game): void {
        $goToJailTile = $game->getBoard()->findTileByType(TileType::GO_TO_JAIL);

        if ($goToJailTile === null) {
            throw new MonopolyException("Aucune case GoToJail trouvée sur le plateau.");
        }

        $goToJailTile->landOn($player, $game);
    }

    private function payOrReceiveAll(Player $player,Game $game,bool $playerPays): void {
        $players=$game->getPlayers();
        foreach($players as $receiver){
            if($receiver !== $player){
                try {
                    if($playerPays){
                        $player->removeMoney($this->value);
                        $receiver->addMoney($this->value);
                    }
                    else{
                        $receiver->removeMoney($this->value);
                        $player->addMoney($this->value);
                    }
                } catch (InsufficientFundsException $e) {
                    if ($playerPays) {
                        $game->declareBankruptcy($player);
                        return; 
                    }
                    $game->declareBankruptcy($receiver);
                }
            }
        }
    }

    private function exitJail(Player $player): void {
        $player->addGetOutOfJailCard();
    }
    
    private function loseMoney(Player $player, Game $game): void {
        $player->removeMoney($this->value);
        $game->emit(GameEventType::MONEY_LOST, ['player' => $player->getName(), 'amount' => $this->value]);
    }

    private function repairBuildings(Player $player, Game $game): void {
        $nbHotels=0;
        $nbHouse=0;

        $tiles=$game->getBoard()->getTiles();
        foreach($tiles as $tile){
            if(($tile instanceof Property) && ($tile->getOwner() === $player)){
                $level = $tile->getBuildLevel();
                if($level<0 || $level>5){
                    throw new MonopolyException("Les batiments n'existent pas.");
                }
                if($level >= 1 && $level <= 4){
                    $nbHouse += $level;
                } elseif($level === 5){
                    $nbHotels += 1;
                }
            }
        }
        $total = $nbHouse * $this->value + $nbHotels * $this->hotelValue;
        $player->removeMoney($total);
        $game->emit(GameEventType::MONEY_LOST, ['player' => $player->getName(), 'amount' => $total]);
    }

    private function moveToNearest(Player $player, Game $game, TileType $type): void {

        $target = $game->getBoard()->findNearestByType($player->getPosition(), $type);

        if ($target === null) {
            throw new MonopolyException("Aucune case de ce type sur le plateau.");
        }

        $game->markCardArrival($this->effectType);
        $this->moveTo($player, $game, $target->getIndex());
    }
}