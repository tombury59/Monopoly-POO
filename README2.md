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

- **utiliser une carte « Sortie de prison gratuite »** s'il en détient une (voir point 4) : sortie immédiate, sans payer ni tour supplémentaire ;
- payer une caution (exemple : 50) pour sortir immédiatement et jouer normalement ce tour ;
- lancer les dés : s'il fait un double, il sort de prison gratuitement et avance de ce double ; sinon, il reste en prison pour ce tour (tour "perdu") ;
- après 3 tours ratés en prison, la sortie devient obligatoire (paiement forcé de la caution).

### Ce qu'il faut faire

- Une propriété de comptage des tours passés en prison (sur `Player`, en plus de `inJail`).
- Une méthode (nom libre, par exemple `Game::playJailedTurn()` ou une branche au début de `playTurn()`) qui gère ce cas spécifique avant la logique normale de déplacement.
- Utiliser la carte est un **choix**, pas une obligation (un joueur peut vouloir rester en prison). Modélisez ce choix par une **méthode publique dédiée**, sur le modèle de `payToLeaveJail()` : par exemple `Game::useJailCard(): void`, que l'appelant invoque seulement si le joueur décide de poser sa carte. Elle vérifie que le joueur en détient une (`hasGetOutOfJailCard()`), la consomme (`useGetOutOfJailCard()`), le sort de prison (`setInJail(false)`, `resetTurnsInJail()`), puis **joue un tour normal** (`playTurn()`), comme après un paiement de caution.
- `playJailedTurn()` (le comportement par défaut si le joueur ne fait aucun choix) **ne touche pas à la carte** : il conserve sa logique « tenter un double / rester / sortie forcée après 3 tours ».

> **Moment d'utilisation.** La carte se pose **au début du tour** d'un joueur déjà en prison (via `useJailCard()`), **jamais** au moment où il entre en prison. Entrer en prison (case GoToJail, 3 doubles, ou carte `GO_TO_JAIL`) **termine le tour immédiatement** — aucune carte n'est consommée à cet instant. La carte ne sert qu'à repartir à un tour suivant, si et quand le joueur le décide.

### La carte « Sortie de prison gratuite » conservable

Contrairement à la simplification initiale du point 4, cette carte **ne s'applique pas au moment où on la pioche** : le joueur la **garde en main** jusqu'à ce qu'il soit en prison. En effet, on ne pioche une carte qu'en se déplaçant — donc en n'étant pas en prison ; l'appliquer immédiatement serait donc inutile.

- Sur `Player`, ajoutez un **compteur** de cartes « sortie gratuite » détenues (un joueur peut en cumuler deux : une de Chance, une de Caisse). Par exemple :

```php
private int $getOutOfJailCards = 0;

public function addGetOutOfJailCard(): void
public function hasGetOutOfJailCard(): bool
public function useGetOutOfJailCard(): void   // décrémente, refuse si le compteur est à 0
```

- **Limite assumée** : dans ce TP, les cartes ne quittent jamais la pioche (elles restent tirables). On ne modélise donc pas le retrait de la carte du deck tant qu'un joueur la détient — le compteur sur `Player` suffit à représenter « je peux sortir gratuitement une fois ».

### Attendu

