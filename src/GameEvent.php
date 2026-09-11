<?php

class GameEvent
{
    private GameEventType $type;
    private array $context;

    public function __construct(GameEventType $type, array $context = []) {
        $this->type=$type;
        $this->context=$context;
    }

    public function getType(): GameEventType {
        return $this->type;
    }
    
    public function getContext(): array {
        return $this->context;
    }
}