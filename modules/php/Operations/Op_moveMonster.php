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
 * moveMonster: Move target monster X areas (count = max steps).
 *
 * Rules:
 * - "Each area can only hold 1 character at a time, and that area is then occupied.
 *    No character may move through occupied areas."
 * - "Characters may never move, or be moved, off the map."
 *
 * Two-phase operation:
 * - Phase 1 (no "target" data field): player selects which monster to move.
 *   Targets filtered by range param (adj/inRange), same pattern as dealDamage.
 * - Phase 2 ("target" data field set): player selects destination hex.
 *   Uses getReachableHexes() with count as maxSteps.
 *
 * Assumption: player will not select Grimheim as destination.
 *
 * Used by: Kick (dealDamage(adj);moveMonster), Swift Kick, Bowling
 */
class Op_moveMonster extends CountableOperation {
    private function getMonsterHex(): ?string {
        return $this->getDataField("target");
    }

    private function getRange(): int {
        $hero = $this->game->getHero($this->getOwner());
        return $hero->getRangeFromParam($this->getParam(0, "adj"));
    }

    function getPrompt() {
        if ($this->getMonsterHex()) {
            return clienttranslate("Select where to move the monster");
        }
        return clienttranslate("Select a monster to move");
    }

    function getPossibleMoves(): array {
        $monsterHex = $this->getMonsterHex();
        if ($monsterHex) {
            return $this->getDestinationMoves($monsterHex);
        }
        $hero = $this->game->getHero($this->getOwner());
        $hexes = $hero->getMonsterHexesInRange($this->getRange());
        return $hexes;
    }

    private function getDestinationMoves(string $monsterHex): array {
        $maxSteps = (int) $this->getCount();
        $minSteps = (int) $this->getMinCount();
        $reachable = $this->game->hexMap->getReachableHexes($monsterHex, $maxSteps, "monster");

        // Filter out Grimheim hexes (assumption: player cannot push monster into Grimheim)
        foreach (array_keys($reachable) as $hex) {
            if ($this->game->hexMap->isInGrimheim($hex)) {
                unset($reachable[$hex]);
            }
        }

        // For mandatory movement (minSteps == maxSteps), only offer hexes at the
        // required distance. If none exist, fall back to farthest reachable.
        if ($minSteps > 0 && $minSteps == $maxSteps && count($reachable) > 0) {
            $maxReachableDist = max($reachable);
            $requiredDist = min($maxSteps, $maxReachableDist);
            $reachable = array_filter($reachable, fn($dist) => $dist == $requiredDist);
        }

        $moves = [];
        foreach (array_keys($reachable) as $hexId) {
            $moves[$hexId] = ["q" => Material::RET_OK];
        }
        return $moves;
    }

    function resolve(): void {
        $targetHex = $this->getCheckedArg();
        $monsterHex = $this->getMonsterHex();
        if ($monsterHex) {
            // Phase 2: move the monster
            $monsterId = $this->game->hexMap->getCharacterOnHex($monsterHex, null);
            $this->game->systemAssert("ERR:moveMonster:noMonsterOnHex:$monsterHex", $monsterId !== null);
            $this->game->getMonster($monsterId)->moveTo($targetHex);
        } else {
            // Phase 1: queue phase 2 with monster hex stored
            $this->queue("moveMonster", null, [
                "target" => $targetHex,
                "count" => $this->getCount(),
                "mcount" => $this->getMinCount(),
            ]);
        }
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
