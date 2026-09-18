<?php

class Game {
    private Board $board;
    private array $players = [];

    private int $currentPlayerIndex = 0;

    private Dice $dice;
    private TileFactory $tileFactory;

    private int $lastDiceTotal = 0;
    private array $observers = [];

    public const JAIL_BAIL = 50;


    private TurnPhase $phase = TurnPhase::AWAITING_ROLL;
    private int $doublesCount = 0;

    // liquidation debt
    private int $pendingDebt = 0;
    private ?Player $pendingCreditor = null;

    // set by a card, consumed by Station/Company applyEffect
    private ?CardEffectType $pendingCardArrival = null;

    private GameRules $rules;

    // additional content
    private int $freeParkingPot = 0;



    public function __construct(array $playerNames, ?GameRules $rules = null) {
        foreach($playerNames as $playerName){
            $this->players[] = new Player($playerName);
        }
        $this->board = new Board();
        $this->dice = new Dice(6);
        $this->tileFactory = new TileFactory();
        $this->rules = $rules ?? new GameRules();
    }

    public function start(): void {
        $this->setupBoard();
        $this->currentPlayerIndex=0;
    }

    public function getBoard(): Board {
        return $this->board;
    }

    public function getCurrentPlayer(): Player {
        return $this->players[$this->currentPlayerIndex];
    }

    public function playJailedTurn(): void {
        $playingPlayer = $this->getCurrentPlayer();

        $dice = $this->dice->rollTwo();
        $this->lastDiceTotal=array_sum($dice);

        if ($dice[0] === $dice[1]) {

            $this->emit(GameEventType::JAIL_ESCAPED_BY_DOUBLE, ['player' => $playingPlayer->getName()]);

            $playingPlayer->setInJail(false);
            $playingPlayer->resetTurnsInJail();

            try {
                $this->resolveMovement($playingPlayer, array_sum($dice));
            } catch (InsufficientFundsException $e) {
                $this->declareBankruptcy($playingPlayer);
            } finally {
                $this->nextPlayer();
            }

            return;
        }

        $playingPlayer->addTurnsInJail();

        if ($playingPlayer->getTurnsInJail() >= 3) {
            try {
                $playingPlayer->removeMoney(self::JAIL_BAIL);
                $playingPlayer->setInJail(false);
                $playingPlayer->resetTurnsInJail();

                $this->emit(GameEventType::JAIL_FORCED_RELEASE, ['player' => $playingPlayer->getName()]);
                $this->emit(GameEventType::JAIL_PAID, ['player' => $playingPlayer->getName(), 'amount' => self::JAIL_BAIL]);

                $this->resolveMovement($playingPlayer, array_sum($dice));
            } catch (InsufficientFundsException $e) {
                $this->declareBankruptcy($playingPlayer);
            } finally {
                $this->nextPlayer();
            }

            return;
        }

        $this->emit(GameEventType::JAIL_TURN_SKIPPED, ['player' => $playingPlayer->getName()]);

        $this->nextPlayer();
    }

    public function payToLeaveJail(): void {
        $playingPlayer = $this->getCurrentPlayer();

        if(!$playingPlayer->isInJail()){
            throw new InvalidPlayerActionException("Le joueur n'est pas en prison.");
        }

        try {
            $playingPlayer->removeMoney(self::JAIL_BAIL);
        } catch (InsufficientFundsException $e) {
            $this->declareBankruptcy($playingPlayer);
            $this->nextPlayer();
            return;
        }

        $playingPlayer->setInJail(false);
        $playingPlayer->resetTurnsInJail();

        $this->emit(GameEventType::JAIL_PAID, ['player' => $playingPlayer->getName(), 'amount' => self::JAIL_BAIL]);

        $this->playTurn();
    }

    public function useJailCard(): void {
        $playingPlayer = $this->getCurrentPlayer();

        if(!$playingPlayer->isInJail()){
            throw new InvalidPlayerActionException("Le joueur n'est pas en prison.");
        }

        if(!$playingPlayer->hasGetOutOfJailCard()){
            throw new InvalidPlayerActionException("Le joueur n'a plus de carte de sortie de prison.");
        }

        $playingPlayer->useGetOutOfJailCard();

        $this->emit(GameEventType::JAIL_CARD_USED, ['player' => $playingPlayer->getName()]);

        $playingPlayer->setInJail(false);
        $playingPlayer->resetTurnsInJail();

        $this->playTurn();
    }

