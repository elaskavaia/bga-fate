<?php

declare(strict_types=1);

namespace Bga\Games\Fate\Model;

use Bga\Games\Fate\Game;

/**
 * Base class for game characters (heroes and monsters).
 * Provides common accessors for position, crystals, and attack range.
 */
class Character {
    public function __construct(protected Game $game, protected string $id) {}

    function getId(): string {
        return $this->id;
    }

    function getHex(): ?string {
        return $this->game->hexMap->getCharacterHex($this->id);
    }

    function getAttackRange(): int {
        return 1;
    }

    /**
     * Move crystals to/from this character.
     * @param string $type crystal type: "red", "green", "yellow"
     * @param int $inc positive = gain, negative = lose
     * @param string $location target location for the crystals
     */
    function moveCrystals(string $type, int $inc, string $location, array $options = []): void {
        $this->game->effect_moveCrystals($this->id, $type, $inc, $location, $options);
    }

    /**
     * Move this character to a new location (hex or off-map).
     */
    function moveTo(string $location, string $message = "*", array $args = []): void {
        if ($message == "*") {
            $message = clienttranslate('${char_name} moves into ${place_name} ${reason}');
        }
        $this->game->tokens->dbSetTokenLocation($this->id, $location, 0, $message, $args + ["char_name" => $this->id]);
        $this->game->hexMap->moveCharacterOnMap($this->id, $location);
    }

    function getRulesFor(string $field, mixed $default = ""): mixed {
        return $this->game->material->getRulesFor($this->id, $field, $default);
    }
}
