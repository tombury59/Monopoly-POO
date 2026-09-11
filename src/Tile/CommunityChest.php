<?php

class CommunityChest extends Tile {

    private array $cards = [];

    public function __construct(string $name, Square $position){
        parent::__construct($name,$position);
        $this->type = TileType::COMMUNITY_CHEST;
        $this->cards = $this->buildDeck();
    }

    protected function applyEffect(Player $player, Game $game): void {

        $card = $this->cards[array_rand($this->cards)];
        $game->emit(GameEventType::COMMUNITY_CHEST_DRAWN, [
            'player' => $player->getName(),
            'card'   => $card->getDescription(),
        ]);
        $card->apply($player, $game);
    }

    // Source (Community Chest cards, US, classique 2008-2021) :
    // 1. Advance to Go (Collect $200)
    // 2. Bank error in your favor. Collect $200
    // 3. Doctor's fee. Pay $50
    // 4. From sale of stock you get $50
    // 5. Get Out of Jail Free
    // 6. Go to Jail. Go directly to jail, do not pass Go, do not collect $200
    // 7. Holiday fund matures. Receive $100
    // 8. Income tax refund. Collect $20
    // 9. It is your birthday. Collect $10 from every player
    // 10. Life insurance matures. Collect $100
    // 11. Pay hospital fees of $100
    // 12. Pay school fees of $50
    // 13. Receive $25 consultancy fee
    // 14. You are assessed for street repair. $40 per house. $115 per hotel
    // 15. You have won second prize in a beauty contest. Collect $10
    // 16. You inherit $100
    private function buildDeck(): array {
        return [
            new Card("Avancez jusqu'à la case Départ", CardEffectType::MOVE_TO, 0),
            new Card("Erreur de banque en votre faveur : gagnez 200$", CardEffectType::GAIN_MONEY, 200),
            new Card("Frais de docteur : payez 50$", CardEffectType::LOSE_MONEY, 50),
            new Card("Vente de stock : gagnez 50$", CardEffectType::GAIN_MONEY, 50),
            new Card("Sortie de prison gratuite", CardEffectType::EXIT_JAIL, 0),
            new Card("Aller en prison, sans passer par la case de départ", CardEffectType::GO_TO_JAIL, 0),
            new Card("Fonds de vacances : gagnez 100$", CardEffectType::GAIN_MONEY, 100),
            new Card("Remboursement d'impôts : gagnez 20$", CardEffectType::GAIN_MONEY, 20),
            new Card("C'est votre anniversaire ! Gagnez 10$ de la part de chaque joueur", CardEffectType::RECEIVE_ALL, 10),
            new Card("Votre assurance-vie vous rapporte 100$", CardEffectType::GAIN_MONEY, 100),
            new Card("Vous devez payer les frais d'hôpital : 100$", CardEffectType::LOSE_MONEY, 100),
            new Card("Vous devez payer les frais de scolarité : 50$", CardEffectType::LOSE_MONEY, 50),
            new Card("Recevez 25$ pour services de consultant", CardEffectType::GAIN_MONEY, 25),
            new Card("Payez des réparations de voirie : 40$ par maison et 115$ par hôtel.", CardEffectType::REPAIR_BUILDINGS, 40,115),
            new Card("Vous avez gagné le second prix de beauté : 10$", CardEffectType::GAIN_MONEY, 10),
            new Card("Vous héritez de 100$", CardEffectType::GAIN_MONEY, 100),
        ];
    }
}