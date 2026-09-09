# TP Guidé - POO avancée en PHP avec Monopoly

## Principe du TP

Dans ce TP, tout le monde doit produire la même architecture de base.

Le but n'est pas de "faire à sa manière", mais de :

- respecter une structure de projet imposée ;
- utiliser les mêmes noms de classes ;
- utiliser les mêmes noms de méthodes ;
- appliquer les mêmes relations d'héritage ;
- mettre en place plusieurs design patterns de manière contrôlée ;
- avancer étape par étape sans tout coder dès le début.

Le sujet est donc volontairement directif.

## Objectif pédagogique

Vous devez construire un mini moteur métier de Monopoly en PHP pour travailler :

- l'encapsulation ;
- l'héritage ;
- les classes abstraites ;
- les interfaces ;
- le polymorphisme ;
- la composition ;
- les exceptions métier ;
- plusieurs design patterns simples et utiles.

Ce TP ne demande pas :

- d'interface graphique ;
- d'intelligence artificielle (joueurs bots) ;
- de multijoueur réseau.

## Règle importante

Vous ne devez pas improviser l'architecture.

Vous devez respecter exactement :

- les dossiers ;
- les fichiers ;
- les classes ;
- les signatures de méthodes ;
- les relations entre classes.

Si une méthode est demandée, elle doit exister avec le nom indiqué.

Si une classe est demandée, elle doit être créée dans le fichier indiqué.

## Arborescence imposée

Votre projet doit respecter exactement cette structure à la fin :

```
monopoly/
├── readme.md
├── index.php
└── src/
    ├── Board.php
    ├── Game.php
    ├── Player.php
    ├── Dice.php
    ├── Square.php
    ├── Factory/
    │   └── TileFactory.php
    ├── Contract/
    │   └── Renderable.php
    ├── Enum/
    │   ├── TileType.php
    │   └── ColorGroup.php
    ├── Exception/
    │   ├── MonopolyException.php
    │   ├── InsufficientFundsException.php
    │   ├── TileNotOwnableException.php
    │   ├── AlreadyOwnedException.php
    │   └── InvalidPlayerActionException.php
    └── Tile/
        ├── Tile.php
        ├── Property.php
        ├── Station.php
        ├── Company.php
        ├── Tax.php
        ├── Chance.php
        ├── CommunityChest.php
        ├── Go.php
        ├── Jail.php
        ├── FreeParking.php
        └── GoToJail.php
```

## Classes imposées

Vous devez créer exactement les classes suivantes :

- Board
- Game
- Player
- Dice
- Square
- TileFactory
- Tile
- Property
- Station
- Company
- Tax
- Chance
- CommunityChest
- Go
- Jail
- FreeParking
- GoToJail
- MonopolyException
- InsufficientFundsException
- TileNotOwnableException
- AlreadyOwnedException
- InvalidPlayerActionException

Vous devez également créer :

- l'interface `Renderable`
- l'enum `TileType`
- l'enum `ColorGroup`

## Relations d'héritage imposées

Vous devez respecter exactement ce schéma :

### Héritage métier

- `Tile` est une classe abstraite
- `Property` hérite de `Tile`
- `Station` hérite de `Tile`
- `Company` hérite de `Tile`
- `Tax` hérite de `Tile`
- `Chance` hérite de `Tile`
- `CommunityChest` hérite de `Tile`
- `Go` hérite de `Tile`
- `Jail` hérite de `Tile`
- `FreeParking` hérite de `Tile`
- `GoToJail` hérite de `Tile`

### Héritage des exceptions

- `MonopolyException` hérite de `Exception`
- `InsufficientFundsException` hérite de `MonopolyException`
- `TileNotOwnableException` hérite de `MonopolyException`
- `AlreadyOwnedException` hérite de `MonopolyException`
- `InvalidPlayerActionException` hérite de `MonopolyException`

### Contrat commun

- `Tile` implémente `Renderable`
- `Board` implémente `Renderable`

## Design patterns imposés

Vous devez mettre en place les patterns suivants.

### 1. Factory

Classe concernée : `TileFactory`

Rôle :

- centraliser la création des cases (tiles) ;
- éviter d'instancier les cases directement dans `Game`.

### 2. Strategy via polymorphisme

