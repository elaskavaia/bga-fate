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
 * Move hero up to X areas. Reuses getReachableHexes() from HexMap with
 * count as maxSteps.
 *
 * "Move X" is always "up to X" per DESIGN.md #11 (designer-confirmed). All
 * reachable distances 1..count are offered; the player picks how far to go.
 * mcount governs skippability only: mcount=0 (e.g. "[0,3]move") means the
 * action can be skipped entirely; mcount>0 means the player must pick a
 * destination (but any distance 1..count is fine).
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
        return clienttranslate("Choose where to move");
    }

    function getPossibleMoves(): array {
        $target = $this->getDataField("target", "");
        if ($target) {
            return [$target];
        }
        $owner = $this->getOwner();
        $hero = $this->game->getHero($owner);
        $maxSteps = (int) $this->getCount();
        $filter = $this->getParam(0, "");

        // Wrecking Ball works on any movement (designer: "always active"): unfiltered
        // moves delegate to the step loop, which owns the occupied-hex targets. Filtered moves
        // keep the one-click flow for now (destination filter; see PLAN_WRECKING_MOVE.md).
        if ($filter === "" && $hero->getWreckingCard() !== null) {
            return $this->getPossibleMovesDelegate("moveStep", ["budget" => $maxSteps, "moved" => 0]);
        }

        $currentHex = $this->game->tokens->getTokenLocation($hero->getId());
        $reachable = $this->game->hexMap->getReachableHexes($currentHex, $maxSteps, $hero);
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
        $presetTarget = $this->getDataField("target", "");
        $target = $presetTarget ?: $this->getCheckedArg();
        $hero = $this->game->getHero($this->getOwner());

        // Wrecking Ball: hand over to the step loop (see getPossibleMoves). Preset
        // targets are scripted moves and keep the direct path.
        if ($presetTarget === "" && $this->getParam(0, "") === "" && $hero->getWreckingCard() !== null) {
            $this->queue("moveStep", null, [
                "budget" => (int) $this->getCount(),
                "moved" => 0,
                "target" => $target,
                "reason" => $this->getReason(),
            ]);
            return;
        }

        // When entering Grimheim, place hero at their home hex
        if ($this->game->hexMap->isInGrimheim($target)) {
            $target = $hero->getRulesFor("location", $target);
        }
        $from = $hero->getHex();
        $path = $this->game->hexMap->getPath($from, $target, $hero);
        // A move that queues no steps would spend the action for nothing (BGA #235653).
        $this->game->systemAssert("ERR:move:emptyPath:$from:$target", count($path) > 0);

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
