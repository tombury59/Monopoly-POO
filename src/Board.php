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

    public function countOwnedByType(Player $player, TileType $type, bool $excludeMortgaged = false): int {
        $ownedType=0;
        foreach($this->tiles as $tile){
            if ($tile->getType() === $type && $tile->getOwner() === $player) {
                if($excludeMortgaged && $tile->isMortgaged()){
                    continue;
                }
                else{
                   $ownedType+=1;
                }
            }
        }
        return $ownedType;
    }

    public function minBuildLevelInGroup(ColorGroup $group): int {
        $minimum=10;
        foreach($this->tiles as $tile){
            if($tile instanceof Property && $tile->getColorGroup()===$group){
                if($tile->getBuildLevel()<$minimum){
                    $minimum=$tile->getBuildLevel();
                }
            }
        }
        return $minimum;
    }

    public function maxBuildLevelInGroup(ColorGroup $group): int {
        $maximum=0;
        foreach($this->tiles as $tile){
            if($tile instanceof Property && $tile->getColorGroup()===$group){
                if($tile->getBuildLevel()>$maximum){
                    $maximum=$tile->getBuildLevel();
                }
            }
        }
        return $maximum;
    }

    public function groupHasMortgage(ColorGroup $group): bool {
        foreach($this->tiles as $tile){
            if($tile instanceof Property && $tile->getColorGroup()===$group){
                if($tile->isMortgaged()){
                    return true;
                }
            }
        }
        return false;
    }
}