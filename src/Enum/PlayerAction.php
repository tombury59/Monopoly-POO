<?php

enum PlayerAction: string
{
    case ROLL               = 'roll';
    case BUY_TILE           = 'buy_tile';
    case BUILD_HOUSE        = 'build_house';
    case SELL_HOUSE         = 'sell_house';
    case MORTGAGE           = 'mortgage';
    case UNMORTGAGE         = 'unmortgage';
    case USE_JAIL_CARD      = 'use_jail_card';
    case PAY_BAIL           = 'pay_bail';
    case DECLARE_BANKRUPTCY = 'declare_bankruptcy';
    case END_TURN           = 'end_turn';
    // TODO : PROPOSE_TRADE, BUYOUT_TILE
}