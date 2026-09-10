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

Valeurs proposées (libre à vous d'en ajouter) :

```php
case GAIN_MONEY;
case LOSE_MONEY;
case MOVE_TO;      // value = index de la case cible
case GO_TO_JAIL;
```

`Card::apply()` doit utiliser un `match` sur `$this->effectType` pour appliquer le bon effet — même principe de Template Method/Strategy que dans `Tile`.

### Ce qu'il faut faire dans `Chance` et `CommunityChest`

- Une liste de `Card` (propriété privée, remplie dans le constructeur ou via une méthode dédiée).
- `applyEffect()` doit tirer une carte au hasard dans la liste (`array_rand()` ou équivalent) et appeler `Card::apply($player, $game)`.

### Attendu

- ✅ au moins 4 cartes différentes par type de case, avec des effets variés ;
- ✅ le tirage est aléatoire à chaque atterrissage.

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

---

## 6. Hypothèques

### Règle

Un propriétaire peut hypothéquer une case qu'il possède (`Property`, `Station`, `Company`) pour récupérer de l'argent immédiatement (exemple : la moitié du prix). Une case hypothéquée ne rapporte plus de loyer tant qu'elle n'est pas "dé-hypothéquée" (remboursement avec intérêt).

### Ce qu'il faut faire

- Une propriété `mortgaged` (booléen) sur `Property`, `Station`, `Company`, avec getter/setter.
- `applyEffect()` de ces trois classes doit vérifier ce statut avant d'appliquer un loyer.

### Attendu

- ✅ une case hypothéquée ne fait payer aucun loyer ;
- ✅ possibilité de rembourser pour la réactiver.

---

## 7. Faillite et fin de partie

### Règle

Si un joueur ne peut pas payer une dette (`InsufficientFundsException` levée quelque part dans le flot), il est déclaré en faillite : ses biens repassent sans propriétaire (ou au créancier, à vous de choisir), et il est retiré de la partie. La partie se termine quand il ne reste qu'un seul joueur.

### Ce qu'il faut faire

- Attraper `InsufficientFundsException` au bon endroit (probablement dans `Game::playTurn()` ou `Game::buyCurrentTile()`), plutôt que de la laisser remonter jusqu'à `index.php` sans traitement.
- Une méthode pour retirer un joueur de `$this->players`.
- Une méthode `isGameOver(): bool` ou équivalent sur `Game`, et une méthode pour connaître le vainqueur.

### Attendu

- ✅ un joueur en faillite est retiré proprement (pas juste ignoré) ;
- ✅ la partie sait se terminer et désigner un gagnant.

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
```