    public function playTurn(): void {
        $playingPlayer = $this->getCurrentPlayer();
        
        if($playingPlayer->isInJail()) {
            $this->playJailedTurn();
            return;
        }
        $this->emit(GameEventType::TURN_STARTED, ['player' => $playingPlayer->getName()]);

        try {
            $doublesCount = 0;

            do {
                $dice = $this->dice->rollTwo();
                $this->emit(GameEventType::DICE_ROLLED, ['dice1' => $dice[0], 'dice2' => $dice[1]]);

                $isDouble = $dice[0] === $dice[1];
                $step = $this->lastDiceTotal = array_sum($dice);

                if ($isDouble) {
                    $doublesCount++;
                    $this->emit(GameEventType::DOUBLE_ROLLED);
                }

                if ($doublesCount === 3) {
                    $goToJailTile = $this->board->findTileByType(TileType::GO_TO_JAIL);
                    if ($goToJailTile === null) {
                        throw new MonopolyException("Aucune case GoToJail trouvée sur le plateau.");
                    }
                    $goToJailTile->landOn($playingPlayer, $this);
                    $this->emit(GameEventType::THREE_DOUBLES);
                    break;
                }

                $this->resolveMovement($playingPlayer, $step);

                if ($playingPlayer->isInJail()) {
                    break;
                }

            } while ($isDouble);
        } catch (InsufficientFundsException $e) {
            $this->declareBankruptcy($playingPlayer);
        } finally {
            $this->nextPlayer();
            $this->emit(GameEventType::TURN_ENDED, ['player' => $playingPlayer->getName()]);
        }
    }

    public function resolveMovement(Player $player, int $step): void {
        $actualPosition = $player->getPosition();

        // player who passes the Go square receive +200, but if he stops on this square:
        // Go::applyEffect() applies and adds another +200
        if (($actualPosition->getIndex() + $step) >= $this->board->getBoardSize()) {
            $player->addMoney(200);
            $this->emit(GameEventType::PASSED_GO, ['player' => $player->getName(),'amount' => 200]);
        }

        $squareToLand = $actualPosition->next($step);
        $player->setPosition($squareToLand);
        $this->emit(GameEventType::PLAYER_MOVED, ['player' => $player->getName(),'position' => $squareToLand->toKey()]);
        

        $tile = $this->board->getTileAt($squareToLand);
        if ($tile === null) {
            throw new MonopolyException("Aucune case trouvée à la position {$squareToLand->toKey()}.");
        }

        $this->emit(GameEventType::LANDED_ON_TILE, ['player' => $player->getName(), 'tile' => $tile->getName()]);
        $tile->landOn($player, $this);
    }

    public function buyCurrentTile(Player $player): void {
        $tile = $this->board->getTileAt($player->getPosition());

        if($tile === null || !$tile->isOwnable()) {
            throw new TileNotOwnableException("Cette case n'est pas achetable.");
        }
        if($tile instanceof Property || $tile instanceof Station || $tile instanceof Company) {
            if($tile->isOwned()) {
                throw new AlreadyOwnedException("La case {$tile->getName()} est déja détenu par un joueur.");
            }
            $player->removeMoney($tile->getPrice());
            $tile->setOwner($player);
            $this->emit(GameEventType::TILE_PURCHASED, ['player' => $player->getName(),'tile' => $tile->getName(),'price' => $tile->getPrice()]);
        }
    }

