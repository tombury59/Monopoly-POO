<?php

class ConsoleGameObserver implements GameObserver {

    public function onEvent(GameEvent $event): void
    {
        echo '[' . $event->getType()->value . '] ' . $this->format($event) . PHP_EOL;
    }

    private function format(GameEvent $event): string
    {
        $c = $event->getContext();

        return match ($event->getType()) {

            // move / board
            GameEventType::DICE_ROLLED =>
                "Des lancés : {$c['dice1']} et {$c['dice2']}.",
            GameEventType::PLAYER_MOVED =>
                "{$c['player']} avance case {$c['position']}.",
            GameEventType::PASSED_GO =>
                "{$c['player']} passe par la case Départ (+{$c['amount']}).",
            GameEventType::LANDED_ON_TILE =>
                "{$c['player']} arrive sur la case {$c['tile']}.",
            
            // money
            GameEventType::RENT_PAID =>
                "{$c['player']} paye {$c['amount']} de loyer sur {$c['tile']} a {$c['owner']}.",
            GameEventType::TAX_PAID =>
                "{$c['player']} paye {$c['amount']} de taxes.",
            GameEventType::MONEY_GAINED =>
                "{$c['player']} gagne {$c['amount']} de monnaie.",
            GameEventType::MONEY_LOST =>
                "{$c['player']} perd {$c['amount']} de monnaie.",

            // properties
            GameEventType::TILE_PURCHASED =>
                "{$c['player']} achète {$c['tile']} pour {$c['price']}.",
            GameEventType::HOUSE_BUILT =>
                "{$c['player']} construit une maison sur {$c['tile']}.",
            GameEventType::HOTEL_BUILT =>
                "{$c['player']} construit un hôtel sur {$c['tile']}.",
            GameEventType::HOUSE_SOLD =>
                "{$c['player']} vend une maison sur {$c['tile']} pour {$c['price']}.",
            GameEventType::TILE_MORTGAGED =>
                "{$c['player']} hypothèque {$c['tile']}.",
            GameEventType::TILE_UNMORTGAGED =>
                "{$c['player']} remet en jeu {$c['tile']}.",
            GameEventType::TILE_BOUGHT_OUT =>
                "{$c['player']} rachète {$c['tile']} à {$c['owner']} pour {$c['price']}.",

            // dice / specials turns
            GameEventType::DOUBLE_ROLLED =>
                "Double !",
            GameEventType::THREE_DOUBLES =>
                "Triple double !",
            GameEventType::TURN_STARTED =>
                "Debut de tour de {$c['player']}.",
            GameEventType::TURN_ENDED =>
                "Fin de tour de {$c['player']}.",

            // jail
            GameEventType::SENT_TO_JAIL =>
                "{$c['player']} est envoyé en prison.",
            GameEventType::JAIL_PAID =>
                "{$c['player']} paye {$c['amount']} pour sortir de prison.",
            GameEventType::JAIL_ESCAPED_BY_DOUBLE =>
                "{$c['player']} sort de prison par double !",
            GameEventType::JAIL_CARD_USED =>
                "{$c['player']} utilise sa carte de sortie de prison.",
            GameEventType::JAIL_FORCED_RELEASE =>
                "{$c['player']} est libéré de prison par force.",
            GameEventType::JAIL_TURN_SKIPPED =>
                "{$c['player']} passe son tour en prison.",

            // card
            GameEventType::CHANCE_CARD_DRAWN =>
                "{$c['player']} tire une carte chance: {$c['card']}.",
            GameEventType::COMMUNITY_CHEST_DRAWN =>
                "{$c['player']} tire une caisse de communauté: {$c['card']}.",

            // end game
            GameEventType::PLAYER_BANKRUPT =>
                "{$c['player']} est en faillite.",
            GameEventType::GAME_OVER =>
                "Fin de partie.",

            default => '',
        };
    }
}