Classes concernées : `Tile` et toutes les classes filles de `Tile`

Rôle :

- chaque type de case possède son propre comportement quand un joueur s'y arrête ;
- on évite un gros bloc de `if/else`/`match` sur le type de case dans `Game`.

### 3. Template Method

Classe concernée : `Tile`

Rôle :

- la classe abstraite porte la structure commune de traitement d'un atterrissage (`landOn`) ;
- les sous-classes ne redéfinissent que l'effet spécifique (`applyEffect`).

### 4. Value Object

Classe concernée : `Square`

Rôle :

- représenter une case du plateau (un index de 0 à 39) ;
- éviter de manipuler des entiers bruts partout ;
- gérer proprement le tour du plateau (bouclage après la case 39).

## Ordre de travail obligatoire

Vous devez avancer dans cet ordre :

1. `Square`
2. `TileType` et `ColorGroup`
3. `Renderable`
4. `Tile`
5. `Property`, `Station`, `Company`, `Tax`, `Chance`, `CommunityChest`, `Go`, `Jail`, `FreeParking`, `GoToJail`
6. `Dice`
7. `Board`
8. `Player`
9. `MonopolyException` et les exceptions filles
10. `TileFactory`
11. `Game`
12. `index.php`

Vous ne devez pas commencer `Game` avant d'avoir terminé `Board`, `Player` et les cases.

## Ce qu'il ne faut pas faire au début

Au début du TP, vous ne devez pas coder :

