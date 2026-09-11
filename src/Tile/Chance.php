<?php

class Chance extends Tile {

    private array $cards = [];

    public function __construct(string $name, Square $position){
        parent::__construct($name,$position);
        $this->type = TileType::CHANCE;
        $this->cards = $this->buildDeck();
    }

    protected function applyEffect(Player $player, Game $game): void {
        $this->cards[array_rand($this->cards)]->apply($player, $game);
        // TODO: notify (card_drawn)
    }

    // Source (Chance cards, US, classique 2008-2021) :
    // 1. Advance to Boardwalk
    // 2. Advance to Go (Collect $200)
    // 3. Advance to Illinois Avenue. If you pass Go, collect $200
    // 4. Advance to St. Charles Place. If you pass Go, collect $200
    // 5. Advance to the nearest Railroad (x2)
    // 6. Advance token to nearest Utility
    // 7. Bank pays you dividend of $50
    // 8. Get Out of Jail Free
    // 9. Go Back 3 Spaces
    // 10. Go to Jail. Go directly to Jail, do not pass Go, do not collect $200
    // 11. Make general repairs on all your property. For each house pay $25. For each hotel pay $100
    // 12. Speeding fine $15
    // 13. Take a trip to Reading Railroad. If you pass Go, collect $200
    // 14. You have been elected Chairman of the Board. Pay each player $50
    // 15. Your building loan matures. Collect $150
    private function buildDeck(): array {
        return [
            new Card("Avancez jusqu'au Boulevard des Capucines", CardEffectType::MOVE_TO, 34),
            new Card("Avancez jusqu'à la case Départ", CardEffectType::MOVE_TO, 0),
            new Card("Avancez jusqu'à la Rue de Vaugirard, si vous passez par la case Départ, gagnez 200$", CardEffectType::MOVE_TO, 6),
            new Card("Avancez jusqu'à l'Avenue Matignon, si vous passez par la case Départ, gagnez 200$", CardEffectType::MOVE_TO, 21),
            new Card("Avancez jusqu'à la gare la plus proche", CardEffectType::NEAREST_STATION),
            new Card("Avancez jusqu'à la compagnie la plus proche", CardEffectType::NEAREST_UTILITY),
            new Card("La banque vous verse un dividende de 50$", CardEffectType::GAIN_MONEY, 50),
            new Card("Sortie de prison gratuite", CardEffectType::EXIT_JAIL, 0),
            new Card("Reculez de 3 cases", CardEffectType::MOVE_STEPS, -3),
            new Card("Rendez-vous directement en prison, sans passer par la case Départ", CardEffectType::GO_TO_JAIL, 0),
            new Card("Vous faites des réparations sur toutes vos propriétés. Pour chaque maison: payez 25$ et pour chaque hotels 100$",CardEffectType::REPAIR_BUILDINGS,25,100),
            new Card("Amende pour excès de vitesse : payez 15$", CardEffectType::LOSE_MONEY, 15),
            new Card("Rendez-vous à la Gare Montparnasse, si vous passez par la case Départ, gagnez 200$", CardEffectType::MOVE_TO, 5),
            new Card("Vous êtes élu président du conseil : payez 50$ à chaque joueur", CardEffectType::PAY_ALL, 50),
            new Card("Votre emprunt immobilier arrive à échéance : gagnez 150$", CardEffectType::GAIN_MONEY, 150),
        ];
    }
}