- ✅ un joueur en prison ne se déplace pas normalement tant qu'il n'en est pas sorti ;
- ✅ sortie par carte conservée, par double, par paiement, ou forcée après 3 tours ;
- ✅ une carte « Sortie de prison gratuite » piochée est **conservée** puis utilisée plus tard, pas appliquée immédiatement ;
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
case EXIT_JAIL;            // carte « Sortie de prison gratuite » CONSERVÉE par le joueur (voir point 3), pas appliquée immédiatement
case PAY_ALL;              // value = montant à verser à chaque autre joueur
case RECEIVE_ALL;          // value = montant reçu de chaque autre joueur
```

`Card::apply()` doit utiliser un `match` sur `$this->effectType` pour appliquer le bon effet — même principe de Template Method/Strategy que dans `Tile`.

> **Cas avancés reportés.** `REPAIR_BUILDINGS` (payer un montant par maison/hôtel possédé) dépend du niveau de construction sur `Property` (point 5) — à compléter une fois le point 5 fait. Les cartes « avancer jusqu'à la gare / la compagnie la plus proche » demandent une recherche « case la plus proche dans le sens de la marche », plus complexe que `Board::findTileByType()` (qui retourne la première case d'un type, sans notion de distance). Pour ces dernières, préférez **deux cases distinctes** — `NEAREST_STATION` et `NEAREST_UTILITY` — plutôt qu'un unique `NEAREST_UTILITY_OR_RAILROAD` ambigu : chaque carte dit clairement sa destination, et le handler n'a pas à décoder un `$value`. Elles s'appuient sur une nouvelle méthode `Board::findNearestByType(Square $from, TileType $type): ?Square` (parcours par distance croissante depuis `$from`), puis réutilisent le mécanisme de `MOVE_TO` (calcul des pas + `resolveMovement`, qui gère déjà le +200 en passant Départ). Simplification assumée : la carte se contente d'avancer le joueur ; le loyer spécial (« double » pour une gare, « 10× les dés » pour une compagnie) est traité au **TP3 — Finalisation** (`README3.md`, section 3) et reste optionnel.

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

Contrairement au reste du TP2, ce point redevient **aussi directif que le TP principal** : respectez exactement les noms de propriétés et les signatures ci-dessous. La *logique interne* des méthodes reste à votre charge (comme dans le TP1), mais l'interface publique est imposée.

### Règle

Un joueur propriétaire de **toutes** les propriétés d'un même `ColorGroup` peut construire des maisons (jusqu'à 4), puis un hôtel, sur chacune. Le loyer (`Property::getRent()`) augmente selon le niveau de construction.

### Fichiers concernés

```
src/Tile/Property.php   (modifié)
src/Board.php           (modifié)
src/Game.php            (modifié)
```

### 5.1 — `Property` : niveau de construction

Propriété imposée à ajouter :

```php
private int $buildLevel = 0; // 0 = terrain nu, 1 à 4 = maisons, 5 = hôtel
```

Méthodes imposées à ajouter :

```php
public function getBuildLevel(): int
public function setBuildLevel(int $level): void
```

Contraintes :

- `setBuildLevel()` doit **valider la borne 0-5** et lever une exception si elle est dépassée (même principe que la validation d'index dans `Square::__construct()`).
- Vous ne modifiez **ni** la signature du constructeur imposée au TP1, **ni** les autres méthodes existantes de `Property`.

### 5.2 — `Property` : la grille de loyers officielle

On utilise ici les **vraies valeurs** du plateau Monopoly (édition France), pas une formule. Chaque terrain porte une grille de **6 loyers** indexée par le niveau de construction :

```
rents = [ loyer_base, 1_maison, 2_maisons, 3_maisons, 4_maisons, hôtel ]
//         index 0      index 1   index 2    index 3    index 4    index 5
```

Ainsi `getRent()` se réduit à `return $this->rents[$this->buildLevel]`. La colonne « monopole nu ×2 » n'est **pas** dans cette grille : elle est calculée à part en 5.4.

#### Données imposées (à recopier dans `setupBoard()`)

| Terrain | Groupe | `rents` (6 valeurs) | `housePrice` |
|---|---|---|---|
| Boulevard de Belleville | BROWN | `[2, 10, 30, 90, 160, 250]` | 50 |
| Rue Lecourbe | BROWN | `[4, 20, 60, 180, 320, 450]` | 50 |
| Rue de Vaugirard | LIGHT_BLUE | `[6, 30, 90, 270, 400, 550]` | 50 |
| Rue de Courcelles | LIGHT_BLUE | `[6, 30, 90, 270, 400, 550]` | 50 |
| Avenue de la République | LIGHT_BLUE | `[8, 40, 100, 300, 450, 600]` | 50 |
| Boulevard de la Villette | PINK | `[10, 50, 150, 450, 625, 750]` | 100 |
| Avenue de Neuilly | PINK | `[10, 50, 150, 450, 625, 750]` | 100 |
| Rue de Paradis | PINK | `[12, 60, 180, 500, 700, 900]` | 100 |
| Avenue Mozart | ORANGE | `[14, 70, 200, 550, 750, 950]` | 100 |
| Boulevard Saint-Michel | ORANGE | `[14, 70, 200, 550, 750, 950]` | 100 |
| Place Pigalle | ORANGE | `[16, 80, 220, 600, 800, 1000]` | 100 |
| Avenue Matignon | RED | `[18, 90, 250, 700, 875, 1050]` | 150 |
| Boulevard Malesherbes | RED | `[18, 90, 250, 700, 875, 1050]` | 150 |
| Avenue Henri-Martin | RED | `[20, 100, 300, 750, 925, 1100]` | 150 |
| Faubourg Saint-Honoré | YELLOW | `[22, 110, 330, 800, 975, 1150]` | 150 |
| Place de la Bourse | YELLOW | `[22, 110, 330, 800, 975, 1150]` | 150 |
| Rue La Fayette | YELLOW | `[24, 120, 360, 850, 1025, 1200]` | 150 |
| Avenue de Breteuil | GREEN | `[26, 130, 390, 900, 1100, 1275]` | 200 |
| Avenue Foch | GREEN | `[26, 130, 390, 900, 1100, 1275]` | 200 |
| Boulevard des Capucines | GREEN | `[28, 150, 450, 1000, 1200, 1400]` | 200 |
| Avenue des Champs-Élysées | DARK_BLUE | `[35, 175, 500, 1100, 1300, 1500]` | 200 |
| Rue de la Paix | DARK_BLUE | `[50, 200, 600, 1400, 1700, 2000]` | 200 |

> Vérification : `rents[0]` = l'ancien `'rent'` de chaque définition. Une **seule source de vérité** — supprimez l'ancien `'rent'` au profit de `'rents'`.

#### La chaîne de transmission `setupBoard → TileFactory → Property`

Le constructeur `Property::__construct(string $name, Square $position, ColorGroup $colorGroup, int $price, int $rent)` est **imposé** (TP1) : vous ne pouvez ni le renommer ni retirer ses 5 paramètres. Vous **ajoutez** donc, en fin de signature, deux paramètres **optionnels** :

```php
public function __construct(
    string $name, Square $position, ColorGroup $colorGroup,
    int $price, int $rent,
    array $rents = [], int $housePrice = 0   // ← ajouts, ne cassent aucun appel existant
)
```

- Propriétés imposées à ajouter : `private array $rents = [];` et `private int $housePrice = 0;`, avec getters `getRents(): array` et `getHousePrice(): int`.
- `getRent()` (signature TP1 inchangée) retourne `$this->rents[$this->buildLevel] ?? $this->rent` — le repli sur `$this->rent` évite un plantage si une `Property` est créée sans grille.
- Dans `setupBoard()` : chaque définition PROPERTY porte `'rents' => [...]` et `'housePrice' => ...` (table ci-dessus) à la place de `'rent' => ...`.
- Dans `TileFactory::create()`, branche PROPERTY : lisez `$options['rents'] ?? []` et `$options['housePrice'] ?? 0`, et nourrissez le paramètre imposé `$rent` avec `$rents[0] ?? 0`.

Contrainte : `getRent()` ne doit **pas** parcourir le plateau ni connaître les autres cases — elle ne dépend que de `$this->rents` et `$this->buildLevel`.

### 5.3 — `Board` : possession d'un groupe de couleur

Méthode imposée à ajouter dans `Board` :

```php
public function ownsWholeGroup(Player $player, ColorGroup $group): bool
```

Rôle et contraintes :

- retourne `true` si **toutes** les `Property` du `ColorGroup` donné ont pour propriétaire `$player` ;
- doit parcourir `$this->tiles`, ne considérer que les `Property` (attention : seules `Property` ont un `getColorGroup()` — un `instanceof` reste nécessaire, comme dans `Game::buyCurrentTile()`) ;
- c'est **la seule** méthode qui parcourt le plateau pour cette question : le loyer doublé (5.4) et l'autorisation de construire (5.5) la **réutilisent**, sans réécrire la boucle.

### 5.4 — Loyer doublé du monopole nu

Règle officielle : si un joueur possède **tout** un groupe de couleur mais **sans aucune construction** (`buildLevel === 0` sur chaque terrain), le loyer de base est **doublé**. Dès la première maison, c'est le barème de construction qui s'applique (le doublement ne se cumule **pas** avec les maisons).

- Cette logique vit dans `Property::applyEffect()` (qui reçoit `$game`), **pas** dans `getRent()` : `applyEffect()` interroge `$game->getBoard()->ownsWholeGroup(...)` pour savoir si le doublement s'applique.
- `applyEffect()` continue de s'appuyer sur `getRent()` pour le montant de base ; il ne fait qu'appliquer (ou non) le facteur 2 par-dessus quand `buildLevel === 0` et que le groupe est complet.

### 5.5 — `Game` : construire

Méthode imposée à ajouter dans `Game` :

```php
public function buildHouse(Property $property): void
```

Elle doit, **dans cet ordre** :

1. lever `InvalidPlayerActionException` si le propriétaire de la case ne possède pas tout le groupe (via `Board::ownsWholeGroup()`) ;
2. lever `InvalidPlayerActionException` si `getBuildLevel()` vaut déjà 5 (plafond hôtel atteint) ;
3. // TODO (point 7) : refuser si une propriété du groupe est hypothéquée ;
4. débiter le coût de construction au propriétaire via `Player::removeMoney()`, en utilisant `$property->getHousePrice()` (le prix officiel de la maison, issu de la grille 5.2) ;
5. incrémenter le niveau via `setBuildLevel(getBuildLevel() + 1)`.

### Ce que vous ne devez pas faire

- pas de parcours du plateau dupliqué : la recherche « groupe complet » n'existe qu'à **un** endroit (`Board::ownsWholeGroup()`) ;
- pas de gros `if/else` sur le type de case ajouté dans `Game` — le loyer reste géré par le polymorphisme de `Property::applyEffect()` ;
- pas de règle de **construction uniforme** (répartition équitable), pas d'inventaire de banque : hors périmètre, ce sont des bonus facultatifs.

### Attendu

- ✅ propriété `buildLevel` et méthodes `getBuildLevel()` / `setBuildLevel()` avec la borne 0-5 validée ;
- ✅ grille de loyers officielle (`rents` à 6 valeurs) + `housePrice` transmis via `setupBoard → TileFactory → Property`, `getRent()` lisant `rents[buildLevel]` ;
- ✅ `Board::ownsWholeGroup()` avec la signature exacte ci-dessus ;
- ✅ `Game::buildHouse()` respectant l'ordre des vérifications ci-dessus ;
- ✅ impossible de construire sans posséder tout le groupe ;
- ✅ le loyer appliqué dans `Property::applyEffect()` reflète le niveau de construction ;
- ✅ un groupe complet sans construction fait payer **le double** du loyer de base ; dès qu'une maison est posée, c'est le barème de construction qui s'applique (pas le double).

### À reprendre maintenant que ce point est fait

Retournez dans `Card::apply()` (point 4) et complétez le cas `REPAIR_BUILDINGS` : il doit parcourir les propriétés du joueur (via `Game::getPlayers()`/une méthode dédiée si vous en créez une, ou en itérant sur `Board::getTiles()` en filtrant celles dont `getOwner() === $player`), et débiter un montant par maison et un montant (généralement plus élevé) par hôtel, selon le niveau de construction que vous venez d'ajouter à `Property`.

Les cartes « gare/compagnie la plus proche » (`NEAREST_STATION` / `NEAREST_UTILITY`) restent optionnelles au TP2 : leur simple déplacement peut être fait ici si vous le souhaitez, mais leur **loyer spécial** (double gare / 10× dés compagnie) est traité au **TP3 — Finalisation** (`README3.md`, section 3).

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

### Interaction avec les constructions (point 5)

Règle officielle : on ne peut construire aucune maison/hôtel sur un groupe de couleur si **l'une** des propriétés de ce groupe est hypothéquée. Réciproquement, une propriété hypothéquée ne rapporte aucun loyer (déjà couvert par la propriété `mortgaged` ci-dessus).

Si vous avez déjà écrit la méthode « possède tout le groupe » du point 5 (ainsi que, éventuellement, le loyer doublé du monopole nu), reprenez-la maintenant :

- la méthode qui autorise la construction (`Game::buildHouse()` ou équivalent) doit refuser si **une** des propriétés du groupe est hypothéquée ;
- le loyer doublé du monopole nu ne doit pas s'appliquer si une propriété du groupe est hypothéquée (le groupe n'est plus « pleinement actif »).

C'est volontairement au point 7, et non au point 5, que cette interaction est traitée : la notion d'hypothèque n'existe pas encore quand vous codez les constructions. Laissez un `// TODO` dans votre méthode de construction au point 5 si vous voulez marquer l'emplacement.

