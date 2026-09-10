<?php

require_once __DIR__ . '/src/Contract/Renderable.php';
require_once __DIR__ . '/src/Enum/TileType.php';
require_once __DIR__ . '/src/Enum/ColorGroup.php';
require_once __DIR__ . '/src/Enum/CardEffectType.php';
require_once __DIR__ . '/src/Square.php';
require_once __DIR__ . '/src/Dice.php';
require_once __DIR__ . '/src/Exception/MonopolyException.php';
require_once __DIR__ . '/src/Exception/InsufficientFundsException.php';
require_once __DIR__ . '/src/Exception/TileNotOwnableException.php';
require_once __DIR__ . '/src/Exception/AlreadyOwnedException.php';
require_once __DIR__ . '/src/Exception/InvalidPlayerActionException.php';
require_once __DIR__ . '/src/Exception/InvalidPropertyLevelException.php';
require_once __DIR__ . '/src/Player.php';
require_once __DIR__ . '/src/Board.php';
require_once __DIR__ . '/src/Card.php';
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
require_once __DIR__ . '/src/Factory/TileFactory.php';
require_once __DIR__ . '/src/Game.php';

function ligne(string $titre = ''): void
{
    echo PHP_EOL . str_repeat('=', 60) . PHP_EOL;
    if ($titre !== '') {
        echo $titre . PHP_EOL;
        echo str_repeat('=', 60) . PHP_EOL;
    }
}

$game = new Game(['Alice', 'Bob']);
$game->start();

ligne('PLATEAU');
echo $game->getBoard()->render() . PHP_EOL;

// ---------------------------------------------------------------------------
// 1. Quelques tours de partie automatiques
// ---------------------------------------------------------------------------
ligne('PARTIE : 20 tours de démonstration');

for ($i = 0; $i < 2000; $i++) {
    $player = $game->getCurrentPlayer();

    try {
        if ($player->isInJail() && random_int(0, 1) === 1) {
            echo "{$player->getName()} paie la caution pour sortir de prison." . PHP_EOL;
            $game->payToLeaveJail();
        } else {
            $game->playTurn();
        }

        $tile   = $game->getBoard()->getTileAt($player->getPosition());
        $label  = $tile !== null ? " ({$tile->getName()})" : '';
        $status = $player->isInJail() ? ' [EN PRISON]' : '';

        echo "{$player->getName()} -> case {$player->getPosition()->getIndex()}{$label}, argent : {$player->getMoney()}{$status}" . PHP_EOL;

        if (!$player->isInJail()) {
            try {
                $game->buyCurrentTile($player);
                echo "  {$player->getName()} achète la case." . PHP_EOL;
            } catch (MonopolyException $e) {
                // case non achetable / déjà possédée / fonds insuffisants : on ignore en démo
            }
        }
    } catch (InsufficientFundsException $e) {
        echo "{$player->getName()} ne peut plus payer : {$e->getMessage()}" . PHP_EOL;
        break;
    }
}

// ---------------------------------------------------------------------------
// 2. Démonstration des maisons / hôtels et du loyer (point 5)
// ---------------------------------------------------------------------------
// ligne('CONSTRUCTION & LOYERS (point 5)');

// // On repart d'une partie neuve pour un scénario maîtrisé
// $demo   = new Game(['Alice', 'Bob']);
// $demo->start();
// $board  = $demo->getBoard();
// [$alice, $bob] = $demo->getPlayers();

// $belleville = $board->getTileAt(new Square(1)); // BROWN
// $lecourbe   = $board->getTileAt(new Square(3)); // BROWN

// // Alice n'a qu'une seule case du groupe : loyer de base
// $belleville->setOwner($alice);
// echo "Loyer Belleville (Alice possède 1 seule case BROWN) : {$belleville->getRent()}" . PHP_EOL;

// $bob->setPosition(new Square(1));
// $avant = $bob->getMoney();
// $belleville->landOn($bob, $demo);
// echo "  Bob s'arrête dessus et paie : " . ($avant - $bob->getMoney()) . PHP_EOL;

// // Alice complète le groupe BROWN : loyer doublé (monopole nu)
// $lecourbe->setOwner($alice);
// echo PHP_EOL . "Alice possède désormais TOUT le groupe BROWN (sans maison)." . PHP_EOL;
// $avant = $bob->getMoney();
// $belleville->landOn($bob, $demo);
// echo "  Bob paie (loyer doublé) : " . ($avant - $bob->getMoney()) . PHP_EOL;

// // Alice construit des maisons : le loyer suit la grille officielle
// echo PHP_EOL . "Alice construit sur Boulevard de Belleville :" . PHP_EOL;
// for ($niveau = 1; $niveau <= 5; $niveau++) {
//     try {
//         $demo->buildHouse($belleville);
//         $etiquette = $belleville->getBuildLevel() === 5 ? 'hôtel' : "{$belleville->getBuildLevel()} maison(s)";
//         echo "  niveau {$belleville->getBuildLevel()} ({$etiquette}) -> loyer {$belleville->getRent()}, argent Alice : {$alice->getMoney()}" . PHP_EOL;
//     } catch (MonopolyException $e) {
//         echo "  construction impossible : {$e->getMessage()}" . PHP_EOL;
//         break;
//     }
// }

// // Tentative de construction interdite (groupe incomplet)
// echo PHP_EOL . "Bob tente de construire sur une case isolée :" . PHP_EOL;
// $vaugirard = $board->getTileAt(new Square(6)); // LIGHT_BLUE
// $vaugirard->setOwner($bob);
// try {
//     $demo->buildHouse($vaugirard);
//     echo "  (aucune erreur — anormal)" . PHP_EOL;
// } catch (InvalidPlayerActionException $e) {
//     echo "  refusé : {$e->getMessage()}" . PHP_EOL;
// }

// ---------------------------------------------------------------------------
// 3. État final
// ---------------------------------------------------------------------------
ligne('ÉTAT FINAL (partie principale)');
foreach ($game->getPlayers() as $player) {
    $status = $player->isInJail() ? ' [EN PRISON]' : '';
    echo "{$player->getName()} — position {$player->getPosition()->getIndex()}, argent : {$player->getMoney()}{$status}" . PHP_EOL;
}