    private function setupBoard(): void {
        $definitions = [
            [TileType::GO, 'Départ', 0, []],
            [TileType::PROPERTY, 'Boulevard de Belleville', 1, ['colorGroup' => ColorGroup::BROWN, 'price' => 60, 'rents' => [2, 10, 30, 90, 160, 250], 'housePrice' => 50]],
            [TileType::COMMUNITY_CHEST, 'Caisse de Communauté', 2, []],
            [TileType::PROPERTY, 'Rue Lecourbe', 3, ['colorGroup' => ColorGroup::BROWN, 'price' => 60, 'rents' => [4, 20, 60, 180, 320, 450], 'housePrice' => 50]],
            [TileType::TAX, 'Impôts sur le revenu', 4, ['amount' => 200]],
            [TileType::STATION, 'Gare Montparnasse', 5, ['price' => 200]],
            [TileType::PROPERTY, 'Rue de Vaugirard', 6, ['colorGroup' => ColorGroup::LIGHT_BLUE, 'price' => 100, 'rents' => [6, 30, 90, 270, 400, 550], 'housePrice' => 50]],
            [TileType::CHANCE, 'Chance', 7, []],
            [TileType::PROPERTY, 'Rue de Courcelles', 8, ['colorGroup' => ColorGroup::LIGHT_BLUE, 'price' => 100, 'rents' => [6, 30, 90, 270, 400, 550], 'housePrice' => 50]],
            [TileType::PROPERTY, 'Avenue de la République', 9, ['colorGroup' => ColorGroup::LIGHT_BLUE, 'price' => 120, 'rents' => [8, 40, 100, 300, 450, 600], 'housePrice' => 50]],
            [TileType::JAIL, 'Prison / Simple visite', 10, []],
            [TileType::PROPERTY, 'Boulevard de la Villette', 11, ['colorGroup' => ColorGroup::PINK, 'price' => 140, 'rents' => [10, 50, 150, 450, 625, 750], 'housePrice' => 100]],
            [TileType::COMPANY, "Compagnie de Distribution d'Électricité", 12, ['price' => 150]],
            [TileType::PROPERTY, 'Avenue de Neuilly', 13, ['colorGroup' => ColorGroup::PINK, 'price' => 140, 'rents' => [10, 50, 150, 450, 625, 750], 'housePrice' => 100]],
            [TileType::PROPERTY, 'Rue de Paradis', 14, ['colorGroup' => ColorGroup::PINK, 'price' => 160, 'rents' => [12, 60, 180, 500, 700, 900], 'housePrice' => 100]],
            [TileType::STATION, 'Gare de Lyon', 15, ['price' => 200]],
            [TileType::PROPERTY, 'Avenue Mozart', 16, ['colorGroup' => ColorGroup::ORANGE, 'price' => 180, 'rents' => [14, 70, 200, 550, 750, 950], 'housePrice' => 100]],
            [TileType::COMMUNITY_CHEST, 'Caisse de Communauté', 17, []],
            [TileType::PROPERTY, 'Boulevard Saint-Michel', 18, ['colorGroup' => ColorGroup::ORANGE, 'price' => 180, 'rents' => [14, 70, 200, 550, 750, 950], 'housePrice' => 100]],
            [TileType::PROPERTY, 'Place Pigalle', 19, ['colorGroup' => ColorGroup::ORANGE, 'price' => 200, 'rents' => [16, 80, 220, 600, 800, 1000], 'housePrice' => 100]],
            [TileType::FREE_PARKING, 'Parc Gratuit', 20, []],
            [TileType::PROPERTY, 'Avenue Matignon', 21, ['colorGroup' => ColorGroup::RED, 'price' => 220, 'rents' => [18, 90, 250, 700, 875, 1050], 'housePrice' => 150]],
            [TileType::CHANCE, 'Chance', 22, []],
            [TileType::PROPERTY, 'Boulevard Malesherbes', 23, ['colorGroup' => ColorGroup::RED, 'price' => 220, 'rents' => [18, 90, 250, 700, 875, 1050], 'housePrice' => 150]],
            [TileType::PROPERTY, 'Avenue Henri-Martin', 24, ['colorGroup' => ColorGroup::RED, 'price' => 240, 'rents' => [20, 100, 300, 750, 925, 1100], 'housePrice' => 150]],
            [TileType::STATION, 'Gare du Nord', 25, ['price' => 200]],
            [TileType::PROPERTY, 'Faubourg Saint-Honoré', 26, ['colorGroup' => ColorGroup::YELLOW, 'price' => 260, 'rents' => [22, 110, 330, 800, 975, 1150], 'housePrice' => 150]],
            [TileType::PROPERTY, 'Place de la Bourse', 27, ['colorGroup' => ColorGroup::YELLOW, 'price' => 260, 'rents' => [22, 110, 330, 800, 975, 1150], 'housePrice' => 150]],
            [TileType::COMPANY, 'Compagnie des Eaux', 28, ['price' => 150]],
            [TileType::PROPERTY, 'Rue La Fayette', 29, ['colorGroup' => ColorGroup::YELLOW, 'price' => 280, 'rents' => [24, 120, 360, 850, 1025, 1200], 'housePrice' => 150]],
            [TileType::GO_TO_JAIL, 'Allez en Prison', 30, []],
            [TileType::PROPERTY, 'Avenue de Breteuil', 31, ['colorGroup' => ColorGroup::GREEN, 'price' => 300, 'rents' => [26, 130, 390, 900, 1100, 1275], 'housePrice' => 200]],
            [TileType::PROPERTY, 'Avenue Foch', 32, ['colorGroup' => ColorGroup::GREEN, 'price' => 300, 'rents' => [26, 130, 390, 900, 1100, 1275], 'housePrice' => 200]],
            [TileType::COMMUNITY_CHEST, 'Caisse de Communauté', 33, []],
            [TileType::PROPERTY, 'Boulevard des Capucines', 34, ['colorGroup' => ColorGroup::GREEN, 'price' => 320, 'rents' => [28, 150, 450, 1000, 1200, 1400], 'housePrice' => 200]],
            [TileType::STATION, 'Gare Saint-Lazare', 35, ['price' => 200]],
            [TileType::CHANCE, 'Chance', 36, []],
            [TileType::PROPERTY, 'Avenue des Champs-Élysées', 37, ['colorGroup' => ColorGroup::DARK_BLUE, 'price' => 350, 'rents' => [35, 175, 500, 1100, 1300, 1500], 'housePrice' => 200]],
            [TileType::TAX, 'Taxe de luxe', 38, ['amount' => 100]],
            [TileType::PROPERTY, 'Rue de la Paix', 39, ['colorGroup' => ColorGroup::DARK_BLUE, 'price' => 400, 'rents' => [50, 200, 600, 1400, 1700, 2000], 'housePrice' => 200]],
        ];

        foreach ($definitions as [$type, $name, $index, $options]) {
            $this->board->placeTile(
                $this->tileFactory->create($type, $name, new Square($index), $options)
            );
        }
    }

