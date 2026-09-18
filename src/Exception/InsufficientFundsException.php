<?php

class InsufficientFundsException extends MonopolyException {

    private int $amount;
    private ?Player $creditor;

    public function __construct(string $message = "", int $amount = 0, ?Player $creditor = null) {
        parent::__construct($message);
        $this->amount   = $amount;
        $this->creditor = $creditor;
    }

    public function getAmount(): int {
        return $this->amount;
    }

    public function getCreditor(): ?Player {
        return $this->creditor;
    }

    public function setCreditor(?Player $creditor): void {
        $this->creditor = $creditor;
    }
}
