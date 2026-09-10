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
            CardEffectType::LOSE_MONEY => $player->removeMoney($this->value),
            CardEffectType::MOVE_TO => $this->moveTo($player, $game, $this->value),
            CardEffectType::MOVE_STEPS => $this->moveSteps($player,$game),
            CardEffectType::GO_TO_JAIL =>$this->goToJail($player,$game),
            CardEffectType::EXIT_JAIL => $this->exitJail($player),
            CardEffectType::PAY_ALL =>$this->payOrReceiveAll($player,$game,true),
            CardEffectType::RECEIVE_ALL =>$this->payOrReceiveAll($player,$game,false),
            CardEffectType::REPAIR_BUILDINGS =>$this->repairBuildings($player, $game),
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
        // TODO: gestion de la faillite
        $players=$game->getPlayers();
        foreach($players as $receiver){
            if($receiver !== $player){
                if($playerPays){
                    $player->removeMoney($this->value);
                    $receiver->addMoney($this->value);
                }
                else{
                    $receiver->removeMoney($this->value);
                    $player->addMoney($this->value);
                }

            }
        }
    }

    private function exitJail(Player $player): void {
        $player->setInJail(false);
        $player->resetTurnsInJail();
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
        $player->removeMoney($nbHouse * $this->value + $nbHotels * $this->hotelValue);
    }


}