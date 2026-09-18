<?php

class GameRules {
    private bool $freeParkingJackpot;
    private bool $businessTour;

    public function __construct(bool $freeParkingJackpot = false, bool $businessTour = false) {
        $this->freeParkingJackpot = $freeParkingJackpot;
        $this->businessTour       = $businessTour;
    }

    public function isFreeParkingJackpotEnabled(): bool {
        return $this->freeParkingJackpot;
    }

    public function isBusinessTourEnabled(): bool {
        return $this->businessTour;
    }
}
