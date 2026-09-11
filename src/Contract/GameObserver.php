<?php

interface GameObserver
{
    public function onEvent(GameEvent $event): void;
}