### Attendu

- ✅ une case hypothéquée ne fait payer aucun loyer ;
- ✅ possibilité de rembourser pour la réactiver ;
- ✅ impossible de construire sur un groupe dont au moins une propriété est hypothéquée.

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

> **Déplacé vers le TP3.** Ce point a été sorti du TP2 : il constitue désormais la première partie du **TP3 — Finalisation** (`README3.md`), où le pattern Observer est implémenté en détail (`GameObserver`, `GameEvent`, `Game::notify()`, `ConsoleGameObserver`, liste des événements et emplacements des `notify()`).

En attendant, vous pouvez continuer à semer des `// TODO: notify` aux endroits pertinents de votre code : ils vous serviront de repères quand vous brancherez l'Observer au TP3.

---

## Ce qui reste interdit même en bonus

- casser la Factory (toujours interdiction de `new Property(...)` en dehors de `TileFactory`) ;
- réintroduire un gros `if/else`/`match` sur le type de case dans `Game` pour gérer les loyers ou les effets (ça doit continuer à passer par le polymorphisme de `Tile`/`applyEffect()`) ;
- dupliquer la logique de vérification des fonds ailleurs qu'à travers `Player::removeMoney()`.

> Les notifications d'événements (Observer) ont été déplacées vers le **TP3 — Finalisation** (`README3.md`), qui a sa propre checklist.
