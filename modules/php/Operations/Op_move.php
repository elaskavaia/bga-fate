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
use Bga\Games\Fate\Model\Trigger;
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
 * - param(0): "locationOnly" — destinations restricted to hexes that belong to a
 *   named location (DarkForest, Grimheim, TempleRuins, …).
 *
 * Used by: Agility (2move), Maneuver (1move), Fleetfoot (1move),
 * Quick Reflexes (1move), Seek Shelter ([0,2]move(locationOnly)).
 */
class Op_move extends CountableOperation {
    function getPrompt() {
        return clienttranslate("Select where to move");
    }

    private function isLocationOnly(): bool {
        return $this->getParam(0, "") === "locationOnly";
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

        if ($this->isLocationOnly()) {
            $reachable = array_filter(
                $reachable,
                fn($_dist, $hexId) => $this->game->hexMap->getHexNamedLocation($hexId) !== "",
                ARRAY_FILTER_USE_BOTH
            );
        }

        $moves = [];
        foreach (array_keys($reachable) as $hexId) {
            $moves[$hexId] = ["q" => Material::RET_OK];
        }
        return $moves;
    }

    function resolve(): void {
        $target = $this->getDataField("target", "") ?: $this->getCheckedArg();
        $hero = $this->game->getHero($this->getOwner());
        // When entering Grimheim, place hero at their home hex
        if ($this->game->hexMap->isInGrimheim($target)) {
            $target = $hero->getRulesFor("location", $target);
        }
        $hero->moveTo($target);
        // Emit the most specific trigger; ActionMove chains through Move so cards
        // listening on EventMove are still offered during action-move resolutions.
        $trigger = $this->getReason() == "Op_actionMove" ? Trigger::ActionMove : Trigger::Move;
        $this->queueTrigger($trigger);
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
