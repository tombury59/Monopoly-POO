<?php

enum CardEffectType{
    case GAIN_MONEY;
    case LOSE_MONEY;
    case MOVE_TO;
    case MOVE_STEPS;
    case GO_TO_JAIL;
    case EXIT_JAIL;
    case PAY_ALL;
    case RECEIVE_ALL;
    // TODO ajouter la derniere carte
    case REPAIR_BUILDINGS;
    // case NEAREST_UTILITY_OR_RAILROAD;
}