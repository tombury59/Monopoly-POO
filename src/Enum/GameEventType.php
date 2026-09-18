<?php

enum GameEventType: string
{
    // move / board
    case DICE_ROLLED    = 'dice_rolled';
    case PLAYER_MOVED   = 'player_moved';
    case PASSED_GO      = 'passed_go';
    case LANDED_ON_TILE = 'landed_on_tile';

    // money
    case RENT_PAID    = 'rent_paid';
    case TAX_PAID     = 'tax_paid';
    case MONEY_GAINED = 'money_gained';
    case MONEY_LOST   = 'money_lost';

    // properties
    case TILE_PURCHASED   = 'tile_purchased';
    case HOUSE_BUILT      = 'house_built';
    case HOTEL_BUILT      = 'hotel_built';
    case HOUSE_SOLD       = 'house_sold';
    case TILE_MORTGAGED   = 'tile_mortgaged';
    case TILE_UNMORTGAGED = 'tile_unmortgaged';
    case TILE_BOUGHT_OUT  = 'tile_bought_out';

    // dice / specials turns
    case DOUBLE_ROLLED = 'double_rolled';
    case THREE_DOUBLES = 'three_doubles';
    case TURN_STARTED  = 'turn_started';
    case TURN_ENDED    = 'turn_ended';

    // jail
    case SENT_TO_JAIL          = 'sent_to_jail';
    case JAIL_PAID             = 'jail_paid';
    case JAIL_ESCAPED_BY_DOUBLE = 'jail_escaped_by_double';
    case JAIL_CARD_USED        = 'jail_card_used';
    case JAIL_FORCED_RELEASE   = 'jail_forced_release';
    case JAIL_TURN_SKIPPED     = 'jail_turn_skipped';

    // card
    case CHANCE_CARD_DRAWN = 'chance_card_drawn';
    case COMMUNITY_CHEST_DRAWN = 'community_chest_drawn';

    // end game
    case PLAYER_BANKRUPT = 'player_bankrupt';
    case GAME_OVER       = 'game_over';
}