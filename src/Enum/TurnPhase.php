<?php

enum TurnPhase: string
{
    case AWAITING_ROLL        = 'awaiting_roll';        // start turn: await roll (or jail decision)
    case AWAITING_ACTION      = 'awaiting_action';      // moved + tile resolved: managing assets / buying / ending turn
    case AWAITING_LIQUIDATION = 'awaiting_liquidation'; // debt > cash: selling/mortgaging or declaring bankruptcy
    case TURN_OVER            = 'turn_over';            // turn ended: ready to pass to the next player
}