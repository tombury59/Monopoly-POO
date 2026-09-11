<?php

class Board {
    private array $tiles = [];

    public function placeTile(Tile $tile): void {
        $this->tiles[$tile->getPosition()->toKey()] = $tile;
    }

    public function getTileAt(Square $position): ?Tile {
        return $this->tiles[$position->toKey()] ?? null;
    }

    public function hasTileAt(Square $position): bool {
        return array_key_exists($position->toKey(),$this->tiles);
    }

    public function getTiles(): array {
        return $this->tiles;
    }

    public function render(): string {
        $res = '';

        foreach ($this->tiles as $tile) {
            $res .= $tile->render() . ' | ';
        }

        return $res;
    }

    public function getBoardSize(): int {
        return Square::BOARD_SIZE;
    }

    public function findTileByType(TileType $type): ?Tile {
        foreach ($this->tiles as $tile) {
            if ($tile->getType() === $type) {
                return $tile;
            }
        }

        return null;
    }

    public function findNearestByType(Square $from, TileType $type): ?Square {
        for( $d=1 ; $d<$this->getBoardSize() ; $d++){
            $candidate=$from->next($d);
            $tile=$this->getTileAt($candidate);
            if($tile !== null && $tile->getType() === $type){
                return $candidate;
            }
        }
        return null;
    }

    public function ownsWholeGroup(Player $player, ColorGroup $group): bool {
        foreach($this->tiles as $tile){
            if($tile instanceof Property ){
                if($tile->getColorGroup() === $group){
                    if($tile->getOwner() !== $player){
                        return false;
                    }
                }
            }
        }
        return true;
    }
}