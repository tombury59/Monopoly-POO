# TP3 - Finalisation : rendre le moteur observable, configurable et testé

## Objectif

Les TP1 et TP2 ont produit un **moteur de jeu complet** : plateau, cases, joueurs, règles (déplacement, prison, cartes, constructions, loyers variables, hypothèques, faillite). Ce TP ne rajoute presque aucune *règle* : il transforme ce moteur en un **produit fini**, prêt à être branché sur un vrai front et à évoluer sans se casser.

Trois axes :

1. **Observable** — le moteur notifie ce qui se passe (pattern Observer), au lieu de modifier son état en silence.
2. **Configurable** — certaines règles deviennent **activables/désactivables** (à commencer par le Parc Gratuit « jackpot »), sans toucher au cœur du jeu.
3. **Fiabilisé** — autoloader propre, tests automatisés, et finition des cas laissés en suspens dans le TP2.

## Prérequis

- Le TP2 est terminé jusqu'au point 8 inclus (faillite et fin de partie), ou au minimum : cartes, constructions, loyers variables gares/compagnies fonctionnels.
- Vous ne cassez **aucune** signature imposée aux TP1/TP2. Vous **ajoutez**.
- Les design patterns déjà en place (Factory, Template Method, Strategy) restent intacts.

## Philosophie

Comme le TP2, ce TP est **directif sur les interfaces** (noms de classes, signatures) mais vous laisse écrire la logique. Il est en revanche plus exigeant sur la **propreté architecturale** : c'est un TP de finition, la qualité prime sur la quantité.

## Vue d'ensemble des ajouts

```
monopoly/
├── composer.json                     (nouveau — autoloader)
├── tests/                            (nouveau — tests automatisés)
│   └── ...
└── src/
    ├── Game.php                      (modifié)
    ├── Contract/
    │   └── GameObserver.php          (nouveau)
    ├── GameEvent.php                 (nouveau)
    ├── Config/
    │   └── GameRules.php             (nouveau — règles activables)
    ├── Observer/
    │   ├── ConsoleGameObserver.php   (nouveau)
    │   └── FreeParkingObserver.php   (nouveau — cagnotte du Parc Gratuit)
    └── Tile/
        ├── FreeParking.php            (modifié)
        ├── Station.php                (modifié — loyer spécial carte)
        └── Company.php                (modifié — loyer spécial carte)
```

## Ordre de travail conseillé

1. Notifications d'événements (Observer) — la fondation, tout le reste s'y appuie
2. Règles activables + Parc Gratuit « jackpot »
3. Loyers spéciaux des cartes « gare/compagnie la plus proche »
4. Autoloader (Composer / PSR-4)
5. Tests automatisés

Chaque partie est indépendante : vous pouvez vous arrêter après n'importe laquelle et avoir un projet cohérent.

---

## 1. Notifications d'événements (Observer)

> Cette partie reprend et **remplace** le point 9 esquissé dans le TP2 : c'est ici qu'on l'implémente pour de bon.

### Problème à résoudre

Aujourd'hui, `Game::playTurn()` (achat, prison, cartes, faillite…) modifie l'état du jeu **en silence** : rien ne permet à un appelant extérieur (un futur front web, un CLI, des logs, des tests) de savoir *ce qui s'est passé* pendant un tour — un passage par Départ, un loyer payé, une carte tirée, une faillite. Comparer l'état avant/après ne dit pas *pourquoi* les choses ont changé.

C'est aussi la **porte d'entrée vers un vrai projet** : le jour où vous branchez un front, il vous suffira d'écrire un nouvel observateur, sans toucher au moteur.

### Ce qui est imposé

Un **Observer** : `Game` (le *sujet*) notifie une liste d'observateurs à chaque événement notable, sans savoir ce qu'ils en font.

Fichier :

```
src/Contract/GameObserver.php
```

```php
public function onEvent(GameEvent $event): void;
```

Fichier :

```
src/GameEvent.php
```

```php
private string $type;
private string $message;
private array $context;

public function __construct(string $type, string $message, array $context = [])
public function getType(): string
public function getMessage(): string
public function getContext(): array
```

`$type` catégorise l'événement sans que l'observateur ait à parser le message (`"passed_go"`, `"rent_paid"`, `"card_drawn"`, `"bankruptcy"`…). `$context` porte les données structurées utiles à un affichage riche (montant, nom de joueur, nom de case…), sans obliger à tout reconstruire depuis le texte.

### Modifications sur `Game`

```php
private array $observers = [];

public function addObserver(GameObserver $observer): void
public function removeObserver(GameObserver $observer): void
private function notify(GameEvent $event): void
```

`notify()` parcourt `$this->observers` et appelle `onEvent()` sur chacun. C'est la **seule** méthode de `Game` qui connaît ce détail — partout ailleurs, le code appelle `$this->notify(new GameEvent(...))` aux endroits pertinents, sans jamais boucler lui-même sur `$this->observers`.

### Un observateur console pour commencer

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

Dans `index.php` : `$game->addObserver(new ConsoleGameObserver());`

### Liste des événements à couvrir