    private function nextPlayer(): void {
        $nbPlayers=count($this->players);
        $this->currentPlayerIndex= ($this->currentPlayerIndex+1) % $nbPlayers;
    }

    public function getPlayers(): array {
        return $this->players;
    }

    public function buildHouse(Property $property): void {
        $owner = $property->getOwner();
        if($owner === null) throw new InvalidPlayerActionException("La case n'a pas de propriétaire.");
        if($property->getBuildLevel() === 5) throw new InvalidPlayerActionException("Hôtel déjà présent.");

        if ($this->rules->isBusinessTourEnabled()) {
            // build only on the tile the owner stands on; no group rules
            if ($owner->getPosition()->getIndex() !== $property->getPosition()->getIndex()) {
                throw new InvalidPlayerActionException("Business Tour : on ne construit que sur la case où l'on est.");
            }
        } else {
            if (!$this->board->ownsWholeGroup($owner, $property->getColorGroup())) {
                throw new InvalidPlayerActionException("Le propriétaire ne possède pas tout le groupe.");
            }
            if ($property->getBuildLevel() > $this->board->minBuildLevelInGroup($property->getColorGroup())) {
                throw new InvalidPlayerActionException("La case n'est pas le minimum du groupe.");
            }
            if ($this->board->groupHasMortgage($property->getColorGroup())) {
                throw new InvalidPlayerActionException("Impossible de construire : une case du groupe est hypothéquée.");
            }
        }
        $owner->removeMoney($property->getHousePrice());
        $property->setBuildLevel($property->getBuildLevel() + 1);
        if($property->getBuildLevel() === 5) $this->emit(GameEventType::HOTEL_BUILT, ['player' => $owner->getName(), 'tile' => $property->getName()]);
        else $this->emit(GameEventType::HOUSE_BUILT, ['player' => $owner->getName(), 'tile' => $property->getName()]);
    }

    public function sellHouse(Property $property): void {
        $owner = $property->getOwner();
        if($owner === null) throw new InvalidPlayerActionException("La case n'a pas de propriétaire.");
        if($property->getBuildLevel() === 0) throw new InvalidPlayerActionException("Aucune construction à revendre.");

        if (!$this->rules->isBusinessTourEnabled()) {
            if (!$this->board->ownsWholeGroup($owner, $property->getColorGroup())) throw new InvalidPlayerActionException("Le propriétaire ne possède pas tout le groupe.");
            if ($property->getBuildLevel() < $this->board->maxBuildLevelInGroup($property->getColorGroup())) {
                throw new InvalidPlayerActionException("La case n'est pas le maximum du groupe.");
            }
        }
        $owner->addMoney(intdiv($property->getHousePrice(), 2));
        $property->setBuildLevel($property->getBuildLevel() - 1);
        $this->emit(GameEventType::HOUSE_SOLD, ['player' => $owner->getName(), 'tile' => $property->getName(), 'price' => intdiv($property->getHousePrice(), 2)]);

        if ($this->phase === TurnPhase::AWAITING_LIQUIDATION) $this->tryAutoSettle();
    }

    public function getLastDiceTotal(): int {
        return $this->lastDiceTotal;
    }

