<?php

class GameRules {
    private bool $freeParkingJackpot;
    private bool $propertyBuyout;

    public function __construct(bool $freeParkingJackpot = false, bool $propertyBuyout = false) {
        $this->freeParkingJackpot = $freeParkingJackpot;
        $this->propertyBuyout     = $propertyBuyout;
    }

    public function isFreeParkingJackpotEnabled(): bool {
        return $this->freeParkingJackpot;
    }

    public function isPropertyBuyoutEnabled(): bool {
        return $this->propertyBuyout;
    }
}