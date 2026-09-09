<?php

class Square{

    public $id;
    public $group;
    public $name;
    public $price;

    public function __construct($id, Group $group, $name, $price){
        $this->id = $id;
        $this->group = $group;
        $this->name = $name;
        $this->price = $price;
    }
}