    public function mortgage(Mortgageable $tile): void {
        if($tile->isMortgaged()) throw new InvalidPlayerActionException("La case est déjà en hypothèque.");
        $owner=$tile->getOwner();
        if($owner===null){
            throw new InvalidPlayerActionException("La case n'a pas de propriétaire.");
        }
        if ($tile instanceof Property && $tile->getBuildLevel() > 0) {
            throw new InvalidPlayerActionException("On n'hypothèque pas une propriété construite !");
        }
        $owner->addMoney(intdiv($tile->getPrice(), 2));
        $tile->setMortgaged(true);
        $this->emit(GameEventType::TILE_MORTGAGED, ['player' => $owner->getName(), 'tile' => $tile->getName()]);

        if ($this->phase === TurnPhase::AWAITING_LIQUIDATION) $this->tryAutoSettle();
    }

    public function unmortgage(Mortgageable $tile): void {
        if(!$tile->isMortgaged()) throw new InvalidPlayerActionException("La case n'est pas en hypothèque.");
        $owner=$tile->getOwner();
        if($owner===null){
            throw new InvalidPlayerActionException("La case n'a pas de propriétaire.");
        }
        if ($tile instanceof Property && $tile->getBuildLevel() > 0) {
            throw new InvalidPlayerActionException("On n'hypothèque pas une propriété construite !");
        }
        $value = intdiv($tile->getPrice(), 2);
        $cost = $value + intdiv($value, 10);
        $owner->removeMoney($cost);
        $tile->setMortgaged(false);
        $this->emit(GameEventType::TILE_UNMORTGAGED, ['player' => $owner->getName(), 'tile' => $tile->getName()]);
    }

    private function releaseAssets(Player $player): void {
        foreach ($this->board->getTiles() as $tile) {
            if ($tile instanceof Mortgageable && $tile->getOwner() === $player) {
                $tile->setOwner(null);
                $tile->setMortgaged(false);
                if($tile instanceof Property){
                    $tile->setBuildLevel(0);
                }
            }
        }
    }

    public function removePlayer(Player $player): void {
        $index = array_search($player, $this->players, true);
        if ($index === false) return;

        array_splice($this->players, $index, 1);

        if ($index <= $this->currentPlayerIndex) {
            $this->currentPlayerIndex--;
        }
    }

    public function declareBankruptcy(Player $player): void {
        $this->emit(GameEventType::PLAYER_BANKRUPT, ['player' => $player->getName()]);
        $this->releaseAssets($player);
        $this->removePlayer($player);
        if($this->isGameOver()) $this->emit(GameEventType::GAME_OVER);
    }

    public function isGameOver(): bool {
        return count($this->players)<=1;
    }

    public function getWinner(): ?Player {
        return $this->isGameOver() ? $this->players[0] : null;
    }

    public function addObserver(GameObserver $observer): void {
        $this->observers[]=$observer;
    }

    public function removeObserver(GameObserver $observer): void {
        $index = array_search($observer, $this->observers, true);
        if ($index !== false) {
            unset($this->observers[$index]);
        }
    }

    private function notify(GameEvent $event): void {
        foreach($this->observers as $observer){
            $observer->onEvent($event);
        }
    }

    public function emit(GameEventType $type, array $context = []): void {
        $this->notify(new GameEvent($type, $context));
    }

    
    /*
    * Modèle de tour interactif
    */

    public function getCurrentPhase(): TurnPhase {
        return $this->phase;
    }

    public function getAvailableActions(): array {
        $player = $this->getCurrentPlayer();

        return match ($this->phase) {
            TurnPhase::AWAITING_ROLL        => $this->actionsAwaitingRoll($player),
            TurnPhase::AWAITING_ACTION      => $this->actionsAwaitingAction($player),
            TurnPhase::AWAITING_LIQUIDATION => $this->actionsAwaitingLiquidation($player),
            TurnPhase::TURN_OVER            => [], // pass
        };
    }

    private function actionsAwaitingRoll(Player $player): array {
        $right=[];
        if (!$player->isInJail()) {
            $right[] = PlayerAction::ROLL;
        } else {
            $right[] = PlayerAction::ROLL;
            $right[] = PlayerAction::PAY_BAIL;
            if ($player->hasGetOutOfJailCard()) {
                $right[] = PlayerAction::USE_JAIL_CARD;
            }
        }
        return $right;
    }

