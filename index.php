<?php

require_once __DIR__ . '/src/Contract/Renderable.php';
require_once __DIR__ . '/src/Contract/Mortgageable.php';
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
// Partie automatique jouée jusqu'à ce qu'il ne reste qu'un joueur
// ---------------------------------------------------------------------------
ligne('PARTIE : jusqu\'à élimination de tous sauf un');

$tour       = 0;
$TOUR_LIMITE = 5000; // garde-fou : une partie peut théoriquement ne jamais finir

while (!$game->isGameOver() && $tour < $TOUR_LIMITE) {
    $tour++;
    $player = $game->getCurrentPlayer();

    // Le joueur en prison paie la caution une fois sur deux (sinon il tente les dés).
    if ($player->isInJail() && random_int(0, 1) === 1) {
        echo "T{$tour} — {$player->getName()} paie la caution pour sortir de prison." . PHP_EOL;
        $game->payToLeaveJail();
    } else {
        $game->playTurn();
    }

    // Si le joueur a fait faillite pendant le tour, il n'est plus dans la partie.
    $encoreEnJeu = in_array($player, $game->getPlayers(), true);

    if ($encoreEnJeu) {
        $tile  = $game->getBoard()->getTileAt($player->getPosition());
        $label = $tile !== null ? " ({$tile->getName()})" : '';
        $etat  = $player->isInJail() ? ' [EN PRISON]' : '';
        echo "T{$tour} — {$player->getName()} -> case {$player->getPosition()->getIndex()}{$label}, argent : {$player->getMoney()}{$etat}" . PHP_EOL;

        // Achat automatique de la case si elle est libre.
        if (!$player->isInJail()) {
            try {
                $game->buyCurrentTile($player);
                echo "        achète la case." . PHP_EOL;
            } catch (MonopolyException $e) {
                // case non achetable / déjà possédée / fonds insuffisants : on ignore en démo
            }

            // Construction automatique : le joueur bâtit sur ses groupes complets
            // (fait grimper les loyers et permet à la partie de se conclure).
            foreach ($game->getBoard()->getTiles() as $tile) {
                if ($tile instanceof Property && $tile->getOwner() === $player) {
                    try {
                        $game->buildHouse($tile);
                    } catch (MonopolyException $e) {
                        // groupe incomplet, non uniforme, hypothéqué ou fonds insuffisants : on ignore
                    }
                }
            }
        }
    } else {
        echo "T{$tour} — {$player->getName()} a fait FAILLITE et quitte la partie. Joueurs restants : " . count($game->getPlayers()) . PHP_EOL;
    }
}

// ---------------------------------------------------------------------------
// Résultat final
// ---------------------------------------------------------------------------
ligne('RÉSULTAT');

if ($game->isGameOver()) {
    $gagnant = $game->getWinner();
    echo "Partie terminée en {$tour} tours." . PHP_EOL;
    echo "🏆 Vainqueur : {$gagnant->getName()} avec {$gagnant->getMoney()} d'argent." . PHP_EOL;
} else {
    echo "Limite de {$TOUR_LIMITE} tours atteinte sans vainqueur unique. Joueurs encore en lice :" . PHP_EOL;
    foreach ($game->getPlayers() as $player) {
        echo "  - {$player->getName()} : {$player->getMoney()}" . PHP_EOL;
    }
}
