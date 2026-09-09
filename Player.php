<?php

class Player{
    public $name = "";
    public $money = 0;
    public $color = "";
    public $position = 0;
    public $ownSquaresIds = [];

    public function __construct($name, $money, $color, $position, $ownSquaresIds){
        $this->name = $name;
        $this->money = $money;
        $this->color = $color;
        $this->position = $position;
        $this->ownSquaresIds = $ownSquaresIds;
    }

}