Vous avez semé des `// TODO: notify` dans tout le code depuis le TP2. Reprenez-les. Au **minimum**, notifiez :

**Déplacement / plateau** : `dice_rolled`, `player_moved`, `passed_go`, `landed_on_tile`
**Argent** : `rent_paid`, `tax_paid`, `money_gained`, `money_lost`
**Propriétés** : `tile_purchased`, `house_built`, `hotel_built`, `tile_mortgaged`, `tile_unmortgaged`
**Dés / tours spéciaux** : `double_rolled`, `three_doubles`, `turn_ended`
**Prison** : `sent_to_jail`, `jail_paid`, `jail_escaped_by_double`, `jail_card_used`, `jail_forced_release`, `jail_turn_skipped`
**Cartes** : `card_drawn`
**Fin de partie** : `player_bankrupt`, `game_over`

Le `$context` est indicatif, à ajuster à vos besoins.

### Où placer les `notify()`

Juste **après** l'action réelle — **jamais à la place**. L'Observer ne porte **aucune** logique métier (pas de calcul d'argent, pas de décision de déplacement) : il ne fait qu'informer, après coup, que quelque chose s'est produit. Vos `// TODO: notify` marquent déjà les emplacements ; remplacez-les par un appel `$this->notify(...)`.

### Attendu

- ✅ `Game` ne connaît pas le détail de ce que font ses observateurs (aucun `if` sur leur type dans `notify()`) ;
- ✅ au moins : déplacement, passage par Départ, loyer payé, achat, faillite sont notifiés ;
- ✅ un `ConsoleGameObserver` fonctionnel, remplaçable par un futur observateur front sans toucher à `Game` ;
- ✅ plus aucun `// TODO: notify` orphelin dans le code.

---

## 2. Règles optionnelles activables (dont le Parc Gratuit)

### Problème

Certaines règles sont des **variantes** : le « jackpot » du Parc Gratuit (règle maison très répandue mais **non officielle**), ou d'autres options que vous voudriez pouvoir activer selon la partie. Les coder « en dur » dans `Game` obligerait à modifier le moteur pour changer une variante. On veut pouvoir dire, à la création de la partie : « cette partie joue avec le jackpot, celle-là non ».

### Ce qui est imposé : un objet de configuration

Fichier :

```
src/Config/GameRules.php
```

Une classe (ou un enum de flags, à vous) qui centralise les options activables. Au minimum :

```php
private bool $freeParkingJackpot = false;

public function __construct(bool $freeParkingJackpot = false)
public function isFreeParkingJackpotEnabled(): bool
```

`Game::__construct()` accepte un `GameRules` **optionnel** (par défaut : tout désactivé, donc règles officielles) :

```php
public function __construct(array $playerNames, ?GameRules $rules = null)
```

Le TP1 imposait `__construct(array $playerNames)`. Ajouter ici un **paramètre optionnel en fin de signature** est une extension **autorisée** — exactement la convention déjà appliquée au TP2 pour étendre `Property::__construct` : les 5 paramètres imposés ne bougent pas, aucun appel existant (`new Game([...])`) n'est cassé. Si vous préférez ne pas toucher du tout au constructeur, une alternative propre est un setter dédié (`Game::setRules(GameRules $rules): void`) appelé après la création.

Ainsi le comportement par défaut reste **strictement officiel** ; les variantes ne s'activent que sur demande explicite.

### Le Parc Gratuit « jackpot »

Règle maison : toutes les sommes payées à la banque (taxes, amendes…) vont dans une **cagnotte** au centre du plateau ; le joueur qui s'arrête sur le Parc Gratuit **récupère toute la cagnotte**, qui repart à zéro.

Ce qu'il faut faire :

- une cagnotte sur `Game` (`private int $freeParkingPot = 0;`) avec de quoi l'alimenter et la vider ;
- `FreeParking::applyEffect()` : **si** la règle est activée (`GameRules`), verser la cagnotte au joueur et la remettre à 0 ; sinon, comportement officiel (rien).

### La belle façon de l'alimenter : via l'Observer

C'est ici que le point 1 paie. Plutôt que de coupler `Tax` (et les amendes de cartes) à la cagnotte de `Game`, **écrivez un observateur** :

```
src/Observer/FreeParkingObserver.php
```

Il écoute les événements `tax_paid` (et éventuellement `money_lost`) et remplit la cagnotte — **sans que `Tax` ni `Card` ne connaissent l'existence du jackpot**. On ne l'ajoute à la partie que si la règle est activée.

Réfléchissez : comment cet observateur accède-t-il à la cagnotte ? (Il peut recevoir le `Game` dans son constructeur, ou `Game` peut exposer `addToFreeParkingPot(int)`.) L'important : `Tax` reste ignorante du jackpot, exactement comme le veut l'Observer.

### Autres variantes possibles (facultatif)

Si vous voulez enrichir `GameRules` : loyer non perçu quand le propriétaire est en prison, enchères sur refus d'achat, salaire de Départ configurable… Une option = un flag dans `GameRules` + une lecture au bon endroit. N'en abusez pas : le Parc Gratuit suffit à démontrer le mécanisme.

### Attendu

- ✅ `GameRules` centralise les options ; par défaut, la partie joue les **règles officielles** ;
- ✅ le jackpot du Parc Gratuit fonctionne quand il est activé, ne fait rien quand il ne l'est pas ;
- ✅ `Tax` et `Card` **ne connaissent pas** la cagnotte : elle est alimentée via un observateur ;
- ✅ activer/désactiver la règle ne demande **aucune** modification du moteur, juste un `GameRules` différent à la création.

---

## 3. Loyers spéciaux des cartes « gare/compagnie la plus proche »

### Rappel

Au TP2, les cartes `NEAREST_STATION` / `NEAREST_UTILITY` se contentaient d'avancer le joueur, qui payait ensuite le loyer **normal**. Les vraies cartes imposent un loyer **spécial** :

- gare la plus proche : le joueur paie **le double** du loyer de gare normal ;
- compagnie la plus proche : le joueur **relance les dés** et paie **10×** le résultat, quel que soit le nombre de compagnies possédées.

### Ce qu'il faut faire

Le défi : `Station`/`Company::applyEffect()` ne savent pas *comment* le joueur est arrivé (par les dés, ou poussé par une carte). Réfléchissez à comment transmettre cette information — par exemple un indicateur temporaire sur `Game` (« le prochain atterrissage vient d'une carte X »), posé par la carte avant de déclencher `landOn()` et consommé par `applyEffect()`, plutôt que de dupliquer la logique de loyer dans `Card`.

C'est une vraie question de conception ; ne la traitez que si les points 1 et 2 sont finis. Elle reste **optionnelle** dans les attendus.

### Attendu (optionnel)

- ✅ la carte « gare la plus proche » fait payer le double du loyer de gare ;
- ✅ la carte « compagnie la plus proche » fait payer 10× un lancer de dés ;
- ✅ aucune duplication de la logique de loyer entre `Card` et `Station`/`Company`.

---

## 4. Autoloader (Composer / PSR-4)

### Problème

`index.php` empile une trentaine de `require_once` à la main, dans le bon ordre. Chaque nouvelle classe oblige à en ajouter un (vous vous êtes déjà fait avoir avec `InvalidPropertyLevelException`). Un vrai projet PHP charge ses classes automatiquement.

### Ce qu'il faut faire

Deux niveaux, au choix :

- **Niveau simple** — un `spl_autoload_register()` maison en tête de `index.php` : à partir du nom de classe, il calcule le chemin du fichier et le charge. Aucune dépendance externe. Suppose une convention de nommage fichier ↔ classe (que vous respectez déjà en grande partie).
- **Niveau pro (recommandé)** — **Composer** avec autoloading **PSR-4** : un `composer.json` déclarant un namespace racine (ex. `Monopoly\`) mappé sur `src/`, puis `composer dump-autoload`. Cela impose d'ajouter un `namespace` en tête de chaque fichier et des `use` là où c'est nécessaire — un refactor mécanique mais formateur.

Dans les deux cas, `index.php` se réduit à **un seul** `require` (l'autoloader) au lieu de trente.

### Attendu

- ✅ plus de longue liste de `require_once` manuels ;
- ✅ ajouter une classe ne demande plus de toucher à `index.php` ;
- ✅ (niveau pro) namespaces PSR-4 cohérents, `composer.json` fonctionnel.

---

## 5. Tests automatisés

### Problème

Vous avez validé chaque mécanique avec des scripts jetables. Un projet fini garde ses tests : ils documentent le comportement attendu et détectent les régressions à chaque modification.

### Ce qu'il faut faire

- Installer **PHPUnit** (via Composer — d'où l'intérêt d'avoir fait le point 4 avant).
- Un dossier `tests/`, une classe de test par classe métier importante (`SquareTest`, `PropertyTest`, `BoardTest`, `GameTest`…).
- Convertissez vos scénarios de scratchpad en vrais tests : loyer selon le niveau de construction, loyer doublé du monopole nu, comptage des gares, cycle de la carte de prison, faillite, etc.

Conseil : privilégiez des tests **déterministes**. Pour ce qui dépend du hasard (dés, tirage de cartes), injectez ou fixez la source d'aléa, ou testez les méthodes de calcul isolément (comme `Property::getRent()`), plutôt qu'une partie entière.

### Attendu

- ✅ une suite PHPUnit qui passe (`vendor/bin/phpunit`) ;
- ✅ au moins les mécaniques clés du TP2 couvertes ;
- ✅ des tests déterministes, indépendants du hasard.

---

## Ce qui reste interdit même en finalisation

- casser la Factory (`new Property(...)` hors `TileFactory`) ;
- réintroduire un gros `if/else`/`match` sur le type de case dans `Game` ;
- mettre de la logique métier dans un observateur (il **informe**, il ne décide pas) ;
- coupler `Tax`/`Card` à la cagnotte du Parc Gratuit (elle passe par l'Observer) ;
- dupliquer la vérification des fonds ailleurs que dans `Player::removeMoney()`.