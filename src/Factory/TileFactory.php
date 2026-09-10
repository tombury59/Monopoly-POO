<?php

class TileFactory {
    public function create(TileType $type, string $name, Square $position, array $options = []): Tile {
        return match ($type) {
            TileType::GO => new Go(
                $name, 
                $position
            ),
            TileType::JAIL => new Jail(
                $name, 
                $position
            ),
            TileType::FREE_PARKING => new FreeParking(
                $name, 
                $position
            ),
            TileType::CHANCE => new Chance(
                $name, 
                $position
            ),
            TileType::COMMUNITY_CHEST => new CommunityChest(
                $name, 
                $position
            ),
            TileType::GO_TO_JAIL => new GoToJail(
                $name, 
                $position
            ),

            TileType::TAX => new Tax(
                $name, $position, 
                $options['amount'] ?? 0 
            ),
            TileType::STATION => new Station(
                $name, $position, 
                $options['price'] ?? 0
            ),
            TileType::COMPANY => new Company(
                $name, $position, 
                $options['price'] ?? 0
            ),

            TileType::PROPERTY => new Property(
                $name, 
                $position, 
                $options['colorGroup'] ?? throw new MonopolyException("Le paramètre 'colorGroup' est requis pour créer une Property."), 
                $options['price'] ?? 0 ,
                $options['rents'][0] ?? 0,
                $options['rents'] ?? [],
                $options['housePrice'] ?? 0
            ),
        };
    }

}