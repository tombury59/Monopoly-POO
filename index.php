<?php

require_once __DIR__ . '/src/Contract/Renderable.php';
require_once __DIR__ . '/src/Enum/TileType.php';
require_once __DIR__ . '/src/Enum/ColorGroup.php';
require_once __DIR__ . '/src/Square.php';
require_once __DIR__ . '/src/Tile/Tile.php';
require_once __DIR__ . '/src/Tile/Go.php';
require_once __DIR__ . '/src/Tile/Property.php';
require_once __DIR__ . '/src/Tile/Station.php';
require_once __DIR__ . '/src/Tile/Company.php';
require_once __DIR__ . '/src/Tile/Tax.php';
require_once __DIR__ . '/src/Tile/Chance.php';
require_once __DIR__ . '/src/Tile/CommunityChest.php';
require_once __DIR__ . '/src/Tile/Jail.php';
require_once __DIR__ . '/src/Tile/FreeParking.php';
require_once __DIR__ . '/src/Tile/GoToJail.php';
require_once __DIR__ . '/src/Board.php';

// $squares = [
//     new Square(1, Group::Station, "Départ", 0),
//     new Square(2, Group::Brown, "Boulevard de Belleville", 60),
//     new Square(3, Group::Station, "Caisse de communauté", 0),
//     new Square(4, Group::Brown, "Rue Lecourbe", 60),
//     new Square(5, Group::Station, "Impôt sur le revenu", 0),
//     new Square(6, Group::Station, "Gare Montparnasse", 200),
//     new Square(7, Group::LightBlue, "Rue de Vaugirard", 100),
//     new Square(8, Group::Station, "Chance", 0),
//     new Square(9, Group::LightBlue, "Rue de Courcelles", 100),
//     new Square(10, Group::LightBlue, "Avenue de la République", 120),
    
//     new Square(11, Group::Station, "En Prison / Simple visite", 0),
//     new Square(12, Group::Pink, "Boulevard de la Villette", 140),
//     new Square(13, Group::Station, "Compagnie de distribution d'électricité", 150),
//     new Square(14, Group::Pink, "Avenue de Neuilly", 140),
//     new Square(15, Group::Pink, "Rue de Paradis", 160),
//     new Square(16, Group::Station, "Gare de Lyon", 200),
//     new Square(17, Group::Orange, "Avenue Mozart", 180),
//     new Square(18, Group::Station, "Caisse de communauté", 0),
//     new Square(19, Group::Orange, "Boulevard Saint-Michel", 180),
//     new Square(20, Group::Orange, "Place Pigalle", 200),
    
//     new Square(21, Group::Station, "Parc Gratuit", 0),
//     new Square(22, Group::Red, "Avenue Matignon", 220),
//     new Square(23, Group::Station, "Chance", 0),
//     new Square(24, Group::Red, "Boulevard Malesherbes", 220),
//     new Square(25, Group::Red, "Avenue Henri-Martin", 240),
//     new Square(26, Group::Station, "Gare du Nord", 200),
//     new Square(27, Group::Yellow, "Faubourg Saint-Honoré", 260),
//     new Square(28, Group::Yellow, "Place de la Bourse", 260),
//     new Square(29, Group::Station, "Compagnie des eaux", 150),
//     new Square(30, Group::Yellow, "Rue La Fayette", 280),
    
//     new Square(31, Group::Station, "Allez en prison", 0),
//     new Square(32, Group::Green, "Avenue de Breteuil", 300),
//     new Square(33, Group::Green, "Avenue Foch", 300),
//     new Square(34, Group::Station, "Caisse de communauté", 0),
//     new Square(35, Group::Green, "Boulevard des Capucines", 320),
//     new Square(36, Group::Station, "Gare Saint-Lazare", 200),
//     new Square(37, Group::Station, "Chance", 0),
//     new Square(38, Group::DarkBlue, "Avenue des Champs-Élysées", 350),
//     new Square(39, Group::Station, "Taxe de luxe", 0),
//     new Square(40, Group::DarkBlue, "Rue de la Paix", 400),
// ];