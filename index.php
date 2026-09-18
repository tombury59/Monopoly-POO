<?php

require_once __DIR__ . '/src/Enum/GameEventType.php';
require_once __DIR__ . '/src/Enum/TurnPhase.php';
require_once __DIR__ . '/src/Enum/PlayerAction.php';
require_once __DIR__ . '/src/GameEvent.php';
require_once __DIR__ . '/src/Contract/GameObserver.php';
require_once __DIR__ . '/src/Observer/ConsoleGameObserver.php';
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
$game->addObserver(new ConsoleGameObserver());
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

// ---------------------------------------------------------------------------
// DÉMO DU MODÈLE DE TOUR INTERACTIF (Bloc A.2)
//
// Ici on ne joue plus via playTurn() (moteur automatique), mais via la machine
// à phases : getCurrentPhase() dit où on en est, getAvailableActions() liste ce
// qui est permis, et on appelle roll() / payBail() / releaseWithCard() /
// buyCurrentTile() / buildHouse() / sellHouse() / mortgage() /
// declareBankruptcyInteractive() / endTurn() en conséquence.
//
// La politique de décision ci-dessous est volontairement simple (auto-pilotée,
// sans saisie clavier) pour que la démo tourne d'un bloc et illustre l'enchaînement
// des phases. Pour une vraie partie « au clavier », remplacer les choix
// automatiques par des readline() proposant getAvailableActions().
// ---------------------------------------------------------------------------

/**
 * Trouve une propriété du joueur revendable : construite ET au niveau maximum de
 * son groupe (contrainte even-build imposée par Game::sellHouse).
 */
function premiereProprieteConstruite(Game $game, Player $joueur): ?Property {
    $board = $game->getBoard();
    foreach ($board->getTiles() as $tile) {
        if ($tile instanceof Property && $tile->getOwner() === $joueur && $tile->getBuildLevel() > 0
            && $tile->getBuildLevel() === $board->maxBuildLevelInGroup($tile->getColorGroup())) {
            return $tile;
        }
    }
    return null;
}

/** Trouve une case hypothéquable du joueur : non hypothéquée et sans construction. */
function premiereHypothecable(Game $game, Player $joueur): ?Mortgageable {
    foreach ($game->getBoard()->getTiles() as $tile) {
        if ($tile instanceof Mortgageable && $tile->getOwner() === $joueur && !$tile->isMortgaged()
            && !($tile instanceof Property && $tile->getBuildLevel() > 0)) {
            return $tile;
        }
    }
    return null;
}

ligne('DÉMO INTERACTIVE : machine à phases (roll / actions / liquidation / endTurn)');

$demo        = new Game(['Alice', 'Bob']);
$demo->addObserver(new ConsoleGameObserver());
$demo->start();

$toursJoues  = 0;
$TOURS_DEMO  = 40; // on borne la démo pour garder une sortie lisible

while (!$demo->isGameOver() && $toursJoues < $TOURS_DEMO) {
    $phase = $demo->getCurrentPhase();

    // Contrat : en TURN_OVER on n'appelle QUE endTurn() (ne pas lire getCurrentPlayer avant).
    if ($phase === TurnPhase::TURN_OVER) {
        $demo->endTurn();
        $toursJoues++;
        continue;
    }

    $joueur  = $demo->getCurrentPlayer();
    $actions = $demo->getAvailableActions();

    switch ($phase) {
        case TurnPhase::AWAITING_ROLL:
            if ($joueur->isInJail()) {
                // Politique : utiliser une carte si on en a, sinon payer 1 fois sur 2, sinon tenter les dés.
                if (in_array(PlayerAction::USE_JAIL_CARD, $actions, true)) {
                    $demo->releaseWithCard();
                } elseif (in_array(PlayerAction::PAY_BAIL, $actions, true)
                          && $joueur->getMoney() >= Game::JAIL_BAIL
                          && random_int(0, 1) === 1) {
                    $demo->payBail();
                } else {
                    $demo->roll();
                }
            } else {
                $demo->roll();
            }
            break;

        case TurnPhase::AWAITING_ACTION:
            // Achat de la case si libre, puis construction sur les groupes complets, puis fin de tour.
            if (in_array(PlayerAction::BUY_TILE, $actions, true)) {
                try { $demo->buyCurrentTile($joueur); } catch (MonopolyException $e) { /* ignore en démo */ }
            }
            foreach ($demo->getBoard()->getTiles() as $tile) {
                if ($tile instanceof Property && $tile->getOwner() === $joueur) {
                    try { $demo->buildHouse($tile); } catch (MonopolyException $e) { /* ignore */ }
                }
            }
            $demo->endTurn();
            break;

        case TurnPhase::AWAITING_LIQUIDATION:
            // Réunir des fonds : revendre une maison, sinon hypothéquer, sinon faire faillite.
            // (sellHouse/mortgage règlent automatiquement la dette dès que possible → TURN_OVER.)
            if (in_array(PlayerAction::SELL_HOUSE, $actions, true)
                && ($prop = premiereProprieteConstruite($demo, $joueur)) !== null) {
                $demo->sellHouse($prop);
            } elseif (in_array(PlayerAction::MORTGAGE, $actions, true)
                      && ($hyp = premiereHypothecable($demo, $joueur)) !== null) {
                $demo->mortgage($hyp);
            } else {
                $demo->declareBankruptcyInteractive();
            }
            break;

        default:
            // ne devrait pas arriver
            break;
    }
}

ligne('RÉSULTAT DÉMO INTERACTIVE');
if ($demo->isGameOver()) {
    $gagnant = $demo->getWinner();
    echo "Démo terminée en {$toursJoues} tours." . PHP_EOL;
    echo "🏆 Vainqueur : {$gagnant->getName()} avec {$gagnant->getMoney()} d'argent." . PHP_EOL;
} else {
    echo "Démo arrêtée après {$toursJoues} tours (bornée pour la lisibilité). État courant :" . PHP_EOL;
    foreach ($demo->getPlayers() as $player) {
        $etat = $player->isInJail() ? ' [EN PRISON]' : '';
        echo "  - {$player->getName()} : {$player->getMoney()}{$etat}" . PHP_EOL;
    }
}
