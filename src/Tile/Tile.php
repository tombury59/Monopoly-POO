<?php 

abstract class Tile implements Renderable{

    protected string $name;
    protected Square $position;
    protected TileType $type;

    public function __construct(string $name, Square $position) {
        $this->name=$name;
        $this->position=$position;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getPosition(): Square {
        return $this->position;
    }

    public function getType(): TileType {
        return $this->type;
    }
    
    /* render() must return a short representation of the tile,
    used for the render method in Board*/
    public function render(): string {
        return match ($this->type) {
            TileType::GO => 'GO',
            TileType::JAIL => 'JAIL',
            TileType::FREE_PARKING => 'PARK',
            TileType::GO_TO_JAIL => 'GTJ',
            TileType::STATION => 'GAR',
            TileType::COMPANY => 'CIE',
            TileType::TAX => 'TAX',
            TileType::CHANCE => 'CHN',
            TileType::COMMUNITY_CHEST => 'CC',
            TileType::PROPERTY => strtoupper(substr($this->name, 0, 3)),
        };
    }

    public function landOn(Player $player, Game $game): void {
        $player->setPosition($this->position);
        $this->applyEffect($player, $game);
    }

    abstract protected function applyEffect(Player $player, Game $game): void;

    public function isOwnable(): bool {
        return false;
    }
}