- les hypothèques (mise en gage d'une propriété) ;
- les maisons / hôtels et leurs paliers de loyer ;
- les échanges entre joueurs ;
- les enchères ;
- les cartes Chance / Caisse de Communauté détaillées (juste l'effet générique de la case) ;
- la faillite complète avec liquidation des biens.

On construit d'abord un socle propre.

## Phase 1 - Mise en place du socle

À cette phase, vous ne travaillez que sur :

- `Square`
- `TileType`
- `ColorGroup`
- `Renderable`

### 1. Classe Square

Fichier :

```
src/Square.php
```

Propriété imposée

```php
private int $index;
```

Méthodes imposées

```php
public function __construct(int $index)
public function getIndex(): int
public function equals(Square $other): bool
// Retourne l'index sous forme de chaîne (exemple : Square(7) -> "7")
public function toKey(): string
// Fait la transformation inverse (exemple : "7" -> Square(7))
public static function fromKey(string $key): Square
// Retourne une nouvelle Square après avoir avancé de $steps cases, en bouclant sur le plateau (0 à 39)
public function next(int $steps): Square
```

Contraintes

- `index` doit être compris entre 0 et 39
- `next()` doit boucler : depuis la case 38 avec 3 pas, on arrive à la case 1 (`(38 + 3) % 40`)
- `toKey()` doit retourner une chaîne du type `"7"`

Ce que vous devez faire ici

- créer la classe ;
- encapsuler la donnée ;
- valider l'index dans le constructeur ;
- gérer le bouclage du plateau dans `next()`.

Ce que vous ne devez pas faire ici

- aucune logique de case (case Property, Station, etc.) ;
- aucune logique de plateau ;
- aucune logique de partie.

### 2. Enum TileType

Fichier :

```
src/Enum/TileType.php
```

Valeurs imposées

```php
case GO;
case PROPERTY;
case STATION;
case COMPANY;
case TAX;
case CHANCE;
case COMMUNITY_CHEST;
case JAIL;
case FREE_PARKING;
case GO_TO_JAIL;
```

### 3. Enum ColorGroup

Fichier :

```
src/Enum/ColorGroup.php
```

Valeurs imposées

```php
case BROWN;
case LIGHT_BLUE;
case PINK;
case ORANGE;
case RED;
case YELLOW;
case GREEN;
case DARK_BLUE;
```

Cet enum ne sert qu'aux cases de type `Property`.

### 4. Interface Renderable

Fichier :

```
src/Contract/Renderable.php
```

Méthode imposée

```php
public function render(): string;
```

## Phase 2 - Hiérarchie des cases

À cette phase, vous créez l'héritage métier.

### 1. Classe abstraite Tile

Fichier :

```
src/Tile/Tile.php
```

Propriétés imposées

```php
protected string $name;
protected Square $position;
protected TileType $type;
```

Méthodes imposées

```php
public function __construct(string $name, Square $position)
public function getName(): string
public function getPosition(): Square
public function getType(): TileType
public function render(): string
public function landOn(Player $player, Game $game): void
abstract protected function applyEffect(Player $player, Game $game): void
protected function isOwnable(): bool
```

Rôle de `render()`

La méthode `render()` doit retourner une représentation texte courte de la case, utilisable dans l'affichage du plateau.

Convention imposée (exemples) :

- Départ : `"GO"`
- Prison / Simple visite : `"JAIL"`
- Parc Gratuit : `"PARK"`
- Allez en Prison : `"GTJ"`
- Une propriété : ses 3 premières lettres en majuscules (exemple : "Rue de Vaugirard" -> `"RUE"`)
- Une gare : `"GAR"`
- Une compagnie : `"CIE"`
- Impôts : `"TAX"`
- Chance : `"CHN"`
- Caisse de Communauté : `"CC"`

La méthode `Board::render()` devra utiliser `Tile::render()` pour construire l'affichage texte du plateau.

Règle de conception imposée

Vous devez appliquer ici un Template Method :

- `landOn()` contient la logique commune ;
- `applyEffect()` est redéfinie dans chaque sous-classe ;
- `isOwnable()` porte une partie de la logique partagée (par défaut `false`, redéfinie à `true` dans les cases achetables).

Ce que `landOn()` doit faire, dans cet ordre

1. positionner le joueur sur la case (`$player->setPosition($this->position)`) ;
2. appeler `applyEffect($player, $game)` ;

Vous ne devez pas changer cet ordre, ni ajouter de logique métier détaillée directement dans `landOn()`.

### 2. Classes filles imposées

Fichiers :

```
src/Tile/Property.php
src/Tile/Station.php
src/Tile/Company.php
src/Tile/Tax.php
src/Tile/Chance.php
src/Tile/CommunityChest.php
src/Tile/Go.php
src/Tile/Jail.php
src/Tile/FreeParking.php
src/Tile/GoToJail.php
```

Méthode imposée dans chaque sous-classe

```php
protected function applyEffect(Player $player, Game $game): void
```

Type imposé dans chaque constructeur

Chaque sous-classe doit initialiser son `TileType` correspondant.

Exemple attendu :

- `Go` doit porter `TileType::GO`
- `Property` doit porter `TileType::PROPERTY`
- etc.

#### Propriétés supplémentaires imposées sur `Property`

```php
private ColorGroup $colorGroup;
private int $price;
private int $rent;
private ?Player $owner = null;
```

Méthodes supplémentaires imposées sur `Property`

```php
public function __construct(string $name, Square $position, ColorGroup $colorGroup, int $price, int $rent)
public function getColorGroup(): ColorGroup
public function getPrice(): int
public function getRent(): int
public function getOwner(): ?Player
public function setOwner(?Player $owner): void
public function isOwned(): bool
protected function isOwnable(): bool // doit retourner true
```

`Property::isOwnable()` doit retourner `true`.

#### Propriétés supplémentaires imposées sur `Station` et `Company`

```php
private int $price;
private ?Player $owner = null;
```

Avec les méthodes équivalentes `getPrice()`, `getOwner()`, `setOwner()`, `isOwned()`, et `isOwnable()` retournant `true`.

#### Propriétés supplémentaires imposées sur `Tax`

```php
private int $amount;
```

Règles à implémenter dans cette phase

- **Go** : quand un joueur atterrit dessus (ou passe dessus, mais on ne gère que l'atterrissage pour l'instant), il reçoit une somme fixe (exemple : 200) ;
- **Property / Station / Company** : si la case n'a pas de propriétaire, rien ne se passe automatiquement (l'achat sera géré plus tard par `Game`) ; si la case a un propriétaire différent du joueur, le joueur paie un loyer au propriétaire ;
- **Tax** : le joueur perd le montant de la taxe ;
- **Chance / CommunityChest** : pour l'instant, `applyEffect()` peut se contenter d'un effet neutre ou très simple (exemple : gagner ou perdre une petite somme fixe) — vous ne devez pas coder tout le système de cartes maintenant ;
- **Jail** : pour l'instant, simple visite, aucun effet si le joueur n'est pas envoyé en prison par ailleurs ;
- **FreeParking** : aucun effet obligatoire ;
- **GoToJail** : envoie le joueur en prison (`$player->setInJail(true)` et repositionne le joueur sur la case Jail).

Ce que vous ne devez toujours pas faire

- pas de gestion des maisons/hôtels ;
- pas de gestion complète des cartes Chance/Caisse de Communauté ;
- pas de gestion de partie complète (tours de jeu).

## Phase 3 - Le lancer de dés

### Classe Dice

Fichier :

```
src/Dice.php
```

Méthodes imposées

```php
public function __construct(int $sides = 6)
public function roll(): int
// Retourne un tableau à deux entrées, exemple [3, 5]
public function rollTwo(): array
```

Rôle

Cette classe représente l'outil de tirage aléatoire.

Elle ne connaît ni le plateau, ni les joueurs, ni les règles de déplacement.

## Phase 4 - Construire le plateau

Vous pouvez maintenant créer `Board`.

### Classe Board

Fichier :

```
src/Board.php
```

Propriété imposée

```php
private array $tiles = [];
```

Format imposé de stockage

Le tableau `$tiles` doit être indexé par la clé retournée par `Square::toKey()`.

Exemple :

```php
$this->tiles['7'] = $tile;
```

Méthodes imposées

```php
public function placeTile(Tile $tile): void
public function getTileAt(Square $position): ?Tile
public function hasTileAt(Square $position): bool
public function getTiles(): array
public function render(): string
```

Règles imposées

- `placeTile()` pose ou remplace une case sur son index ;
- `render()` doit retourner une représentation texte du plateau (une ligne, ou plusieurs, selon votre choix, tant que chaque case apparaît) ;
- `getTiles()` doit retourner un tableau de `Tile`.

Point technique important

`Board` ne décide pas si un joueur peut acheter une case ou payer un loyer.

`Board` gère uniquement l'état du plateau.

## Phase 5 - Le joueur

### Classe Player

Fichier :

```
src/Player.php
```

Propriétés imposées

```php
private string $name;
private int $money;
private Square $position;
private bool $inJail = false;
```

Méthodes imposées

```php
public function __construct(string $name, int $startingMoney = 1500)
public function getName(): string
public function getMoney(): int
public function addMoney(int $amount): void
public function removeMoney(int $amount): void
public function getPosition(): Square
public function setPosition(Square $position): void
public function isInJail(): bool
public function setInJail(bool $inJail): void
```

Règle imposée

`removeMoney()` doit lever une `InsufficientFundsException` si le montant demandé dépasse l'argent disponible du joueur.

## Phase 6 - Exceptions métier

Fichiers imposés

```
src/Exception/MonopolyException.php
src/Exception/InsufficientFundsException.php
src/Exception/TileNotOwnableException.php
src/Exception/AlreadyOwnedException.php
src/Exception/InvalidPlayerActionException.php
```

Rôle imposé

Vous devez utiliser :

- `InsufficientFundsException` si un joueur n'a pas assez d'argent pour payer (loyer, taxe, achat) ;
- `TileNotOwnableException` si on tente d'acheter une case qui n'est pas achetable (`isOwnable()` retourne `false`) ;
- `AlreadyOwnedException` si on tente d'acheter une case déjà possédée par un autre joueur ;
- `InvalidPlayerActionException` pour toute action de joueur incohérente (exemple : ce n'est pas son tour, il tente d'acheter une case sur laquelle il n'est pas positionné, etc.).

Vous ne devez pas remplacer tout cela par de simples `false` ou de simples `echo`.

## Phase 7 - Factory de cases

### Classe TileFactory

Fichier :

```
src/Factory/TileFactory.php
```

Méthode imposée

```php
public function create(TileType $type, string $name, Square $position, array $options = []): Tile
```

`$options` sert à transmettre les paramètres spécifiques (prix, loyer, groupe de couleur, montant de la taxe...) selon le type de case demandé.

Interdiction

Dans `Game`, vous ne devez pas écrire :

```php
new Property(...)
new Station(...)
new Tax(...)
```

Toute création de case doit passer par la factory.

## Phase 8 - Construire la partie

Vous pouvez maintenant coder la classe la plus haute : `Game`.

### Classe Game

Fichier :

```
src/Game.php
```

Propriétés imposées

```php
private Board $board;
private array $players = [];
private int $currentPlayerIndex = 0;
private Dice $dice;
private TileFactory $tileFactory;
```

Méthodes imposées

```php
public function __construct(array $playerNames)
public function start(): void
public function getBoard(): Board
public function getCurrentPlayer(): Player
public function playTurn(): void
public function buyCurrentTile(): void
private function setupBoard(): void
private function nextPlayer(): void
```

Responsabilités imposées de `Game`

`Game` doit :

- créer le plateau ;
- créer les joueurs à partir de `$playerNames`, avec l'argent de départ par défaut ;
- appeler la factory pour créer les cases ;
- placer les cases au démarrage ;
- faire lancer les dés au joueur courant ;
- déplacer le joueur (via `Square::next()`) ;
- déclencher `Tile::landOn()` sur la case d'arrivée ;
- permettre au joueur courant d'acheter la case sur laquelle il se trouve, si elle est achetable et non possédée ;
- changer de joueur courant.

Responsabilités interdites à `Game`

`Game` ne doit pas :

- recalculer la logique détaillée de chaque type de case ;
- contenir un gros `switch`/`match` sur les types de cases pour appliquer leurs effets ;
- accéder directement au tableau interne du plateau.

### Mise en place du plateau initial

Vous devez utiliser exactement ce placement (plateau classique à 40 cases) :

| Index | Nom | Type | Groupe / info | Prix | Loyer |
|---|---|---|---|---|---|
| 0 | Départ | GO | - | - | - |
| 1 | Boulevard de Belleville | PROPERTY | BROWN | 60 | 2 |
| 2 | Caisse de Communauté | COMMUNITY_CHEST | - | - | - |
| 3 | Rue Lecourbe | PROPERTY | BROWN | 60 | 4 |
| 4 | Impôts sur le revenu | TAX | montant 200 | - | - |
| 5 | Gare Montparnasse | STATION | - | 200 | - |
| 6 | Rue de Vaugirard | PROPERTY | LIGHT_BLUE | 100 | 6 |
| 7 | Chance | CHANCE | - | - | - |
| 8 | Rue de Courcelles | PROPERTY | LIGHT_BLUE | 100 | 6 |
| 9 | Avenue de la République | PROPERTY | LIGHT_BLUE | 120 | 8 |
| 10 | Prison / Simple visite | JAIL | - | - | - |
| 11 | Boulevard de la Villette | PROPERTY | PINK | 140 | 10 |
| 12 | Compagnie de Distribution d'Électricité | COMPANY | - | 150 | - |
| 13 | Avenue de Neuilly | PROPERTY | PINK | 140 | 10 |
| 14 | Rue de Paradis | PROPERTY | PINK | 160 | 12 |
| 15 | Gare de Lyon | STATION | - | 200 | - |
| 16 | Avenue Mozart | PROPERTY | ORANGE | 180 | 14 |
| 17 | Caisse de Communauté | COMMUNITY_CHEST | - | - | - |
| 18 | Boulevard Saint-Michel | PROPERTY | ORANGE | 180 | 14 |
| 19 | Place Pigalle | PROPERTY | ORANGE | 200 | 16 |
| 20 | Parc Gratuit | FREE_PARKING | - | - | - |
| 21 | Avenue Matignon | PROPERTY | RED | 220 | 18 |
| 22 | Chance | CHANCE | - | - | - |
| 23 | Boulevard Malesherbes | PROPERTY | RED | 220 | 18 |
| 24 | Avenue Henri-Martin | PROPERTY | RED | 240 | 20 |
| 25 | Gare du Nord | STATION | - | 200 | - |
| 26 | Faubourg Saint-Honoré | PROPERTY | YELLOW | 260 | 22 |
| 27 | Place de la Bourse | PROPERTY | YELLOW | 260 | 22 |
| 28 | Compagnie des Eaux | COMPANY | - | 150 | - |
| 29 | Rue La Fayette | PROPERTY | YELLOW | 280 | 24 |
| 30 | Allez en Prison | GO_TO_JAIL | - | - | - |
| 31 | Avenue de Breteuil | PROPERTY | GREEN | 300 | 26 |
| 32 | Avenue Foch | PROPERTY | GREEN | 300 | 26 |
| 33 | Caisse de Communauté | COMMUNITY_CHEST | - | - | - |
| 34 | Boulevard des Capucines | PROPERTY | GREEN | 320 | 28 |
| 35 | Gare Saint-Lazare | STATION | - | 200 | - |
| 36 | Chance | CHANCE | - | - | - |
| 37 | Avenue des Champs-Élysées | PROPERTY | DARK_BLUE | 350 | 35 |
| 38 | Taxe de luxe | TAX | montant 100 | - | - |
| 39 | Rue de la Paix | PROPERTY | DARK_BLUE | 400 | 50 |

Convention imposée

- la case 0 est toujours `Go` ;
- la case 10 est toujours `Jail` ;
- la case 20 est toujours `FreeParking` ;
- la case 30 est toujours `GoToJail` ;
- l'argent de départ de chaque joueur est 1500.

### Règles métier minimales à obtenir

Quand `Game::playTurn()` est appelé, votre code doit :

1. faire lancer les dés au joueur courant ;
2. calculer sa nouvelle position via `Square::next()` ;
3. positionner le joueur ;
4. déclencher `landOn()` sur la case d'arrivée ;
5. si la case déclenche un paiement (loyer, taxe) et que le joueur n'a pas assez d'argent, laisser remonter `InsufficientFundsException` ;
6. changer de joueur courant.

Quand `Game::buyCurrentTile()` est appelé, votre code doit :

1. récupérer la case sur laquelle se trouve le joueur courant ;
2. lever `TileNotOwnableException` si la case n'est pas achetable ;
3. lever `AlreadyOwnedException` si la case a déjà un propriétaire ;
4. lever `InsufficientFundsException` si le joueur n'a pas assez d'argent ;
5. débiter le joueur et lui attribuer la case.

Cet ordre doit être respecté.

### Ce que vous ne gérez pas encore

- les paliers de loyer selon le nombre de gares/compagnies possédées ;
- les maisons/hôtels ;
- les cartes Chance/Caisse de Communauté détaillées ;
- la faillite et la fin de partie.

## index.php imposé

Le fichier `index.php` doit au minimum :

- créer une instance de `Game` avec au moins 2 joueurs ;
- appeler `start()` ;
- afficher le plateau ;
- jouer quelques tours de démonstration (`playTurn()`, éventuellement `buyCurrentTile()`) ;
- afficher l'état des joueurs (argent, position) après les tours.

Vous pouvez utiliser des `try/catch` pour afficher proprement les erreurs métier.

## Consignes de développement

À respecter

- propriétés `private` ou `protected` ;
- typage strict des paramètres et retours ;
- une classe par fichier ;
- aucune logique métier dans `index.php` ;
- pas de tableau "magique" dispersé partout.

À éviter

- gros `if/else`/`match` central dans `Game` sur le type de case ;
- duplication de logique entre cases ;
- index de case codés en dur partout ;
- création directe des cases dans plusieurs endroits.

## Déblocage pédagogique par étapes

Vous ne devez pas lire ce TP comme un corrigé complet.

Travaillez dans cet ordre :

### Étape A

Créez uniquement :

- `Square`
- `TileType`
- `ColorGroup`
- `Renderable`

Vérifiez que tout est propre.

### Étape B

Créez :

- `Tile`
- les 10 cases concrètes

À ce stade, une case doit déjà savoir appliquer son effet de base sur un joueur.

### Étape C

Créez :

- `Dice`
- `Board`
- `Player`

À ce stade, vous devez pouvoir poser des cases et positionner un joueur.

### Étape D

Ajoutez :

- les exceptions ;
- `TileFactory`

### Étape E

Créez `Game`.

À ce stade, la partie doit fonctionner avec alternance des tours, déplacement, et achat de case.

### Étape F

Vérifiez les cas d'erreur (fonds insuffisants, case déjà possédée, case non achetable).

Seulement maintenant.

## Ce qui sera évalué

L'évaluation portera sur :

- le respect strict de l'arborescence ;
- le respect strict des noms de classes ;
- le respect strict des signatures de méthodes ;
- la bonne hiérarchie d'héritage ;
- la mise en place des patterns demandés ;
- la qualité du polymorphisme ;
- la gestion correcte des règles métier de base ;
- la propreté du code.

## Bonus autorisés uniquement à la fin

Une fois le sujet principal terminé, vous pouvez ajouter :

- les maisons / hôtels et les paliers de loyer ;
- les cartes Chance / Caisse de Communauté avec effets variés (tirage aléatoire) ;
- les hypothèques ;
- la faillite et la fin de partie ;
- des tests automatisés.

Mais ces bonus ne doivent pas casser l'architecture imposée ci-dessus.

## AJOUTEZ UN README !!!

Et mettez les bonnes informations sur ce que vous avez eu le temps de faire ou pas en gardant / supprimant les emojis correspondants à chaque ligne.

### Classes principales

✅ / ❌ Square

- ✅ / ❌ `__construct()`
- ✅ / ❌ `getIndex()`
- ✅ / ❌ `equals()`
- ✅ / ❌ `toKey()`
- ✅ / ❌ `fromKey()`
- ✅ / ❌ `next()`

✅ / ❌ Dice

- ✅ / ❌ `roll()`
- ✅ / ❌ `rollTwo()`

✅ / ❌ Board

- ✅ / ❌ `placeTile()`
- ✅ / ❌ `getTileAt()`
- ✅ / ❌ `hasTileAt()`
- ✅ / ❌ `getTiles()`
- ✅ / ❌ `render()`

✅ / ❌ Player

- ✅ / ❌ `__construct()`
- ✅ / ❌ `getName()`
- ✅ / ❌ `getMoney()`
- ✅ / ❌ `addMoney()`
- ✅ / ❌ `removeMoney()`
- ✅ / ❌ `getPosition()`
- ✅ / ❌ `setPosition()`
- ✅ / ❌ `isInJail()`
- ✅ / ❌ `setInJail()`

✅ / ❌ Game

- ✅ / ❌ `__construct()`
- ✅ / ❌ `start()`
- ✅ / ❌ `getBoard()`
- ✅ / ❌ `getCurrentPlayer()`
- ✅ / ❌ `playTurn()`
- ✅ / ❌ `buyCurrentTile()`
- ✅ / ❌ `setupBoard()`
- ✅ / ❌ `nextPlayer()`

### Cases (Tile)

✅ / ❌ Tile

- ✅ / ❌ `__construct()`
- ✅ / ❌ `getName()`
- ✅ / ❌ `getPosition()`
- ✅ / ❌ `getType()`
- ✅ / ❌ `render()`
- ✅ / ❌ `landOn()`
- ✅ / ❌ `applyEffect()`
- ✅ / ❌ `isOwnable()`

✅ / ❌ Property (+ `getColorGroup()`, `getPrice()`, `getRent()`, `getOwner()`, `setOwner()`, `isOwned()`)
✅ / ❌ Station (+ `getPrice()`, `getOwner()`, `setOwner()`, `isOwned()`)
✅ / ❌ Company (+ `getPrice()`, `getOwner()`, `setOwner()`, `isOwned()`)
✅ / ❌ Tax
✅ / ❌ Chance
✅ / ❌ CommunityChest
✅ / ❌ Go
✅ / ❌ Jail
✅ / ❌ FreeParking
✅ / ❌ GoToJail

### Factory

✅ / ❌ TileFactory
✅ / ❌ `create()`

### Interface / Enums

✅ / ❌ Renderable

- ✅ / ❌ `render()`

✅ / ❌ TileType (10 valeurs)
✅ / ❌ ColorGroup (8 valeurs)

### Exceptions

✅ / ❌ MonopolyException
✅ / ❌ InsufficientFundsException
✅ / ❌ TileNotOwnableException
✅ / ❌ AlreadyOwnedException
✅ / ❌ InvalidPlayerActionException

### Bonus

✅ / ❌ Maisons / hôtels et paliers de loyer
✅ / ❌ Cartes Chance / Caisse de Communauté détaillées
✅ / ❌ Hypothèques
✅ / ❌ Faillite et fin de partie
✅ / ❌ Tests automatisés
✅ / ❌ Autre bonus : à préciser