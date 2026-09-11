<?php

interface Mortgageable {
    public function getPrice(): int;
    public function getOwner(): ?Player;
    public function isMortgaged(): bool;
    public function setMortgaged(bool $mortgaged): void;
}