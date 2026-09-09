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
}