    private function actionsAwaitingAction(Player $player): array {
        $actions = [PlayerAction::END_TURN];

        $tile=$this->board->getTileAt($player->getPosition());
        if($tile !== null && $tile->isOwnable() && !$tile->isOwned()){
            $actions[]= PlayerAction::BUY_TILE;
        }

        $ownsProperty     = false;
        $ownsMortgageable = false;

        foreach ($this->board->getTiles() as $t) {
            if ($t instanceof Mortgageable && $t->getOwner() === $player) {
                $ownsMortgageable = true;
                if ($t instanceof Property) {
                    $ownsProperty = true;
                }
            }
        }

        if ($this->rules->isBusinessTourEnabled()) {
            // build only on the current tile if owned
            if ($tile instanceof Property && $tile->getOwner() === $player && $tile->getBuildLevel() < 5) {
                $actions[] = PlayerAction::BUILD_HOUSE;
            }
            if ($ownsProperty) $actions[] = PlayerAction::SELL_HOUSE;
            // buyout an opponent's bare, unmortgaged tile
            if ($tile instanceof Mortgageable
                && $tile->getOwner() !== null
                && $tile->getOwner() !== $player
                && !$tile->isMortgaged()
                && !($tile instanceof Property && $tile->getBuildLevel() > 0)) {
                $actions[] = PlayerAction::BUYOUT_TILE;
            }
        } elseif ($ownsProperty) {
            $actions[] = PlayerAction::BUILD_HOUSE;
            $actions[] = PlayerAction::SELL_HOUSE;
        }
        if ($ownsMortgageable) {
            $actions[] = PlayerAction::MORTGAGE;
            $actions[] = PlayerAction::UNMORTGAGE;
        }
        if (count($this->players) > 1) {
            $actions[] = PlayerAction::PROPOSE_TRADE;
        }

        return $actions;
    }

    private function actionsAwaitingLiquidation(Player $player): array {
        $actions = [PlayerAction::DECLARE_BANKRUPTCY];

        $ownsBuilt        = false;
        $ownsMortgageable = false;

        foreach ($this->board->getTiles() as $t) {
            if ($t instanceof Mortgageable && $t->getOwner() === $player && !$t->isMortgaged()) {
                $ownsMortgageable = true;
            }
            if ($t instanceof Property && $t->getOwner() === $player && $t->getBuildLevel() > 0) {
                $ownsBuilt = true;
            }
        }

        if ($ownsBuilt)        $actions[] = PlayerAction::SELL_HOUSE;
        if ($ownsMortgageable) $actions[] = PlayerAction::MORTGAGE;

        return $actions;
    }

    public function endTurn(): void {
        $this->nextPlayer();
        $this->phase=TurnPhase::AWAITING_ROLL;
        $this->doublesCount = 0;
        // TODO emit turn_ended here instead in playTurn
    }

    public function roll(): void {
        if ($this->phase !== TurnPhase::AWAITING_ROLL) {
            throw new InvalidPlayerActionException("Ce n'est pas le moment de lancer les dés.");
        }

        $player = $this->getCurrentPlayer();

        // jail: try dice (3-attempt rule)
        if ($player->isInJail()) {
            $this->rollFromJail($player);
            return;
        }

        $dice = $this->dice->rollTwo();
        $this->emit(GameEventType::DICE_ROLLED, ['dice1' => $dice[0], 'dice2' => $dice[1]]);

        $isDouble = $dice[0] === $dice[1];
        $step = $this->lastDiceTotal = array_sum($dice);

        if ($isDouble) {
            $this->doublesCount++;
            $this->emit(GameEventType::DOUBLE_ROLLED);
        }

        if ($this->doublesCount === 3) {
            $goToJailTile = $this->board->findTileByType(TileType::GO_TO_JAIL);
            if ($goToJailTile === null) {
                throw new MonopolyException("Aucune case GoToJail trouvée sur le plateau.");
            }
            $goToJailTile->landOn($player, $this);
            $this->emit(GameEventType::THREE_DOUBLES);
            $this->phase=TurnPhase::TURN_OVER;
            return;
        }

        try {
            $this->resolveMovement($player, $step);
        } catch (InsufficientFundsException $e) {
            $this->enterLiquidation($e);
            return;
        }

        if (!in_array($player, $this->players, true)) {
            $this->phase = TurnPhase::TURN_OVER;
            return;
        }

        if ($player->isInJail()) {
            $this->phase = TurnPhase::TURN_OVER;
        } elseif ($isDouble) {
            $this->phase = TurnPhase::AWAITING_ROLL;
        } else {
            $this->phase = TurnPhase::AWAITING_ACTION;
        }
    }

