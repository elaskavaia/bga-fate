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
 * adj(Location): adjacency-gate predicate. Voids (ERR_PREREQ) unless the acting hero
 * is adjacent to at least one hex whose terrain or named location matches the param.
 *
 * Parallel to Op_in (which checks containment), for rules like
 * "Gain 2 [XP] if you are adjacent to a mountain area" (Miner: `adj(mountain):2gainXp`).
 *
 * Param matches against either:
 * - terrain field (e.g. "forest", "mountain", "plains", "lake")
 * - named loc field (e.g. "Grimheim")
 *
 * As with on(...) and in(...), this gate must be the leftmost element of its paygain
 * chain so the void state propagates before any sub-op runs.
 */
class Op_adj extends Operation {
    private function getExpectedLocation(): string {
        return $this->getParam(0, "");
    }

    function getPossibleMoves() {
        $expected = $this->getExpectedLocation();
        if ($expected === "") {
            return ["q" => Material::ERR_PREREQ];
        }
        $hex = $this->game->getHero($this->getOwner())->getHex();
        $this->game->systemAssert("ERR:adj:heroNotOnMap", $hex !== null);
        foreach ($this->game->hexMap->getAdjacentHexes($hex) as $adjHex) {
            $named = $this->game->hexMap->getHexNamedLocation($adjHex);
            $terrain = $this->game->hexMap->getHexTerrain($adjHex);
            if ($named === $expected || $terrain === $expected) {
                return parent::getPossibleMoves();
            }
        }
        return ["q" => Material::ERR_PREREQ, "err" => clienttranslate("Not adjacent to required location")];
    }

    function resolve(): void {
        // no-op gate
    }
}
