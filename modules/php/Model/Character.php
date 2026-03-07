<?php

declare(strict_types=1);

namespace Bga\Games\Fate\Model;

use Bga\Games\Fate\Game;

/**
 * Base class for game characters (heroes and monsters).
 * Provides common accessors for position, crystals, and attack range.
 */
class Character {
    private int $armorRemaining = 0;

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

    function getArmor(): int {
        return (int) $this->getRulesFor("armor", 0);
    }

    /**
     * Reset armor for a new attack. Call before rolling dice.
     */
    function beginDefense(): void {
        $this->armorRemaining = $this->getArmor();
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

    /**
     * Check if defender has cover (forest hex blocks "hitcov" results).
     */
    function hasCover(): bool {
        $hex = $this->getHex();
        return $hex !== null && $this->game->hexMap->getHexTerrain($hex) === "forest";
    }

    /**
     * Apply a single die result as damage. Checks cover for "hitcov",
     * and Dead faction rune-as-hit.
     * Places a red crystal on this character if it's a hit.
     * @param string $rule die result rule: "hit", "hitcov", "miss", "rune"
     * @param string $attackerId token id of the attacker
     * @return int 1 if hit, 0 if miss
     */
    function applyDamage(string $rule, string $attackerId): int {
        $isHit = $rule === "hit" || ($rule === "hitcov" && !$this->hasCover());
        // Dead faction: rune counts as hit
        if ($rule === "rune") {
            // TODO: handle other rune effects (some cards trigger on rune)
            $attackerFaction = $this->game->material->getRulesFor($attackerId, "faction", "");
            if ($attackerFaction === "dead") {
                $isHit = true;
            }
        }
        if ($isHit) {
            // Armor absorbs hits (e.g. Draugr armor=1)
            if ($this->armorRemaining > 0) {
                $this->armorRemaining--;
                return 0;
            }
            $this->moveCrystals("red", 1, $this->id, ["message" => ""]);
            return 1;
        }
        return 0;
    }

    function getRulesFor(string $field, mixed $default = ""): mixed {
        return $this->game->material->getRulesFor($this->id, $field, $default);
    }
}
