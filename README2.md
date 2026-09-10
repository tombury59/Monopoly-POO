# TP2 (bonus) - Faire tourner un vrai Monopoly

## Prérequis

Ce TP suppose que le TP principal est terminé et fonctionnel :

- toutes les classes de `monopoly/src/` existent et compilent ;
- `index.php` peut jouer plusieurs tours sans erreur ;
- l'achat de case fonctionne (`Game::buyCurrentTile()`).

Vous n'avez pas le droit de casser l'architecture imposée dans le TP principal :

- les noms de classes déjà créées ne changent pas ;
- les signatures déjà imposées ne changent pas (vous pouvez **ajouter** des méthodes, pas retirer ni renommer celles imposées) ;
- vous continuez à respecter les design patterns déjà en place (Factory, Template Method, Strategy).

## Principe

Contrairement au TP principal, celui-ci est plus court et **moins directif sur le détail du code** : je vous donne les règles à respecter et les nouvelles classes/méthodes imposées, mais vous devez réfléchir vous-même à l'intégration dans l'existant.

## Vue d'ensemble des ajouts

```
monopoly/
└── src/
    ├── Game.php                     (modifié)
    ├── Player.php                   (modifié)
    ├── Card.php                     (nouveau)
    ├── Enum/
    │   └── CardEffectType.php       (nouveau)
    └── Tile/
        ├── Chance.php                (modifié)
        ├── CommunityChest.php        (modifié)
        ├── Property.php              (modifié)
        ├── Station.php               (modifié)
        └── Company.php               (modifié)
```

## Ordre de travail conseillé

1. Passage par la case Départ
2. Doubles aux dés (rejouer / 3e double → prison)
3. Prison réelle (sortie par paiement, double, ou carte)
4. Cartes Chance / Caisse de Communauté avec effets variés
5. Maisons et hôtels
6. Hypothèques
7. Faillite et fin de partie

Chaque partie est indépendante des suivantes — vous pouvez vous arrêter à n'importe quelle étape et avoir un projet cohérent.

---

## 1. Passage par la case Départ

### Règle

Un joueur qui **passe** par la case Départ (index 0) pendant son déplacement, sans forcément s'y arrêter, touche 200 dès qu'il la franchit ou s'y arrête.

### Contrainte technique

`Square::next(int $steps): Square` retourne uniquement la **destination**. Il ne vous dit pas si le trajet a traversé la case 0.

### Ce qu'il faut faire

Réfléchissez à comment détecter ce passage à partir de deux informations que vous avez déjà : la position de départ du joueur, et le nombre de cases avancées. Indice : une comparaison arithmétique simple entre l'ancienne position + le nombre de pas, et la taille du plateau (40), suffit — pas besoin de nouvelle classe.

Cette logique doit vivre dans `Game::playTurn()`, pas dans `Square` ni dans `Go` (une case ne peut pas savoir si on est "passé devant" elle, seule `Game` connaît le trajet complet).

### Attendu

- ✅ un joueur qui s'arrête pile sur la case 0 touche 200 (déjà fait dans le TP principal, via `Go::applyEffect()`) ;
- ✅ un joueur qui passe devant sans s'arrêter touche aussi 200, une seule fois par tour.

---

## 2. Doubles aux dés

### Règle

Si `Dice::rollTwo()` retourne deux valeurs identiques (un double), le joueur rejoue un tour supplémentaire immédiatement, sans changer de joueur courant. S'il fait 3 doubles d'affilée dans le même tour, il est envoyé directement en prison au lieu de jouer son 3e déplacement.

### Ce qu'il faut faire

- Un compteur de doubles consécutifs, propre au tour en cours (pas une propriété permanente de `Player` — réfléchissez à où le stocker : variable locale dans `playTurn()`, ou compteur temporaire sur `Game` ?).
- `Game::nextPlayer()` ne doit être appelée que lorsque le tour du joueur est réellement terminé (pas de double, ou 3e double atteint).

### Attendu

- ✅ un double → le joueur rejoue (même `currentPlayerIndex`) ;
- ✅ 3 doubles d'affilée → prison directe, tour terminé, joueur suivant.

