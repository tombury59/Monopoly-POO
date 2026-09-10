<?php

require_once __DIR__ . '/src/Contract/Renderable.php';
require_once __DIR__ . '/src/Enum/TileType.php';
require_once __DIR__ . '/src/Enum/ColorGroup.php';
require_once __DIR__ . '/src/Square.php';
require_once __DIR__ . '/src/Dice.php';
require_once __DIR__ . '/src/Exception/MonopolyException.php';
require_once __DIR__ . '/src/Exception/InsufficientFundsException.php';
require_once __DIR__ . '/src/Exception/TileNotOwnableException.php';
require_once __DIR__ . '/src/Exception/AlreadyOwnedException.php';
require_once __DIR__ . '/src/Exception/InvalidPlayerActionException.php';
require_once __DIR__ . '/src/Player.php';
require_once __DIR__ . '/src/Tile/Tile.php';
require_once __DIR__ . '/src/Tile/Go.php';
require_once __DIR__ . '/src/Tile/Jail.php';
require_once __DIR__ . '/src/Tile/FreeParking.php';
require_once __DIR__ . '/src/Tile/GoToJail.php';
require_once __DIR__ . '/src/Tile/Chance.php';
require_once __DIR__ . '/src/Tile/CommunityChest.php';
require_once __DIR__ . '/src/Tile/Tax.php';
require_once __DIR__ . '/src/Tile/Station.php';
require_once __DIR__ . '/src/Tile/Company.php';
require_once __DIR__ . '/src/Tile/Property.php';
require_once __DIR__ . '/src/Board.php';
require_once __DIR__ . '/src/Factory/TileFactory.php';
require_once __DIR__ . '/src/Game.php';

$game = new Game(['Alice', 'Bob']);
$game->start();

echo $game->getBoard()->render() . PHP_EOL . PHP_EOL;

for ($i = 0; $i < 16; $i++) {
    $player = $game->getCurrentPlayer();

    try {
        $game->playTurn();
        echo "{$player->getName()} est maintenant en case {$player->getPosition()->getIndex()}, argent : {$player->getMoney()}" . PHP_EOL;

        $game->buyCurrentTile();
        echo "{$player->getName()} a acheté la case courante." . PHP_EOL;
    } catch (MonopolyException $e) {
        echo "Erreur pour {$player->getName()} : {$e->getMessage()}" . PHP_EOL;
    }
}

echo PHP_EOL . "État final des joueurs :" . PHP_EOL;
foreach ($game->getPlayers() as $player) {
    echo "{$player->getName()} — position {$player->getPosition()->getIndex()}, argent : {$player->getMoney()}" . PHP_EOL;
}