<?php

require_once __DIR__ . '/../vendor/autoload.php';

/** @return Tile[] all tiles of a given type on a started game's board. */
function tilesByType(Game $game, TileType $type): array
{
    $out = [];
    foreach ($game->getBoard()->getTiles() as $t) {
        if ($t->getType() === $type) {
            $out[] = $t;
        }
    }
    return $out;
}

function firstTileByType(Game $game, TileType $type): Tile
{
    return tilesByType($game, $type)[0];
}
