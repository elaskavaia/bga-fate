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

    /** True for heroes — replaces the "hero"/"monster" string used by passability checks. */
    function isHero(): bool {
        return false;
    }

    /** Mountain terrain still blocks this character. Overridden by Hero for Fleetfoot II. */
    function canIgnoreMountains(): bool {
        return false;
    }

    /** Occupied intermediate hexes still block this character's path. Overridden by Hero for Fleetfoot II. */
    function canIgnoreOccupied(): bool {
        return false;
    }

    function isInForest(): bool {
        $hex = $this->getHex();
        return $hex !== null && $this->game->hexMap->getHexTerrain($hex) === "forest";
    }

    function getAttackRange(): int {
        return 1;
    }

    function getArmor(): int {
        return (int) $this->getRulesFor("armor", 0);
    }

    /** Number of red crystals (damage) on this character. */
    function getDamage(): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $this->id));
    }

    /**
     * Move crystals to/from this character.
     * @param string $type crystal type: "red", "green", "yellow"
     * @param int $inc positive = gain, negative = lose
     * @param string $location target location for the crystals
     */
    function moveCrystals(string $type, int $inc, string $location, array $options = []): void {
        $this->game->effect_moveCrystals($this->id, $type, $inc, $location, $options + ["char_name" => $this->id]);
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
     *
     * @param string $rule die result rule: "hit", "hitcov", "miss", "rune"
     * @param string|null $defenderHex hex of the defender — null means "no cover"
     * @return int 1 if hit, 0 if miss
     */
    function countHit(string $rule, ?string $defenderHex = null): int {
        $defenderHasCover = $defenderHex !== null && $this->game->hexMap->getHexTerrain($defenderHex) === "forest";
        $isHit = $rule === "hit" || ($rule === "hitcov" && !$defenderHasCover);
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

    /**
     * Health threshold used by evaluateDamage to decide whether totalDamage
     * has killed/knocked-out this character. Override in Hero (max health) and
     * Monster (card health). The base PHP_INT_MAX makes plain Character invincible.
     */
    function getEffectiveHealth(): int {
        return PHP_INT_MAX;
    }

    /**
     * Pure damage detection — no side effects.
     *
     * Reads the current red-crystal count on this character and decides whether
     * the character is killed/knocked-out. Does NOT add the new damage; the
     * caller (Op_applyDamage) is responsible for placing the red crystals on
     * this character before calling.
     *
     * @return array{killed:bool, remaining:int, totalDamage:int}
     */
    function evaluateDamage(int $amount, string $attackerId): array {
        $this->game->systemAssert("ERR:evaluateDamage:negative:$amount", $amount >= 0);
        $totalDamage = $this->getDamage();
        $health = $this->getEffectiveHealth();
        $remaining = $health - $totalDamage;
        return [
            "killed" => $totalDamage >= $health,
            "remaining" => $remaining,
            "totalDamage" => $totalDamage,
        ];
    }

    /**
     * Finalisation step run from Op_finishKill, after TMonsterKilled /
     * THeroKnockedOut have dispatched. Subclasses do their cleanup here:
     * Monster moves to supply + awards XP; Hero is knocked back to Grimheim;
     * GoldVein extracts gold for the attacker.
     *
     * $noXp suppresses any XP / gold reward this kill would normally produce —
     * set by Op_blockXp inside trigger handler chains for "claim card INSTEAD
     * of XP" quests (Helmet, Quiver, Orebiter via card text).
     */
    function finalizeDamage(int $amount, string $attackerId, bool $noXp = false): void {
        // base: no cleanup
    }
}