    // jail dice roll (3-attempt rule); escaping never grants a bonus turn
    private function rollFromJail(Player $player): void {
        $dice = $this->dice->rollTwo();
        $this->emit(GameEventType::DICE_ROLLED, ['dice1' => $dice[0], 'dice2' => $dice[1]]);
        $this->lastDiceTotal = array_sum($dice);
        $isDouble = $dice[0] === $dice[1];

        if ($isDouble) {
            $this->emit(GameEventType::JAIL_ESCAPED_BY_DOUBLE, ['player' => $player->getName()]);
            $player->setInJail(false);
            $player->resetTurnsInJail();
            try {
                $this->resolveMovement($player, $this->lastDiceTotal);
            } catch (InsufficientFundsException $e) {
                $this->enterLiquidation($e);
                return;
            }
            $this->phase = TurnPhase::TURN_OVER;
            return;
        }

        $player->addTurnsInJail();

        if ($player->getTurnsInJail() >= 3) {
            // 3rd fail: forced bail
            try {
                $player->removeMoney(self::JAIL_BAIL);
            } catch (InsufficientFundsException $e) {
                // can't pay forced bail: bankrupt
                $this->declareBankruptcy($player);
                $this->phase = TurnPhase::TURN_OVER;
                return;
            }
            $player->setInJail(false);
            $player->resetTurnsInJail();
            $this->emit(GameEventType::JAIL_FORCED_RELEASE, ['player' => $player->getName()]);
            $this->emit(GameEventType::JAIL_PAID, ['player' => $player->getName(), 'amount' => self::JAIL_BAIL]);
            try {
                $this->resolveMovement($player, $this->lastDiceTotal);
            } catch (InsufficientFundsException $e) {
                $this->enterLiquidation($e);
                return;
            }
            $this->phase = TurnPhase::TURN_OVER;
            return;
        }

        // fail, stays in jail
        $this->emit(GameEventType::JAIL_TURN_SKIPPED, ['player' => $player->getName()]);
        $this->phase = TurnPhase::TURN_OVER;
    }

    // voluntary bail; phase stays AWAITING_ROLL
    public function payBail(): void {
        if ($this->phase !== TurnPhase::AWAITING_ROLL) {
            throw new InvalidPlayerActionException("Ce n'est pas le moment de payer la caution.");
        }
        $player = $this->getCurrentPlayer();
        if (!$player->isInJail()) {
            throw new InvalidPlayerActionException("Le joueur n'est pas en prison.");
        }
        if ($player->getMoney() < self::JAIL_BAIL) {
            throw new InvalidPlayerActionException("Fonds insuffisants pour payer la caution.");
        }
        $player->removeMoney(self::JAIL_BAIL);
        $player->setInJail(false);
        $player->resetTurnsInJail();
        $this->emit(GameEventType::JAIL_PAID, ['player' => $player->getName(), 'amount' => self::JAIL_BAIL]);
    }

    // release with card; phase stays AWAITING_ROLL
    public function releaseWithCard(): void {
        if ($this->phase !== TurnPhase::AWAITING_ROLL) {
            throw new InvalidPlayerActionException("Ce n'est pas le moment d'utiliser la carte.");
        }
        $player = $this->getCurrentPlayer();
        if (!$player->isInJail()) {
            throw new InvalidPlayerActionException("Le joueur n'est pas en prison.");
        }
        if (!$player->hasGetOutOfJailCard()) {
            throw new InvalidPlayerActionException("Le joueur n'a pas de carte de sortie de prison.");
        }
        $player->useGetOutOfJailCard();
        $player->setInJail(false);
        $player->resetTurnsInJail();
        $this->emit(GameEventType::JAIL_CARD_USED, ['player' => $player->getName()]);
    }

    // enter liquidation, remember debt
    private function enterLiquidation(InsufficientFundsException $e): void {
        $this->pendingDebt     = $e->getAmount();
        $this->pendingCreditor = $e->getCreditor();
        $this->phase           = TurnPhase::AWAITING_LIQUIDATION;
    }

    // settle debt once affordable, then end turn
    private function tryAutoSettle(): void {
        $player = $this->getCurrentPlayer();
        if ($player->getMoney() < $this->pendingDebt) {
            return;
        }
        $player->removeMoney($this->pendingDebt);
        if ($this->pendingCreditor !== null) {
            $this->pendingCreditor->addMoney($this->pendingDebt);
        }
        $this->emit(GameEventType::MONEY_LOST, ['player' => $player->getName(), 'amount' => $this->pendingDebt]);
        $this->pendingDebt     = 0;
        $this->pendingCreditor = null;
        $this->phase           = TurnPhase::TURN_OVER;
    }

    // give up during liquidation
    public function declareBankruptcyInteractive(): void {
        if ($this->phase !== TurnPhase::AWAITING_LIQUIDATION) {
            throw new InvalidPlayerActionException("Aucune faillite à déclarer hors liquidation.");
        }
        $player = $this->getCurrentPlayer();
        $this->pendingDebt     = 0;
        $this->pendingCreditor = null;
        $this->declareBankruptcy($player);
        $this->phase = TurnPhase::TURN_OVER;
    }

