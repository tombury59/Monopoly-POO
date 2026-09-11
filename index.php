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
// 1. Quelques tours de partie automatiques
// ---------------------------------------------------------------------------
ligne('PARTIE : 20 tours de démonstration');

for ($i = 0; $i < 200; $i++) {
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

// // ---------------------------------------------------------------------------
// // 2. Démonstration des maisons / hôtels et du loyer (point 5)
// // ---------------------------------------------------------------------------
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

// // ---------------------------------------------------------------------------
// // 3. Carte « Sortie de prison gratuite » conservable (points 3-4)
// // ---------------------------------------------------------------------------
// ligne('CARTE SORTIE DE PRISON (conservable)');

// $jeu   = new Game(['Alice', 'Bob']);
// $jeu->start();
// $alice = $jeu->getCurrentPlayer();

// $carteEtat = fn(Player $p): string =>
//     ($p->isInJail() ? 'EN PRISON' : 'libre') .
//     ', carte : ' . ($p->hasGetOutOfJailCard() ? 'oui' : 'non') .
//     ', position ' . $p->getPosition()->getIndex();

// // Alice pioche la carte alors qu'elle est libre -> elle la CONSERVE
// $carte = new Card('Sortie de prison gratuite', CardEffectType::EXIT_JAIL);
// $carte->apply($alice, $jeu);
// echo "Alice pioche la carte : {$carteEtat($alice)}" . PHP_EOL;
// echo "  -> conservée, pas de sortie immédiate." . PHP_EOL;

// // Plus tard, Alice tombe sur « Allez en prison » : tour terminé, elle garde la carte
// $jeu->getBoard()->findTileByType(TileType::GO_TO_JAIL)->landOn($alice, $jeu);
// echo "Alice va en prison : {$carteEtat($alice)}" . PHP_EOL;

// // À un tour suivant, Alice choisit de poser sa carte
// $jeu->useJailCard();
// echo "Alice utilise sa carte : {$carteEtat($alice)}" . PHP_EOL;
// echo "  -> sortie gratuite, carte consommée, tour normal joué." . PHP_EOL;

// // ---------------------------------------------------------------------------
// // 4. Loyers variables : gares et compagnies (point 6)
// // ---------------------------------------------------------------------------
// ligne('LOYERS GARES & COMPAGNIES (point 6)');

// $jeu6  = new Game(['Alice', 'Bob']);
// $jeu6->start();
// $plateau = $jeu6->getBoard();
// [$proprio, $visiteur] = $jeu6->getPlayers();

// // -- Gares : 25 / 50 / 100 / 200 selon le nombre possédé --
// $gares = [5, 15, 25, 35]; // Montparnasse, Lyon, Nord, Saint-Lazare
// $gare  = $plateau->getTileAt(new Square($gares[0]));
// echo "Gares (Alice propriétaire, Bob s'arrête sur Gare Montparnasse) :" . PHP_EOL;
// foreach ($gares as $rang => $index) {
//     $plateau->getTileAt(new Square($index))->setOwner($proprio);
//     $avant = $visiteur->getMoney();
//     $gare->landOn($visiteur, $jeu6);
//     echo "  " . ($rang + 1) . " gare(s) possédée(s) -> loyer " . ($avant - $visiteur->getMoney()) . PHP_EOL;
// }

// // -- Compagnies : x4 (une seule) puis x10 (les deux), sur le dernier lancer de dés --
// echo PHP_EOL . "Compagnies (loyer = multiplicateur x dernier lancer de dés) :" . PHP_EOL;
// $jeu6->playTurn();                       // fait un vrai lancer -> alimente getLastDiceTotal()
// $des = $jeu6->getLastDiceTotal();
// $cie = $plateau->getTileAt(new Square(12)); // Compagnie d'Électricité

// $plateau->getTileAt(new Square(12))->setOwner($proprio);
// $avant = $visiteur->getMoney();
// $cie->landOn($visiteur, $jeu6);
// echo "  1 compagnie, dés = {$des} -> loyer " . ($avant - $visiteur->getMoney()) . " (x4)" . PHP_EOL;

// $plateau->getTileAt(new Square(28))->setOwner($proprio); // Compagnie des Eaux
// $avant = $visiteur->getMoney();
// $cie->landOn($visiteur, $jeu6);
// echo "  2 compagnies, dés = {$des} -> loyer " . ($avant - $visiteur->getMoney()) . " (x10)" . PHP_EOL;

// ---------------------------------------------------------------------------
// 5. État final
// ---------------------------------------------------------------------------
ligne('ÉTAT FINAL (partie principale)');
foreach ($game->getPlayers() as $player) {
    $status = $player->isInJail() ? ' [EN PRISON]' : '';
    echo "{$player->getName()} — position {$player->getPosition()->getIndex()}, argent : {$player->getMoney()}{$status}" . PHP_EOL;
}
