<?php

class GoToJail extends Tile {

    public function __construct(string $name, Square $position){
        parent::__construct($name,$position);
        $this->type = TileType::GO_TO_JAIL;
    }

    protected function applyEffect(Player $player, Game $game): void {
        $jailTile = $game->getBoard()->findTileByType(TileType::JAIL);

        if ($jailTile === null) {
            throw new MonopolyException("Aucune case Jail trouvée sur le plateau.");
        }

        $player->setInJail(true);
        $player->setPosition($jailTile->getPosition());
        $game->emit(GameEventType::SENT_TO_JAIL, ['player' => $player->getName()]);
    }
}