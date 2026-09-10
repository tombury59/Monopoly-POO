<?php

class Game {
    private Board $board;
    private array $players = [];
    private int $currentPlayerIndex = 0;
    private Dice $dice;
    private TileFactory $tileFactory;

    public const JAIL_BAIL = 50;

    public function __construct(array $playerNames) {
        foreach($playerNames as $playerName){
            $this->players[] = new Player($playerName);
        }
        $this->board = new Board();
        $this->dice = new Dice(6);
        $this->tileFactory = new TileFactory();
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

        if ($dice[0] === $dice[1]) {
            // TODO: notify
            $playingPlayer->setInJail(false);
            $playingPlayer->resetTurnsInJail();

            try {
                $this->resolveMovement($playingPlayer, array_sum($dice));
            } finally {
                $this->nextPlayer();
            }

            return;
        }

        $playingPlayer->addTurnsInJail();

        if ($playingPlayer->getTurnsInJail() >= 3) {
            try {
                // TODO: faillite si le joueur ne peut pas payer la caution
                $playingPlayer->removeMoney(self::JAIL_BAIL);
                $playingPlayer->setInJail(false);
                $playingPlayer->resetTurnsInJail();
                $this->resolveMovement($playingPlayer, array_sum($dice));
            } finally {
                $this->nextPlayer();
            }

            return;
        }

        // TODO: notify
        $this->nextPlayer();
    }

    public function payToLeaveJail(): void {
        $playingPlayer = $this->getCurrentPlayer();

        if(!$playingPlayer->isInJail()){
            throw new InvalidPlayerActionException("Le joueur n'est pas en prison.");
        }

        // TODO: notify
        // TODO: faillite si le joueur ne peut pas payer la caution
        $playingPlayer->removeMoney(self::JAIL_BAIL);
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

        try {
            $doublesCount = 0;

            do {
                $dice = $this->dice->rollTwo();
                // TODO: notify

                $isDouble = $dice[0] === $dice[1];
                $step = array_sum($dice);

                if ($isDouble) {
                    $doublesCount++;
                    // TODO: notify
                }

                if ($doublesCount === 3) {
                    $goToJailTile = $this->board->findTileByType(TileType::GO_TO_JAIL);
                    if ($goToJailTile === null) {
                        throw new MonopolyException("Aucune case GoToJail trouvée sur le plateau.");
                    }
                    $goToJailTile->landOn($playingPlayer, $this);
                    // TODO: notify
                    break;
                }

                $this->resolveMovement($playingPlayer, $step);

                if ($playingPlayer->isInJail()) {
                    break;
                }

            } while ($isDouble);
        } finally {
            $this->nextPlayer();
            // TODO: notify
        }
    }

