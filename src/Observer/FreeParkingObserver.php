<?php

class FreeParkingObserver implements GameObserver {
    private Game $game;

    public function __construct(Game $game) {
        $this->game = $game;
    }

    public function onEvent(GameEvent $event): void {
        if ($event->getType() === GameEventType::TAX_PAID) {
            $c = $event->getContext();
            $this->game->addToFreeParkingPot($c['amount']);
        }
    }
}