# TP3 - Finalisation : rendre le moteur observable, pilotable, complet et testé

## Objectif

Les TP1 et TP2 ont produit un **moteur de jeu complet** : plateau, cases, joueurs, règles (déplacement, prison, cartes, constructions, loyers variables, hypothèques, faillite). Ce TP rajoute peu de *règles* (l'échange entre joueurs étant la principale) : il transforme surtout ce moteur en un **produit fini**, prêt à être branché sur un vrai front et à évoluer sans se casser.

Quatre axes :

1. **Observable** — le moteur notifie ce qui se passe (pattern Observer), au lieu de modifier son état en silence.
2. **Pilotable** — le tour n'est plus un bloc « tout-en-un » : le moteur expose ses **points de décision** pour qu'un vrai front (web, CLI interactif) puisse rendre la main au joueur. C'est la marche décisive vers une application jouable.
3. **Complet & configurable** — on finit les règles laissées de côté (loyers spéciaux des cartes, échanges entre joueurs) et on rend certaines variantes **activables/désactivables** sans toucher au cœur du jeu.
4. **Fiabilisé** — autoloader propre et tests automatisés.

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
    ├── GameEvent.php                 (nouveau — un fait : type + context)
    ├── Enum/
    │   ├── GameEventType.php         (nouveau — catalogue des événements)
    │   ├── TurnPhase.php             (nouveau — phases d'un tour)
    │   └── PlayerAction.php          (nouveau — actions proposables)
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

## Ordre de travail

Le TP est découpé en trois blocs, à faire dans l'ordre : l'architecture d'abord (elle sert de socle), les règles ensuite (elles s'appuient dessus), l'industrialisation en dernier.

**Bloc A — Architecture (rendre le moteur observable et pilotable)**
1. Notifications d'événements (Observer) — la fondation, tout le reste s'y appuie
2. Modèle de tour interactif — le grand pas vers une vraie application

**Bloc B — Compléter et enrichir les règles**
3. Loyers spéciaux des cartes « gare/compagnie la plus proche »
4. Échanges de propriétés entre joueurs
5. Règles optionnelles activables (Parc Gratuit « jackpot », rachat de propriété)

**Bloc C — Industrialiser**
6. Autoloader (Composer / PSR-4)
7. Tests automatisés

Chaque partie est indépendante : vous pouvez vous arrêter après n'importe laquelle et avoir un projet cohérent.

---

# Bloc A — Architecture

## 1. Notifications d'événements (Observer)

> Cette partie reprend et **remplace** le point 9 esquissé dans le TP2 : c'est ici qu'on l'implémente pour de bon.

### Problème à résoudre

Aujourd'hui, `Game::playTurn()` (achat, prison, cartes, faillite…) modifie l'état du jeu **en silence** : rien ne permet à un appelant extérieur (un futur front web, un CLI, des logs, des tests) de savoir *ce qui s'est passé* pendant un tour — un passage par Départ, un loyer payé, une carte tirée, une faillite. Comparer l'état avant/après ne dit pas *pourquoi* les choses ont changé.

C'est aussi la **porte d'entrée vers un vrai projet** : le jour où vous branchez un front, il vous suffira d'écrire un nouvel observateur, sans toucher au moteur.

### Le principe directeur : l'événement porte des *faits*, pas du *texte*

Une décision de conception structure tout le reste : **un `GameEvent` ne contient aucune phrase**. Il porte seulement *ce qui s'est passé* — un **type** et un **context** (les données brutes). La mise en mots (« Bob paie 50 de loyer à Alice ») est de la **présentation** : c'est le travail de l'**observateur**, pas du moteur.

Pourquoi ? Parce qu'un même événement peut être rendu de dix façons : une console française, un front web anglais, un observateur qui écrit du JSON et se fiche du texte. Si la phrase était figée dans l'événement (ou pire, dans le moteur), on serait coincé. En ne transportant que les faits, chaque observateur formate à sa guise. C'est l'esprit même de l'Observer : le moteur **rapporte**, il ne **raconte** pas.

### Ce qui est imposé

**1. Un catalogue d'événements typé** — plutôt que des chaînes en dur (`'passed_go'`, fragiles et sans autocomplétion), un **enum adossé à une string** :

```
src/Enum/GameEventType.php
```

```php
enum GameEventType: string
{
    case PASSED_GO      = 'passed_go';
    case RENT_PAID      = 'rent_paid';
    case TILE_PURCHASED = 'tile_purchased';
    case CARD_DRAWN     = 'card_drawn';
    case PLAYER_BANKRUPT = 'player_bankrupt';
    // ... un case par événement notable (voir la liste plus bas)
}
```

Vous gagnez l'autocomplétion, la vérification à la compilation, et une liste centralisée. La `->value` (la string) reste disponible pour l'affichage ou de futurs logs.

**2. Le contrat observateur** :

```
src/Contract/GameObserver.php
```

```php
public function onEvent(GameEvent $event): void;
```

**3. L'événement — type + context, sans message** :

```
src/GameEvent.php
```

```php
private GameEventType $type;
private array $context;

public function __construct(GameEventType $type, array $context = [])
public function getType(): GameEventType
public function getContext(): array
```

`$context` porte les données structurées de l'événement (`['player' => 'Bob', 'amount' => 50, 'tile' => 'Rue de la Paix']`). L'observateur y puise ce dont il a besoin.

### Modifications sur `Game`

```php
private array $observers = [];

public function addObserver(GameObserver $observer): void
public function removeObserver(GameObserver $observer): void
private function notify(GameEvent $event): void
public function emit(GameEventType $type, array $context = []): void
```

- `notify()` (privé) parcourt `$this->observers` et appelle `onEvent()` sur chacun. C'est la **seule** méthode qui connaît la boucle — aucun `if` sur le type d'observateur.
- `emit()` (public) est le point d'entrée pratique : il construit le `GameEvent` et le passe à `notify()`. Partout dans le code, on écrit `$this->emit(GameEventType::XXX, [...])` — court, uniforme, et `Game` garde le contrôle de la construction de l'événement.
- **`emit()` est public exprès** : les **cases** (`Tax`, `Property`, `GoToJail`…) ne sont pas dans `Game` mais reçoivent `$game` en paramètre de `applyEffect()`. Elles émettent via `$game->emit(...)` sans jamais toucher à `GameEvent` ni aux observateurs.

### Un observateur console — c'est lui qui porte le texte

```
src/Observer/ConsoleGameObserver.php
```

Puisque l'événement n'a pas de message, l'observateur **compose** la phrase à partir du type et du context, via un `match` :

```php
class ConsoleGameObserver implements GameObserver
{
    public function onEvent(GameEvent $event): void
    {
        echo '[' . $event->getType()->value . '] ' . $this->format($event) . PHP_EOL;
    }

    private function format(GameEvent $event): string
    {
        $c = $event->getContext();
        return match ($event->getType()) {
            GameEventType::PASSED_GO      => "{$c['player']} passe par la case Départ (+{$c['amount']}).",
            GameEventType::TILE_PURCHASED => "{$c['player']} achète {$c['tile']} pour {$c['price']}.",
            // ... un cas par événement
            default => '',   // filet tant que tous ne sont pas couverts
        };
    }
}
```

C'est **ici, et nulle part ailleurs**, que vit le texte français. Un futur observateur web aurait son propre `format()`.

Dans `index.php` : `$game->addObserver(new ConsoleGameObserver());`

### La méthode de travail : chaque événement est une *paire*

Brancher un événement, c'est toujours deux gestes indissociables :

1. **émettre** le fait dans le moteur — `$this->emit(GameEventType::XXX, ['clé' => valeur, ...])` ;
2. **traduire** ce fait dans le `format()` de l'observateur — `GameEventType::XXX => "phrase lisant $c['clé']"`.

La règle d'or : **les clés que le `format()` lit doivent être exactement celles que l'`emit()` écrit.** Une clé oubliée = warning `Undefined array key` + phrase vide. Soyez donc discipliné sur le `context` : chaque `emit` fournit toutes les données dont sa phrase aura besoin.

### Liste des événements à couvrir

Vous avez semé des `// TODO: notify (type)` dans tout le code depuis le TP2 — chacun indique déjà l'événement attendu. Reprenez-les : un `case` dans `GameEventType`, un `emit()` à l'emplacement, un cas dans le `format()`. Au **minimum** :

**Déplacement / plateau** : `dice_rolled`, `player_moved`, `passed_go`, `landed_on_tile`
**Argent** : `rent_paid`, `tax_paid`, `money_gained`, `money_lost`
**Propriétés** : `tile_purchased`, `house_built`, `hotel_built`, `tile_mortgaged`, `tile_unmortgaged`
**Dés / tours spéciaux** : `double_rolled`, `three_doubles`, `turn_ended`
**Prison** : `sent_to_jail`, `jail_paid`, `jail_escaped_by_double`, `jail_card_used`, `jail_forced_release`, `jail_turn_skipped`
**Cartes** : `card_drawn`
**Fin de partie** : `player_bankrupt`, `game_over`

Le `$context` de chaque `emit` est à ajuster à ce que sa phrase (dans le `format()`) doit afficher — souvent `player`, `amount`, `tile`.

### Où placer les `emit()`

Juste **après** l'action réelle — **jamais à la place**. L'Observer ne porte **aucune** logique métier (pas de calcul d'argent, pas de décision de déplacement) : il ne fait qu'informer, après coup, que quelque chose s'est produit. Vos `// TODO: notify (type)` marquent déjà les emplacements ; remplacez-les par un `$this->emit(GameEventType::TYPE, [...])` (ou `$game->emit(...)` dans une case).

Deux points d'attention repérés à l'usage :
- **`turn_ended`** est émis dans le `finally` de `playTurn`, **après** `nextPlayer()` : `getCurrentPlayer()` est déjà le joueur *suivant*. Pour nommer celui qui vient de jouer, gardez la référence capturée en début de méthode.
- Un même passage peut émettre **plusieurs** événements (la sortie de prison forcée : `jail_forced_release` **et** `jail_paid`).

### Attendu

- ✅ `Game` ne connaît pas le détail de ce que font ses observateurs (aucun `if` sur leur type dans `notify()`) ;
- ✅ l'événement ne transporte **aucune phrase** : type + context uniquement, le texte vit dans l'observateur ;
- ✅ au moins : déplacement, passage par Départ, loyer payé, achat, faillite sont notifiés ;
- ✅ un `ConsoleGameObserver` fonctionnel, remplaçable par un futur observateur front sans toucher à `Game` ;
- ✅ plus aucun `// TODO: notify` orphelin, et chaque `emit` a son cas dans le `format()` (pas de clé de context manquante).

---

## 2. Modèle de tour interactif

### Problème

Aujourd'hui, `Game::playTurn()` fait **tout d'un coup** : il lance les dés, déplace le joueur, résout la case, et l'achat est décidé *à l'extérieur* par la boucle de démo. Ça marche pour un script automatique, mais **aucun vrai front ne fonctionne comme ça**. Sur le web, chaque clic est une requête distincte : il faut lancer les dés, **s'arrêter**, montrer au joueur « tu es sur la Rue de la Paix, veux-tu l'acheter ? », attendre sa réponse, puis reprendre. Le moteur doit donc exposer ses **points de décision** au lieu de tout enchaîner.

C'est l'étape qui fait passer d'un *moteur qui joue seul* à un *moteur qu'on pilote*.

### Principe : ne rien casser, ajouter une couche

`playTurn()` (imposé au TP1) **reste** : il devient la version « tour automatique » pratique pour la console et les tests. À côté, vous ajoutez une **API interactive** que `playTurn()` peut d'ailleurs réutiliser en interne. Aucune signature existante ne change.

L'idée : un tour est une petite **machine à états**. À tout instant, la partie est dans une **phase**, et propose une liste d'**actions possibles** au joueur courant. Le front lit ces actions, en choisit une, l'exécute, et la phase avance.

### Ce qui est imposé

**Deux enums.**

```
src/Enum/TurnPhase.php
```

Les étapes d'un tour, par exemple : `AWAITING_ROLL` (on attend le lancer), `AWAITING_ACTION` (déplacement fait, case résolue, le joueur peut gérer ses biens / acheter), `TURN_OVER` (tour terminé, on peut passer au suivant). À vous d'affiner (une phase prison en amont, par exemple).

```
src/Enum/PlayerAction.php
```

Les actions qu'un joueur peut se voir proposer : `ROLL`, `BUY_TILE`, `BUILD_HOUSE`, `SELL_HOUSE`, `MORTGAGE`, `UNMORTGAGE`, `USE_JAIL_CARD`, `PAY_BAIL`, `END_TURN`… (on l'enrichira au fil du bloc B avec `PROPOSE_TRADE`, `BUYOUT_TILE`, etc.). Ce sont exactement les gestes que vous avez déjà codés — vous ne réécrivez pas la logique, vous la **nommez** pour pouvoir la proposer.

**Sur `Game`, l'API de pilotage :**

```php
public function getCurrentPhase(): TurnPhase
public function getAvailableActions(): array   // liste de PlayerAction pour le joueur courant, selon la phase et l'état
public function endTurn(): void                // clôt le tour et passe au joueur suivant
```

- `getAvailableActions()` est le cœur : elle **calcule** ce que le joueur a le droit de faire *maintenant*. En phase `AWAITING_ROLL`, ce sera `ROLL` (ou `PAY_BAIL` / `USE_JAIL_CARD` s'il est en prison). En phase `AWAITING_ACTION`, ce sera `END_TURN` + éventuellement `BUY_TILE` (si la case est achetable et libre), `BUILD_HOUSE` (s'il possède un groupe complet et a les fonds), `MORTGAGE`, etc. C'est de la **lecture d'état** : les vraies vérifications restent dans vos méthodes existantes (`buildHouse`, `mortgage`… qui continuent de lever leurs exceptions si on les appelle à tort).
- Vos méthodes d'action existantes (`buyCurrentTile`, `buildHouse`, `mortgage`…) deviennent les « transitions » que le front déclenche. Elles doivent **faire avancer la phase** quand c'est pertinent (lancer les dés fait passer de `AWAITING_ROLL` à `AWAITING_ACTION`).

### La boucle, avant / après

Aujourd'hui (`index.php`, tout enchaîné) :

```php
$game->playTurn();
try { $game->buyCurrentTile($player); } catch (MonopolyException $e) {}
```

Demain, un front (pseudo-code) — **le moteur ne décide plus, il propose** :

```
tant que la phase n'est pas TURN_OVER :
    actions = game.getAvailableActions()
    choix   = /* clic du joueur, ou IA, ou input console */
    game.exécuter(choix)      // roll / buy / build / endTurn...
```

Le même moteur alimente alors une UI web, un CLI interactif **ou** un joueur automatique — chacun n'est qu'une façon différente de choisir dans `getAvailableActions()`.

### Le lien avec l'Observer

Les deux se complètent : `getAvailableActions()` dit **ce que le joueur peut faire** (avant l'action), l'Observer dit **ce qui vient de se passer** (après l'action). Ensemble, ils suffisent à piloter n'importe quel front sans jamais lire l'intérieur du moteur.

### Application directe : la liquidation avant faillite

Au TP2, la faillite est **immédiate** dès qu'une dette dépasse les liquidités. La vraie règle laisse d'abord le joueur se renflouer : revendre ses constructions, hypothéquer ses biens, et ne couler **que** s'il ne peut toujours pas payer une fois tout liquidé. Or « que vendre, qu'hypothéquer » est précisément une **décision** — donc du ressort de ce modèle interactif.

Deux niveaux, au choix :

- **Liquidation interactive** — quand une dette dépasse les liquidités, le moteur entre dans une phase dédiée (`AWAITING_LIQUIDATION` par exemple) et `getAvailableActions()` ne propose que `SELL_HOUSE` / `MORTGAGE` / `DECLARE_BANKRUPTCY`, jusqu'à ce que le joueur ait réuni la somme (il paie alors et le tour reprend) ou renonce (faillite). C'est la version fidèle et la vraie démonstration de l'intérêt du modèle.
- **Liquidation automatique** (repli plus simple) — une méthode `Game::raiseFunds(Player, int): bool` qui revend puis hypothèque *à la place* du joueur jusqu'à atteindre le montant, appelée dans le `catch` de dette avant de se résoudre à `declareBankruptcy`. Pas de décision, mais la règle « on ne coule qu'après avoir tout liquidé » est respectée.

Dans les deux cas, `declareBankruptcy()` (écrit au TP2 point 8) reste le **dernier** recours, une fois la liquidation épuisée.

### Attendu

- ✅ `playTurn()` fonctionne toujours (rien de cassé), idéalement réécrit pour s'appuyer sur la nouvelle API ;
- ✅ `getCurrentPhase()` / `getAvailableActions()` / `endTurn()` sur `Game` ;
- ✅ `getAvailableActions()` ne propose que des actions réellement légales dans l'état courant ;
- ✅ aucune logique de règle dupliquée : `getAvailableActions()` lit l'état, les méthodes d'action gardent leurs vérifications ;
- ✅ une petite boucle interactive (même en console, avec `readline()`) démontrant qu'on pilote une partie coup par coup.

---

# Bloc B — Compléter et enrichir les règles

## 3. Loyers spéciaux des cartes « gare/compagnie la plus proche »

### Rappel

Au TP2, les cartes `NEAREST_STATION` / `NEAREST_UTILITY` se contentaient d'avancer le joueur, qui payait ensuite le loyer **normal**. Les vraies cartes imposent un loyer **spécial** :

- gare la plus proche : le joueur paie **le double** du loyer de gare normal ;
- compagnie la plus proche : le joueur **relance les dés** et paie **10×** le résultat, quel que soit le nombre de compagnies possédées.

### Ce qu'il faut faire

Le défi : `Station`/`Company::applyEffect()` ne savent pas *comment* le joueur est arrivé (par les dés, ou poussé par une carte). Réfléchissez à comment transmettre cette information — par exemple un indicateur temporaire sur `Game` (« le prochain atterrissage vient d'une carte X »), posé par la carte avant de déclencher `landOn()` et consommé par `applyEffect()`, plutôt que de dupliquer la logique de loyer dans `Card`.

C'est une vraie question de conception. Elle reste **optionnelle** dans les attendus.

### Attendu (optionnel)

- ✅ la carte « gare la plus proche » fait payer le double du loyer de gare ;
- ✅ la carte « compagnie la plus proche » fait payer 10× un lancer de dés ;
- ✅ aucune duplication de la logique de loyer entre `Card` et `Station`/`Company`.

---

## 4. Échanges de propriétés entre joueurs

### Pourquoi

Sans échange, dans une partie à plusieurs joueurs qui achètent au hasard, les cases d'un même groupe de couleur finissent presque toujours dispersées entre plusieurs propriétaires. Or on ne construit que sur un **groupe complet** : sans monopole, personne ne bâtit, les loyers restent dérisoires face aux +200 de la case Départ, et **la partie ne se termine jamais**. L'échange est le mécanisme qui permet aux joueurs de **regrouper** les cases et de débloquer les constructions — c'est une vraie règle du Monopoly, et accessoirement la solution au problème de parties interminables.

### La règle

Deux joueurs se mettent d'accord pour échanger un ensemble de **biens** (`Property`, `Station`, `Company`) et/ou une **somme d'argent**, dans les deux sens. Contraintes officielles :

- on ne peut échanger que des biens qu'on **possède** ;
- un bien portant des **constructions** ne peut pas être échangé : il faut d'abord revendre les maisons/hôtels de tout son groupe (`sellHouse`, TP2 point 5) ;
- un bien **hypothéqué** peut être échangé, mais l'acquéreur hérite de l'hypothèque (à lui de la lever ensuite via `unmortgage`, avec l'intérêt) — à vous de décider si vous gérez ce cas ou si vous l'interdisez pour rester simple.

### Ce qui est imposé

Une méthode sur `Game`, par exemple :

```php
public function trade(
    Player $a,
    Player $b,
    array $biensDeA,      // Mortgageable[] cédés par A à B
    array $biensDeB,      // Mortgageable[] cédés par B à A
    int $argentDeAversB = 0
): void
```

Elle doit, **avant de rien transférer**, tout **valider** (chaque bien appartient bien au bon joueur ; aucun bien construit ; l'argent est disponible) et lever une `InvalidPlayerActionException` sinon — un échange est **atomique** : soit tout passe, soit rien. Ne modifiez l'état qu'une fois toutes les vérifications passées, pour ne pas laisser un échange à moitié fait.

Le transfert lui-même réutilise ce que vous avez déjà : `setOwner()` pour changer de propriétaire, `addMoney()`/`removeMoney()` pour la soulte. Aucune nouvelle logique de possession à réécrire.

### Le lien avec le reste

- **Observer (bloc A)** : émettez un événement `trade_completed` (avec le détail dans le `context`).
- **Modèle interactif (bloc A)** : proposer/accepter un échange est une **négociation** entre deux joueurs — l'action `PROPOSE_TRADE` a sa place dans `getAvailableActions()`. Le moteur, lui, ne fait qu'**exécuter** un échange déjà accepté ; il n'a pas à décider qui propose quoi.

### Attendu

- ✅ `Game::trade()` valide entièrement avant de transférer (atomique) ;
- ✅ refus d'échanger un bien non possédé, ou portant des constructions ;
- ✅ après l'échange, un joueur qui réunit un groupe complet peut immédiatement construire ;
- ✅ aucune duplication : le transfert s'appuie sur `setOwner`/`addMoney`/`removeMoney` existants.

---

## 5. Règles optionnelles activables

### Problème

Certaines règles sont des **variantes** : le « jackpot » du Parc Gratuit (règle maison très répandue mais **non officielle**), ou le rachat de propriété façon *Business Tour*. Les coder « en dur » dans `Game` obligerait à modifier le moteur pour changer une variante. On veut pouvoir dire, à la création de la partie : « cette partie joue avec telle variante, celle-là non ».

### Ce qui est imposé : un objet de configuration

Fichier :

```
src/Config/GameRules.php
```

Une classe (ou un enum de flags, à vous) qui centralise les options activables. Au minimum :

```php
private bool $freeParkingJackpot = false;
private bool $propertyBuyout = false;

public function __construct(bool $freeParkingJackpot = false, bool $propertyBuyout = false)
public function isFreeParkingJackpotEnabled(): bool
public function isPropertyBuyoutEnabled(): bool
```

`Game::__construct()` accepte un `GameRules` **optionnel** (par défaut : tout désactivé, donc règles officielles) :

```php
public function __construct(array $playerNames, ?GameRules $rules = null)
```

Le TP1 imposait `__construct(array $playerNames)`. Ajouter ici un **paramètre optionnel en fin de signature** est une extension **autorisée** — exactement la convention déjà appliquée au TP2 pour étendre `Property::__construct` : les paramètres imposés ne bougent pas, aucun appel existant (`new Game([...])`) n'est cassé. Si vous préférez ne pas toucher du tout au constructeur, une alternative propre est un setter dédié (`Game::setRules(GameRules $rules): void`) appelé après la création.

Ainsi le comportement par défaut reste **strictement officiel** ; les variantes ne s'activent que sur demande explicite.

### 5.1 — Le Parc Gratuit « jackpot »

Règle maison : toutes les sommes payées à la banque (taxes, amendes…) vont dans une **cagnotte** au centre du plateau ; le joueur qui s'arrête sur le Parc Gratuit **récupère toute la cagnotte**, qui repart à zéro.

Ce qu'il faut faire :

- une cagnotte sur `Game` (`private int $freeParkingPot = 0;`) avec de quoi l'alimenter et la vider ;
- `FreeParking::applyEffect()` : **si** la règle est activée (`GameRules`), verser la cagnotte au joueur et la remettre à 0 ; sinon, comportement officiel (rien).

**La belle façon de l'alimenter : via l'Observer.** C'est ici que le bloc A paie. Plutôt que de coupler `Tax` (et les amendes de cartes) à la cagnotte de `Game`, **écrivez un observateur** :

```
src/Observer/FreeParkingObserver.php
```

Il écoute les événements `tax_paid` (et éventuellement `money_lost`) et remplit la cagnotte — **sans que `Tax` ni `Card` ne connaissent l'existence du jackpot**. On ne l'ajoute à la partie que si la règle est activée. Réfléchissez : comment cet observateur accède-t-il à la cagnotte ? (Il peut recevoir le `Game` dans son constructeur, ou `Game` peut exposer `addToFreeParkingPot(int)`.) L'important : `Tax` reste ignorante du jackpot, exactement comme le veut l'Observer.

### 5.2 — Le rachat de propriété (variante « Business Tour »)

Règle popularisée par *Business Tour* : quand un joueur s'arrête sur une propriété **adverse**, il paie d'abord le loyer **normalement**, puis a la **possibilité de racheter** cette propriété à son propriétaire. Le propriétaire encaisse le prix de rachat et perd la case ; l'acheteur en devient propriétaire.

Ce qu'il faut faire :

- le loyer se paie **comme aujourd'hui** (via `applyEffect`, inchangé) — le rachat est une action **séparée**, proposée *après* l'atterrissage ;
- une méthode `Game`, par exemple :

  ```php
  public function buyoutTile(Player $acheteur, Mortgageable $bien): void
  ```

  qui valide (règle activée, le bien a un propriétaire **différent** de l'acheteur, l'acheteur a les fonds), débite l'acheteur du **prix de rachat**, crédite l'ancien propriétaire, puis transfère via `setOwner()`. Comme toute action, elle lève `InvalidPlayerActionException` si les conditions ne sont pas réunies (dont : la règle est désactivée).

Décisions de conception à trancher (assumez-les) :

- **le prix de rachat.** Le prix d'achat brut rendrait le rachat trop facile et déstabiliserait le jeu. Une base raisonnable : un **multiple** du prix (par ex. 2×), éventuellement porté par `GameRules`. À vous.
- **les cases construites / hypothéquées** : rachat interdit tant qu'il y a des constructions ? l'hypothèque est-elle transférée ? Le plus simple est de n'autoriser le rachat que sur une case **nue et non hypothéquée** ; documentez votre choix.

**Lien interactif (bloc A) :** le rachat est une **décision** → l'action `BUYOUT_TILE` apparaît dans `getAvailableActions()` en phase `AWAITING_ACTION`, uniquement quand le joueur est sur une case adverse rachetable **et** que la règle est activée. Émettez aussi un événement Observer (`tile_bought_out`).

### Autres variantes possibles (facultatif)

Si vous voulez enrichir `GameRules` : loyer non perçu quand le propriétaire est en prison, enchères sur refus d'achat, salaire de Départ configurable… Une option = un flag dans `GameRules` + une lecture au bon endroit. N'en abusez pas : le Parc Gratuit et le rachat suffisent à démontrer le mécanisme.

### Attendu

- ✅ `GameRules` centralise les options ; par défaut, la partie joue les **règles officielles** ;
- ✅ le jackpot du Parc Gratuit fonctionne quand il est activé, ne fait rien quand il ne l'est pas ;
- ✅ `Tax` et `Card` **ne connaissent pas** la cagnotte : elle est alimentée via un observateur ;
- ✅ le rachat de propriété fonctionne quand il est activé (loyer payé d'abord, puis transfert contre paiement), et est refusé quand il ne l'est pas ;
- ✅ activer/désactiver une règle ne demande **aucune** modification du moteur, juste un `GameRules` différent à la création.

---

# Bloc C — Industrialiser

## 6. Autoloader (Composer / PSR-4)

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

## 7. Tests automatisés

### Problème

Vous avez validé chaque mécanique avec des scripts jetables. Un projet fini garde ses tests : ils documentent le comportement attendu et détectent les régressions à chaque modification.

### Ce qu'il faut faire

- Installer **PHPUnit** (via Composer — d'où l'intérêt d'avoir fait le point 6 avant).
- Un dossier `tests/`, une classe de test par classe métier importante (`SquareTest`, `PropertyTest`, `BoardTest`, `GameTest`…).
- Convertissez vos scénarios de scratchpad en vrais tests : loyer selon le niveau de construction, loyer doublé du monopole nu, comptage des gares, cycle de la carte de prison, hypothèque, faillite, échange, etc.

Conseil : privilégiez des tests **déterministes**. Pour ce qui dépend du hasard (dés, tirage de cartes), injectez ou fixez la source d'aléa, ou testez les méthodes de calcul isolément (comme `Property::getRent()`), plutôt qu'une partie entière.

### Attendu

- ✅ une suite PHPUnit qui passe (`vendor/bin/phpunit`) ;
- ✅ au moins les mécaniques clés des TP2/TP3 couvertes ;
- ✅ des tests déterministes, indépendants du hasard.

---

## Ce qui reste interdit même en finalisation

- casser la Factory (`new Property(...)` hors `TileFactory`) ;
- réintroduire un gros `if/else`/`match` sur le type de case dans `Game` ;
- mettre de la logique métier dans un observateur (il **informe**, il ne décide pas) ;
- coupler `Tax`/`Card` à la cagnotte du Parc Gratuit (elle passe par l'Observer) ;
- dupliquer la vérification des fonds ailleurs que dans `Player::removeMoney()`.
