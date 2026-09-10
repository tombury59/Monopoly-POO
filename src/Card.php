<?php

class Card{
    private string $description;
    private CardEffectType $effectType;
    private int $value;

    public function __construct(string $description, CardEffectType $effectType, int $value = 0) {
        $this->description = $description;
        $this->effectType = $effectType;
        $this->value = $value;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function apply(Player $player, Game $game): void {
        match ($this->effectType) {
            CardEffectType::GAIN_MONEY => $player->addMoney($this->value),
            CardEffectType::LOSE_MONEY => $player->removeMoney($this->value),
            CardEffectType::MOVE_TO => $this->moveTo($player, $game, new Square($this->value)),
            CardEffectType::MOVE_STEPS => $this->moveSteps($player,$game),
            CardEffectType::GO_TO_JAIL =>$this->goToJail($player,$game),
            CardEffectType::EXIT_JAIL => $this->exitJail($player),
            CardEffectType::PAY_ALL =>$this->payOrReceiveAll($player,$game,true),
            CardEffectType::RECEIVE_ALL =>$this->payOrReceiveAll($player,$game,false),
        };
    }

    private function moveTo(Player $player, Game $game, Square $target): void {
        if ($target->getIndex() < $player->getPosition()->getIndex()) {
            $player->addMoney(200);
            // TODO: notify
        }

        $this->resolveLanding($player, $game, $target);
    }

    private function moveSteps(Player $player,Game $game): void {
        $from = $player->getPosition()->getIndex();

        if ($this->value > 0 && $from + $this->value >= $game->getBoard()->getBoardSize()) {
            $player->addMoney(200);
            // TODO: notify
        }

        $this->resolveLanding($player, $game, $player->getPosition()->next($this->value));
    }

    private function resolveLanding(Player $player, Game $game, Square $target): void {
        $player->setPosition($target);

        $tile = $game->getBoard()->getTileAt($target);
        if ($tile !== null) {
            $tile->landOn($player, $game);
        }
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
}