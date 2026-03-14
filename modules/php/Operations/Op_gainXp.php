<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 * gainXp: Gain X gold/XP (move yellow crystals from supply to player tableau).
 * Count = amount of XP to gain (default 1).
 * Automated operation — no user choice needed.
 *
 * Rules: "Throughout the game, you will collect gold and experience (yellow),
 * which are treated as the same resource."
 * Used by: Miner (2gainXp(grimheim)), Popular (2gainXp(adjMountain)), Discipline.
 *
 * Optional param(0): location condition
 *   - "grimheim" — hero must be in Grimheim
 *   - "adjMountain" — hero must be adjacent to a mountain hex
 */
class Op_gainXp extends CountableOperation {
    function getPossibleMoves(): array {
        $condition = $this->getParam(0, "");
        if ($condition !== "" && !$this->checkCondition($condition)) {
            return ["q" => Material::ERR_PREREQ, "err" => clienttranslate("Condition not met")];
        }
        return parent::getPossibleMoves();
    }

    private function checkCondition(string $condition): bool {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $heroHex = $this->game->hexMap->getCharacterHex($heroId);
        if ($heroHex === null) {
            return false;
        }
        return match ($condition) {
            "grimheim" => $this->game->hexMap->isInGrimheim($heroHex),
            "adjMountain" => $this->isAdjacentToTerrain($heroHex, "mountain"),
            default => true,
        };
    }

    private function isAdjacentToTerrain(string $hex, string $terrain): bool {
        foreach ($this->game->hexMap->getAdjacentHexes($hex) as $adjHex) {
            if ($this->game->hexMap->getHexTerrain($adjHex) === $terrain) {
                return true;
            }
        }
        return false;
    }

    function resolve(): void {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $amount = (int) $this->getCount();
        $this->game->effect_moveCrystals($heroId, "yellow", $amount, "tableau_$owner");
    }
}