---

## 3. Prison réelle

### Règle

Un joueur avec `isInJail() === true` ne peut pas jouer normalement. À son tour, il doit choisir (ou vous décidez d'une règle automatique simple) entre :

- payer une caution (exemple : 50) pour sortir immédiatement et jouer normalement ce tour ;
- lancer les dés : s'il fait un double, il sort de prison gratuitement et avance de ce double ; sinon, il reste en prison pour ce tour (tour "perdu") ;
- après 3 tours ratés en prison, la sortie devient obligatoire (paiement forcé de la caution).

### Ce qu'il faut faire

- Une propriété de comptage des tours passés en prison (sur `Player`, en plus de `inJail`).
- Une méthode (nom libre, par exemple `Game::playJailedTurn()` ou une branche au début de `playTurn()`) qui gère ce cas spécifique avant la logique normale de déplacement.

### Attendu

- ✅ un joueur en prison ne se déplace pas normalement tant qu'il n'en est pas sorti ;
- ✅ sortie par double, par paiement, ou forcée après 3 tours ;
- ✅ `inJail` repassé à `false` à la sortie.

---

## 4. Cartes Chance / Caisse de Communauté

### Règle

Chaque case `Chance`/`CommunityChest` doit tirer une carte aléatoire parmi un jeu de cartes, avec des effets variés (gagner/perdre de l'argent, avancer à une case précise, aller en prison, etc.), au lieu de l'effet fixe codé dans le TP principal.

### Classes imposées

Fichier :

```
src/Card.php
```

Propriétés imposées

```php
private string $description;
private CardEffectType $effectType;
private int $value;
```

Méthodes imposées

```php
public function __construct(string $description, CardEffectType $effectType, int $value = 0)
public function getDescription(): string
public function apply(Player $player, Game $game): void
```

Fichier :

```
src/Enum/CardEffectType.php
```

Valeurs à couvrir dès ce point 4 (libre à vous d'en ajouter d'autres qui ne dépendent de rien d'autre) :

```php
case GAIN_MONEY;          // value = montant gagné
case LOSE_MONEY;          // value = montant perdu
case MOVE_TO;              // value = index de la case cible
case MOVE_STEPS;           // value = nombre de cases à avancer (peut réutiliser resolveMovement())
case GO_TO_JAIL;
case EXIT_JAIL;            // sortie de prison immédiate, sans paiement ni tour supplémentaire
case PAY_ALL;              // value = montant à verser à chaque autre joueur
case RECEIVE_ALL;          // value = montant reçu de chaque autre joueur
```

`Card::apply()` doit utiliser un `match` sur `$this->effectType` pour appliquer le bon effet — même principe de Template Method/Strategy que dans `Tile`.

> **Deux cas reportés après le point 5.** `REPAIR_BUILDINGS` (payer un montant par maison/hôtel possédé) dépend d'une notion de niveau de construction sur `Property` qui n'existe pas encore à ce stade. `NEAREST_UTILITY_OR_RAILROAD` (avancer jusqu'à la gare ou la compagnie la plus proche) demande une recherche "case la plus proche dans le sens de la marche", plus complexe que `Board::findTileByType()` qui retourne simplement la première case trouvée d'un type donné, sans notion de distance. Vous pouvez déjà les ajouter à l'enum dès maintenant si vous le souhaitez (un enum PHP peut évoluer sans rien casser), mais laissez un `// TODO` dans `Card::apply()` pour ces deux cas plutôt que de les implémenter tout de suite — voir la note à la fin du point 5.

### Ce qui est imposé dans `Chance` et `CommunityChest`

Contrairement au reste du TP2, cette partie redevient aussi directive que le TP principal — mêmes règles : respectez exactement le nom de la propriété et la signature.

Propriété imposée, dans chacune des deux classes :

```php
private array $cards = [];
```

Méthode imposée, dans chacune des deux classes :

```php
private function buildDeck(): array
```

`buildDeck()` doit construire et retourner le tableau des `Card` propres à cette case (les cartes Chance sont différentes des cartes Caisse de Communauté — chaque classe a son propre contenu). Elle est appelée depuis le constructeur, qui assigne son résultat à `$this->cards` :

```php
public function __construct(string $name, Square $position)
{
    parent::__construct($name, $position);
    $this->type = TileType::CHANCE; // ou COMMUNITY_CHEST selon la classe
    $this->cards = $this->buildDeck();
}
```

`applyEffect()` (déjà imposée par le TP principal) doit tirer une carte au hasard dans `$this->cards` (`array_rand()` ou équivalent) et appeler `Card::apply($player, $game)` dessus — aucune autre logique ne doit s'y trouver, tout le détail de l'effet appartient à `Card`.

### Attendu

- ✅ au moins 4 cartes différentes par type de case, avec des effets variés ;
- ✅ le tirage est aléatoire à chaque atterrissage ;
- ✅ `$cards` et `buildDeck()` respectent exactement le nom et la signature ci-dessus.

---

## 5. Maisons et hôtels

### Règle

Un joueur propriétaire de **toutes** les propriétés d'un même `ColorGroup` peut construire des maisons (jusqu'à 4), puis un hôtel, sur chacune. Le loyer (`Property::getRent()`) augmente selon le nombre de maisons/hôtel.

### Ce qu'il faut faire

- Une propriété sur `Property` pour compter le niveau de construction (0 à 5, où 5 = hôtel), avec un getter/setter.
- Une grille de loyers par palier (vous pouvez la stocker comme second tableau dans le constructeur de `Property`, ou calculer le loyer avec une formule simple — à vous de choisir, tant que `getRent()` retourne un montant cohérent avec le niveau de construction actuel).
- Une méthode pour vérifier qu'un joueur possède bien tout le groupe de couleur avant d'autoriser la construction (elle peut vivre dans `Game` ou dans `Board`, à vous de juger le meilleur endroit).

### Attendu

- ✅ impossible de construire sans posséder tout le groupe ;
- ✅ le loyer appliqué dans `Property::applyEffect()` reflète le niveau de construction.

### À reprendre maintenant que ce point est fait

Retournez dans `Card::apply()` (point 4) et complétez le cas `REPAIR_BUILDINGS` : il doit parcourir les propriétés du joueur (via `Game::getPlayers()`/une méthode dédiée si vous en créez une, ou en itérant sur `Board::getTiles()` en filtrant celles dont `getOwner() === $player`), et débiter un montant par maison et un montant (généralement plus élevé) par hôtel, selon le niveau de construction que vous venez d'ajouter à `Property`.

`NEAREST_UTILITY_OR_RAILROAD` reste optionnel — ce n'est pas dans les attendus stricts du TP2, à traiter seulement si vous voulez aller plus loin.

---

## 6. Loyers variables pour Station et Company

### Constat

Le TP principal n'imposait qu'un `price` sur `Station`/`Company`, sans notion de loyer séparée — `applyEffect()` fait donc actuellement payer le **prix d'achat** en guise de loyer, une simplification assumée du socle de départ, mais différente des vraies règles du Monopoly : le loyer d'une gare ou d'une compagnie dépend du **nombre de cases du même type possédées par le même propriétaire**, pas d'un montant fixe.

### Règle réelle

- **Gares** : le loyer double à chaque gare supplémentaire possédée par le même propriétaire (25 avec 1 gare, 50 avec 2, 100 avec 3, 200 avec les 4).
- **Compagnies** : le loyer dépend d'un lancer de dés — 4× le total des dés si le propriétaire possède une seule compagnie, 10× s'il possède les deux.

### Ce qu'il faut faire

- Une méthode sur `Board` pour compter, pour un joueur donné et un `TileType` donné, le nombre de cases de ce type qu'il possède — par exemple :

```php
public function countOwnedByType(Player $player, TileType $type): int
```

Elle doit parcourir `$this->tiles`, ne considérer que celles dont le type correspond, et dont `getOwner() === $player` (attention : seules `Property`, `Station`, `Company` ont un `getOwner()` — un `instanceof` ou une interface commune reste nécessaire, comme déjà fait dans `Game::buyCurrentTile()`).

- Dans `Station::applyEffect()`, remplacer le loyer fixe par un calcul basé sur `$game->getBoard()->countOwnedByType($this->getOwner(), TileType::STATION)`.
- Dans `Company::applyEffect()`, le calcul a besoin du dernier lancer de dés — ce qui pose une vraie question de conception : `Company` n'a aucun moyen de connaître le résultat du lancer qui a mené le joueur jusqu'à elle, puisque `applyEffect()` ne reçoit que `$player` et `$game`. Réfléchissez à comment faire remonter cette information (par exemple, `Game` pourrait exposer une méthode `getLastDiceTotal(): int`, mise à jour à chaque lancer dans `playTurn()`), plutôt que d'improviser un nouveau lancer de dés dans `Company` elle-même — ce qui tricherait par rapport à la vraie règle.

### Attendu

- ✅ le loyer d'une gare change selon le nombre de gares possédées par le même propriétaire ;
- ✅ le loyer d'une compagnie se base sur le dernier lancer de dés réel du joueur, pas un nouveau lancer improvisé ;
- ✅ aucune donnée dupliquée entre `Board`/`Game`/`Station`/`Company` — chaque classe consulte les autres plutôt que de recalculer ce qu'elle ne devrait pas connaître.

---

## 7. Hypothèques

### Règle

Un propriétaire peut hypothéquer une case qu'il possède (`Property`, `Station`, `Company`) pour récupérer de l'argent immédiatement (exemple : la moitié du prix). Une case hypothéquée ne rapporte plus de loyer tant qu'elle n'est pas "dé-hypothéquée" (remboursement avec intérêt).

### Ce qu'il faut faire

- Une propriété `mortgaged` (booléen) sur `Property`, `Station`, `Company`, avec getter/setter.
- `applyEffect()` de ces trois classes doit vérifier ce statut avant d'appliquer un loyer.

### Attendu

- ✅ une case hypothéquée ne fait payer aucun loyer ;
- ✅ possibilité de rembourser pour la réactiver.

---

## 8. Faillite et fin de partie

### Règle

Si un joueur ne peut pas payer une dette (`InsufficientFundsException` levée quelque part dans le flot), il est déclaré en faillite : ses biens repassent sans propriétaire (ou au créancier, à vous de choisir), et il est retiré de la partie. La partie se termine quand il ne reste qu'un seul joueur.

### Ce qu'il faut faire

- Attraper `InsufficientFundsException` au bon endroit (probablement dans `Game::playTurn()` ou `Game::buyCurrentTile()`), plutôt que de la laisser remonter jusqu'à `index.php` sans traitement.
- Une méthode pour retirer un joueur de `$this->players`.
- Une méthode `isGameOver(): bool` ou équivalent sur `Game`, et une méthode pour connaître le vainqueur.

### Attendu

- ✅ un joueur en faillite est retiré proprement (pas juste ignoré) ;
- ✅ la partie sait se terminer et désigner un gagnant.

### À reprendre maintenant que ce point est fait

Retournez dans `Card::payOrReceiveAll()` (point 4) : un `// TODO` y a été laissé pour le cas où un joueur ne peut pas payer sa part lors d'un `PAY_ALL`/`RECEIVE_ALL`. Deux sous-cas à distinguer :

- le joueur qui a pioché la carte (`PAY_ALL`) n'a pas assez pour payer tout le monde → il fait faillite, avec la même mécanique que n'importe quelle autre dette impayée ;
- un des **autres** joueurs (`RECEIVE_ALL`, ou un receveur dans `PAY_ALL`) n'a pas assez → c'est lui qui fait faillite individuellement, sans empêcher le transfert de continuer pour les autres joueurs de la boucle.

Le plus propre est d'attraper `InsufficientFundsException` **à l'intérieur** de la boucle `foreach` de `payOrReceiveAll()`, joueur par joueur, plutôt que de laisser une seule exception interrompre tout le transfert — puis de déclencher la faillite du joueur concerné via la méthode que vous venez d'écrire pour ce point 8.

---

## 9. Notifications d'événements (Observer)

### Problème à résoudre

Aujourd'hui, `Game::playTurn()` (et bientôt `buyCurrentTile()`, la prison, les cartes...) modifie l'état du jeu silencieusement : rien ne permet à un appelant extérieur (typiquement un futur front) de savoir *ce qui s'est passé* pendant le tour — un passage par la case Départ, un loyer payé, une carte tirée, une faillite... Comparer l'état avant/après ne dit pas *pourquoi* les choses ont changé.

### Ce qui est imposé

Le pattern à mettre en place est un **Observer** : `Game` (le *sujet*) notifie une liste d'observateurs à chaque événement notable, sans savoir ce qu'ils en font (affichage console, futur front, logs, tests...).

Fichier :

```
src/Contract/GameObserver.php
```

Méthode imposée

```php
public function onEvent(GameEvent $event): void;
```

Fichier :

```
src/GameEvent.php
```

Propriétés et méthode imposées

```php
private string $type;
private string $message;
private array $context;

public function __construct(string $type, string $message, array $context = [])
public function getType(): string
public function getMessage(): string
public function getContext(): array
```

`$type` sert à catégoriser l'événement sans que l'observateur ait à parser le message (exemples de valeurs libres : `"passed_go"`, `"rent_paid"`, `"card_drawn"`, `"bankruptcy"`). `$context` porte les données structurées utiles à un affichage riche (montant, nom de joueur, nom de case...), sans obliger à tout reconstruire depuis le texte.

### Liste des événements à couvrir

Établissez cette liste **maintenant**, avant même d'écrire `notify()` — elle vous sert de checklist pendant que vous codez chaque règle du TP, pour ne pas avoir à ratisser tout le code plus tard en cherchant ce qui mériterait une notification. Chaque type ci-dessous est une suggestion de valeur pour `GameEvent::$type` ; le contenu de `$context` est indicatif, à ajuster selon vos besoins réels.

**Déplacement et plateau**
- `dice_rolled` — les dés ont été lancés (`context: valeurs des deux dés, total`)
- `player_moved` — un joueur a changé de position (`context: ancienne position, nouvelle position`)
- `passed_go` — le joueur passe devant ou s'arrête sur la case Départ (`context: montant reçu`)
- `landed_on_tile` — le joueur atterrit sur une case (`context: nom et type de la case`)

**Argent**
- `rent_paid` — un loyer a été payé (`context: montant, joueur payeur, propriétaire`)
- `tax_paid` — une taxe a été payée (`context: montant`)
- `money_gained` — gain d'argent générique (carte, bonus...) (`context: montant, raison`)
- `money_lost` — perte d'argent générique (`context: montant, raison`)

**Propriétés**
- `tile_purchased` — un joueur achète une case (`context: nom de la case, prix, acheteur`)
- `house_built` — une maison est construite (`context: case, nouveau niveau`)
- `hotel_built` — un hôtel est construit (`context: case`)
- `tile_mortgaged` — une case est hypothéquée (`context: case, montant reçu`)
- `tile_unmortgaged` — une case est dé-hypothéquée (`context: case, montant remboursé`)

**Dés et tours spéciaux**
- `double_rolled` — un double a été fait, le joueur rejoue (`context: valeur du double`)
- `three_doubles` — 3 doubles d'affilée, envoi direct en prison (`context: aucun ou joueur concerné`)
- `turn_ended` — le tour du joueur courant se termine, passage au suivant (`context: joueur suivant`)

**Prison**
- `sent_to_jail` — un joueur est envoyé en prison (`context: raison — case GoToJail, 3 doubles...`)
- `jail_turn_skipped` — le joueur reste en prison ce tour (`context: nombre de tours déjà passés`)
- `jail_paid` — le joueur paie la caution pour sortir (`context: montant`)
- `jail_escaped_by_double` — le joueur sort de prison grâce à un double (`context: aucun`)
- `jail_forced_release` — sortie forcée après 3 tours (`context: montant payé`)

**Cartes**
- `card_drawn` — une carte Chance/Caisse de Communauté est tirée (`context: description de la carte, type de pioche`)

**Fin de partie**
- `player_bankrupt` — un joueur est en faillite et retiré de la partie (`context: joueur, créancier éventuel`)
- `game_over` — la partie se termine (`context: joueur vainqueur`)

Vous n'êtes pas obligé de tout implémenter d'un coup — mais en ayant cette liste sous les yeux dès maintenant, vous saurez, à chaque règle que vous codez dans les sections 1 à 7, où il faudra brancher un `notify()` plus tard, et vous pourrez même laisser un `// TODO: notify <type>` en attendant d'avoir écrit l'Observer.

### Modifications sur Game

```php
private array $observers = [];

public function addObserver(GameObserver $observer): void
public function removeObserver(GameObserver $observer): void
private function notify(GameEvent $event): void
```

`notify()` doit simplement parcourir `$this->observers` et appeler `onEvent()` sur chacun. C'est la **seule** méthode de `Game` qui doit connaître ce détail — le reste du code de `Game` (dans `playTurn()`, `buyCurrentTile()`, etc.) se contente d'appeler `$this->notify(new GameEvent(...))` aux endroits pertinents, sans jamais boucler sur `$this->observers` lui-même.

### Un exemple d'observateur simple pour commencer

Avant de brancher un vrai front, un observateur console suffit à valider que le mécanisme fonctionne :

```
src/Observer/ConsoleGameObserver.php
```

```php
class ConsoleGameObserver implements GameObserver
{
    public function onEvent(GameEvent $event): void
    {
        echo "[{$event->getType()}] {$event->getMessage()}" . PHP_EOL;
    }
}
```

Dans `index.php` :

```php
$game->addObserver(new ConsoleGameObserver());
```

### Où placer les `notify()` dans le code existant

Reprenez chacune des règles déjà codées (passage par Départ, loyer payé, achat, carte tirée, prison, faillite...) et ajoutez un appel à `$this->notify(...)` juste après l'action réelle — jamais à la place. L'Observer ne doit **jamais** porter de logique métier lui-même (pas de calcul d'argent, pas de décision de déplacement) : il ne fait qu'informer, après coup, que quelque chose s'est produit.

### Attendu

- ✅ `Game` ne connaît pas le détail de ce que font ses observateurs (aucun `if` sur leur type dans `notify()`) ;
- ✅ au moins les événements suivants sont notifiés : déplacement, passage par Départ, loyer payé, achat de case, faillite ;
- ✅ un `ConsoleGameObserver` fonctionnel, prêt à être remplacé ou complété plus tard par un vrai observateur front sans toucher à `Game`.

### Pourquoi ce pattern seulement maintenant

Contrairement à Factory/Template Method/Strategy (nécessaires dès le socle), l'Observer n'a de sens qu'une fois qu'il y a plusieurs règles métier réellement en place à notifier — le mettre en place trop tôt aurait ajouté de la complexité sans bénéfice visible. C'est aussi la porte d'entrée naturelle vers un vrai projet : le jour où vous branchez un front (web, CLI interactif, API), il vous suffira d'écrire un nouvel observateur, sans toucher au moteur de jeu.

---

## Ce qui reste interdit même en bonus

- casser la Factory (toujours interdiction de `new Property(...)` en dehors de `TileFactory`) ;
- réintroduire un gros `if/else`/`match` sur le type de case dans `Game` pour gérer les loyers ou les effets (ça doit continuer à passer par le polymorphisme de `Tile`/`applyEffect()`) ;
- dupliquer la logique de vérification des fonds ailleurs qu'à travers `Player::removeMoney()`.

## Mise à jour du README

Ajoutez ces lignes à votre `readme.md` existant, à cocher selon ce que vous avez implémenté :

```
Bonus TP2
✅ / ❌ Passage par la case Départ (200 en passant)
✅ / ❌ Doubles aux dés (rejouer / 3e double → prison)
✅ / ❌ Prison réelle (caution / double / forcé après 3 tours)
✅ / ❌ Cartes Chance avec effets variés
✅ / ❌ Cartes Caisse de Communauté avec effets variés
✅ / ❌ Maisons / hôtels et paliers de loyer
✅ / ❌ Hypothèques
✅ / ❌ Faillite
✅ / ❌ Fin de partie et désignation d'un vainqueur
✅ / ❌ Notifications d'événements (Observer) + ConsoleGameObserver
```