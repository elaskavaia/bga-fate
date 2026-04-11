<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

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

    function getArmor(): int {
        return (int) $this->getRulesFor("armor", 0);
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
     * Check if a single die result is a hit. Handles cover for "hitcov"
     * and Dead faction rune-as-hit.
     * @param string $rule die result rule: "hit", "hitcov", "miss", "rune"
     * @param string $attackerId token id of the attacker
     * @return int 1 if hit, 0 if miss
     */
    function countHit(string $rule, string $attackerId): int {
        $isHit = $rule === "hit" || ($rule === "hitcov" && !$this->hasCover());
        // Dead faction: rune counts as hit
        if ($rule === "rune") {
            // TODO: handle other rune effects (some cards trigger on rune)
            $attackerFaction = $this->game->material->getRulesFor($attackerId, "faction", "");
            if ($attackerFaction === "dead") {
                $isHit = true;
            }
        }
        return $isHit ? 1 : 0;
    }

    /**
     * Reduce raw hits by armor. Call once with total hit count.
     * @return int effective damage after armor absorption
     */
    function applyArmor(int $hits): int {
        $armor = $this->getArmor();
        return max(0, $hits - $armor);
    }

    function getRulesFor(string $field, mixed $default = ""): mixed {
        return $this->game->material->getRulesFor($this->id, $field, $default);
    }

    /** @return int health - totalDamage: positive if survived, <= 0 if killed (abs = overkill) */
    function applyDamageEffects(int $amount, string $attackerId): int {
        // Base character has no damage effects; overridden in Hero and Monster
        return 1;
    }
}