    public function resolveMovement(Player $player, int $step): void {
        $actualPosition = $player->getPosition();

        // player who passes the Go square receive +200, but if he stops on this square:
        // Go::applyEffect() applies and adds another +200
        if (($actualPosition->getIndex() + $step) >= $this->board->getBoardSize()) {
            $player->addMoney(200);
            // TODO: notify
        }

        $squareToLand = $actualPosition->next($step);
        $player->setPosition($squareToLand);
        // TODO: notify

        $tile = $this->board->getTileAt($squareToLand);
        if ($tile === null) {
            throw new MonopolyException("Aucune case trouvée à la position {$squareToLand->toKey()}.");
        }

        // TODO: notify
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
            // TODO: notify
        }
    }

    private function setupBoard(): void {
        $definitions = [
            [TileType::GO, 'Départ', 0, []],
            [TileType::PROPERTY, 'Boulevard de Belleville', 1, ['colorGroup' => ColorGroup::BROWN, 'price' => 60, 'rent' => 2]],
            [TileType::COMMUNITY_CHEST, 'Caisse de Communauté', 2, []],
            [TileType::PROPERTY, 'Rue Lecourbe', 3, ['colorGroup' => ColorGroup::BROWN, 'price' => 60, 'rent' => 4]],
            [TileType::TAX, 'Impôts sur le revenu', 4, ['amount' => 200]],
            [TileType::STATION, 'Gare Montparnasse', 5, ['price' => 200]],
            [TileType::PROPERTY, 'Rue de Vaugirard', 6, ['colorGroup' => ColorGroup::LIGHT_BLUE, 'price' => 100, 'rent' => 6]],
            [TileType::CHANCE, 'Chance', 7, []],
            [TileType::PROPERTY, 'Rue de Courcelles', 8, ['colorGroup' => ColorGroup::LIGHT_BLUE, 'price' => 100, 'rent' => 6]],
            [TileType::PROPERTY, 'Avenue de la République', 9, ['colorGroup' => ColorGroup::LIGHT_BLUE, 'price' => 120, 'rent' => 8]],
            [TileType::JAIL, 'Prison / Simple visite', 10, []],
            [TileType::PROPERTY, 'Boulevard de la Villette', 11, ['colorGroup' => ColorGroup::PINK, 'price' => 140, 'rent' => 10]],
            [TileType::COMPANY, "Compagnie de Distribution d'Électricité", 12, ['price' => 150]],
            [TileType::PROPERTY, 'Avenue de Neuilly', 13, ['colorGroup' => ColorGroup::PINK, 'price' => 140, 'rent' => 10]],
            [TileType::PROPERTY, 'Rue de Paradis', 14, ['colorGroup' => ColorGroup::PINK, 'price' => 160, 'rent' => 12]],
            [TileType::STATION, 'Gare de Lyon', 15, ['price' => 200]],
            [TileType::PROPERTY, 'Avenue Mozart', 16, ['colorGroup' => ColorGroup::ORANGE, 'price' => 180, 'rent' => 14]],
            [TileType::COMMUNITY_CHEST, 'Caisse de Communauté', 17, []],
            [TileType::PROPERTY, 'Boulevard Saint-Michel', 18, ['colorGroup' => ColorGroup::ORANGE, 'price' => 180, 'rent' => 14]],
            [TileType::PROPERTY, 'Place Pigalle', 19, ['colorGroup' => ColorGroup::ORANGE, 'price' => 200, 'rent' => 16]],
            [TileType::FREE_PARKING, 'Parc Gratuit', 20, []],
            [TileType::PROPERTY, 'Avenue Matignon', 21, ['colorGroup' => ColorGroup::RED, 'price' => 220, 'rent' => 18]],
            [TileType::CHANCE, 'Chance', 22, []],
            [TileType::PROPERTY, 'Boulevard Malesherbes', 23, ['colorGroup' => ColorGroup::RED, 'price' => 220, 'rent' => 18]],
            [TileType::PROPERTY, 'Avenue Henri-Martin', 24, ['colorGroup' => ColorGroup::RED, 'price' => 240, 'rent' => 20]],
            [TileType::STATION, 'Gare du Nord', 25, ['price' => 200]],
            [TileType::PROPERTY, 'Faubourg Saint-Honoré', 26, ['colorGroup' => ColorGroup::YELLOW, 'price' => 260, 'rent' => 22]],
            [TileType::PROPERTY, 'Place de la Bourse', 27, ['colorGroup' => ColorGroup::YELLOW, 'price' => 260, 'rent' => 22]],
            [TileType::COMPANY, 'Compagnie des Eaux', 28, ['price' => 150]],
            [TileType::PROPERTY, 'Rue La Fayette', 29, ['colorGroup' => ColorGroup::YELLOW, 'price' => 280, 'rent' => 24]],
            [TileType::GO_TO_JAIL, 'Allez en Prison', 30, []],
            [TileType::PROPERTY, 'Avenue de Breteuil', 31, ['colorGroup' => ColorGroup::GREEN, 'price' => 300, 'rent' => 26]],
            [TileType::PROPERTY, 'Avenue Foch', 32, ['colorGroup' => ColorGroup::GREEN, 'price' => 300, 'rent' => 26]],
            [TileType::COMMUNITY_CHEST, 'Caisse de Communauté', 33, []],
            [TileType::PROPERTY, 'Boulevard des Capucines', 34, ['colorGroup' => ColorGroup::GREEN, 'price' => 320, 'rent' => 28]],
            [TileType::STATION, 'Gare Saint-Lazare', 35, ['price' => 200]],
            [TileType::CHANCE, 'Chance', 36, []],
            [TileType::PROPERTY, 'Avenue des Champs-Élysées', 37, ['colorGroup' => ColorGroup::DARK_BLUE, 'price' => 350, 'rent' => 35]],
            [TileType::TAX, 'Taxe de luxe', 38, ['amount' => 100]],
            [TileType::PROPERTY, 'Rue de la Paix', 39, ['colorGroup' => ColorGroup::DARK_BLUE, 'price' => 400, 'rent' => 50]],
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
}