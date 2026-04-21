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

use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 * Move hero X areas (count = max steps, mcount = min steps).
 * Reuses getReachableHexes() from HexMap with count as maxSteps.
 *
 * Mandatory movement (mcount == count, e.g. "2move"): hero must move exactly
 * count steps if possible. If no hex at that distance is reachable, falls back to
 * the farthest reachable distance. If completely blocked, returns empty.
 *
 * Optional movement (mcount < count, e.g. "[0,3]move"): hero may move any
 * number of steps from mcount to count.
 *
 * Params:
 * - param(0):
 *   - "locationOnly" — destinations restricted to hexes belonging to any named
 *     location (DarkForest, Grimheim, TempleRuins, …).
 *   - "<name>" — destinations restricted to hexes whose terrain or named
 *     location equals <name>, e.g. "forest" for Treetreader's move(forest).
 *
 * Used by: Agility (2move), Maneuver (1move), Fleetfoot (1move),
 * Quick Reflexes (1move), Seek Shelter ([0,2]move(locationOnly)),
 * Treetreader (move(forest)).
 */
class Op_move extends CountableOperation {
    function getPrompt() {
        return clienttranslate("Select where to move");
    }

    function getPossibleMoves(): array {
        $target = $this->getDataField("target", "");
        if ($target) {
            return [$target];
        }
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $currentHex = $this->game->tokens->getTokenLocation($heroId);
        $maxSteps = (int) $this->getCount();
        $minSteps = (int) $this->getMinCount();
        $reachable = $this->game->hexMap->getReachableHexes($currentHex, $maxSteps);

        // For mandatory movement (minSteps == maxSteps), only offer hexes at the
        // required distance. If none exist, fall back to farthest reachable.
        if ($minSteps > 0 && $minSteps == $maxSteps && count($reachable) > 0) {
            $maxReachableDist = max($reachable);
            $requiredDist = min($maxSteps, $maxReachableDist);
            $reachable = array_filter($reachable, fn($dist) => $dist == $requiredDist);
        }

        $filter = $this->getParam(0, "");
        if ($filter === "locationOnly") {
            $reachable = array_filter(
                $reachable,
                fn($_dist, $hexId) => $this->game->hexMap->getHexNamedLocation($hexId) !== "",
                ARRAY_FILTER_USE_BOTH
            );
        } elseif ($filter !== "") {
            $reachable = array_filter(
                $reachable,
                fn($_dist, $hexId) => $this->game->hexMap->getHexTerrain($hexId) === $filter ||
                    $this->game->hexMap->getHexNamedLocation($hexId) === $filter,
                ARRAY_FILTER_USE_BOTH
            );
        }

        return array_keys($reachable);
    }

    function resolve(): void {
        $target = $this->getDataField("target", "") ?: $this->getCheckedArg();
        $hero = $this->game->getHero($this->getOwner());
        // When entering Grimheim, place hero at their home hex
        if ($this->game->hexMap->isInGrimheim($target)) {
            $target = $hero->getRulesFor("location", $target);
        }
        $path = $this->game->hexMap->getPath($hero->getHex(), $target, "hero");

        foreach ($path as $hex) {
            $isFinal = $hex === $target;
            $this->queue("step", null, [
                "hex" => $hex,
                "final" => $isFinal,
                "reason" => $this->getReason(),
            ]);
        }
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
