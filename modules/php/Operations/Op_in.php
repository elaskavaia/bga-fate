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

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * in(Location): location-gate predicate, used inside r expressions to restrict
 * a clause to when the acting hero stands in a specific named location or
 * terrain. Voids (ERR_PREREQ) when the hero is elsewhere.
 *
 * Param matches against either:
 * - the hex's named loc field (e.g. "Grimheim")
 * - the hex's terrain field (e.g. "forest", "mountain", "plains", "lake")
 *
 * No collision: terrain values and named locations are disjoint.
 *
 * Example (Blade Decorations): `in(Grimheim):2spendXp:gainEquip` — only resolves
 * when the hero is standing in Grimheim.
 *
 * As with on(...), this gate must be the leftmost element of its paygain
 * chain so the void state propagates before any sub-op runs.
 */
class Op_in extends Operation {
    private function getExpectedLocation(): string {
        return $this->getParam(0, "");
    }

    function getPossibleMoves() {
        $expected = $this->getExpectedLocation();
        if ($expected === "") {
            return ["q" => Material::ERR_PREREQ];
        }
        $hex = $this->game->getHero($this->getOwner())->getHex();
        $this->game->systemAssert("ERR:in:heroNotOnMap", $hex !== null);
        $named = $this->game->hexMap->getHexNamedLocation($hex);
        $terrain = $this->game->hexMap->getHexTerrain($hex);
        if ($named === $expected || $terrain === $expected) {
            return parent::getPossibleMoves();
        }
        return ["q" => Material::ERR_PREREQ, "err" => "Incorrect location for the effect"];
    }

    function resolve(): void {
        // no-op gate
    }
}