    public function markCardArrival(CardEffectType $type): void {
        $this->pendingCardArrival = $type;
    }

    public function consumeCardArrival(): ?CardEffectType {
        $type = $this->pendingCardArrival;
        $this->pendingCardArrival = null;
        return $type;
    }

    public function rollForCardRent(): int {
        $dice = $this->dice->rollTwo();
        $this->emit(GameEventType::DICE_ROLLED, ['dice1' => $dice[0], 'dice2' => $dice[1]]);
        return array_sum($dice);
    }

    public function getRules(): GameRules {
        return $this->rules;
    }

    public function addToFreeParkingPot(int $amount): void {
        $this->freeParkingPot += $amount;
    }

    public function collectFreeParkingPot(): int {
        $pot = $this->freeParkingPot;
        $this->freeParkingPot = 0;
        return $pot;
    }

    public const BUYOUT_MULTIPLIER = 2;

    // Business Tour: buy an opponent's bare tile (rent already paid on landing)
    public function buyoutTile(Player $acheteur, Mortgageable $bien): void {
        if (!$this->rules->isBusinessTourEnabled()) {
            throw new InvalidPlayerActionException("Le rachat n'est pas activé.");
        }
        $owner = $bien->getOwner();
        if ($owner === null || $owner === $acheteur) {
            throw new InvalidPlayerActionException("Cette case n'appartient pas à un adversaire.");
        }
        if ($bien->isMortgaged() || ($bien instanceof Property && $bien->getBuildLevel() > 0)) {
            throw new InvalidPlayerActionException("Rachat impossible : case construite ou hypothéquée.");
        }
        $prix = self::BUYOUT_MULTIPLIER * $bien->getPrice();
        if ($acheteur->getMoney() < $prix) {
            throw new InvalidPlayerActionException("Fonds insuffisants pour racheter.");
        }
        $acheteur->removeMoney($prix);
        $owner->addMoney($prix);
        $bien->setOwner($acheteur);
        $this->emit(GameEventType::TILE_BOUGHT_OUT, [
            'player' => $acheteur->getName(), 'tile' => $bien->getName(),
            'owner'  => $owner->getName(),    'price' => $prix,
        ]);
    }

    /**
     * Atomic swap of tiles and/or money between two players.
     * @param Mortgageable[] $biensDeA tiles A gives to B
     * @param Mortgageable[] $biensDeB tiles B gives to A
     */
    public function trade(Player $a, Player $b, array $biensDeA, array $biensDeB, int $argentDeAversB = 0): void {
        if ($a === $b) {
            throw new InvalidPlayerActionException("Un joueur ne peut pas échanger avec lui-même.");
        }

        // validate everything before mutating anything
        $this->checkTradeSide($biensDeA, $a);
        $this->checkTradeSide($biensDeB, $b);
        if ($argentDeAversB > 0 && $a->getMoney() < $argentDeAversB) {
            throw new InvalidPlayerActionException("A n'a pas les fonds pour la soulte.");
        }
        if ($argentDeAversB < 0 && $b->getMoney() < -$argentDeAversB) {
            throw new InvalidPlayerActionException("B n'a pas les fonds pour la soulte.");
        }

        // transfers (reuse existing setOwner / add/removeMoney)
        foreach ($biensDeA as $bien) $bien->setOwner($b);
        foreach ($biensDeB as $bien) $bien->setOwner($a);
        if ($argentDeAversB > 0) {
            $a->removeMoney($argentDeAversB);
            $b->addMoney($argentDeAversB);
        } elseif ($argentDeAversB < 0) {
            $b->removeMoney(-$argentDeAversB);
            $a->addMoney(-$argentDeAversB);
        }

        $this->emit(GameEventType::TRADE_COMPLETED, [
            'a' => $a->getName(), 'b' => $b->getName(),
            'biensDeA' => array_map(fn(Mortgageable $t) => $t->getName(), $biensDeA),
            'biensDeB' => array_map(fn(Mortgageable $t) => $t->getName(), $biensDeB),
            'money' => $argentDeAversB,
        ]);
    }

    // each tile must belong to $owner and carry no buildings
    private function checkTradeSide(array $biens, Player $owner): void {
        foreach ($biens as $bien) {
            if (!($bien instanceof Mortgageable) || $bien->getOwner() !== $owner) {
                throw new InvalidPlayerActionException("Bien non possédé par le bon joueur.");
            }
            if ($bien instanceof Property && $bien->getBuildLevel() > 0) {
                throw new InvalidPlayerActionException("Un bien construit ne peut pas être échangé.");
            }
        }
    }

}