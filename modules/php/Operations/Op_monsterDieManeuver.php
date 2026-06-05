<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\Operation;

/**
 * Monster Die `maneuver_1` / `maneuver_2` side: every monster adjacent to a
 * hero rotates one hex around that hero — clockwise (param=cw) for side 1,
 * counter-clockwise (param=ccw) for side 2.
 *
 * Per FORUM #3 (designer) and #2 (HORST324 thread):
 *  - Trigger the effect for every hero, in player order — a monster adjacent
 *    to multiple heroes rotates once per hero (chain effects).
 *  - Within one hero's group: simultaneous. Monsters don't block each other;
 *    only another hero on the destination hex (or off-map / Grimheim) matters.
 *  - May rotate into Grimheim (destroys town piece, removes monster).
 *  - May diverge from path / step further from Grimheim.
 *
 * Queued from Op_rollMonsterDie when side 1 or 2 is rolled.
 *
 * Params:
 *  - param(0): "cw" or "ccw"
 */
class Op_monsterDieManeuver extends Operation {
    function resolve(): void {
        $dir = $this->getParam(0, "");
        $this->game->systemAssert("ERR:monsterDieManeuver:badDir:$dir", $dir === "cw" || $dir === "ccw");
        $clockwise = $dir === "cw";

        // "in player order" starting from the first player.
        foreach ($this->game->getPlayerColorsInOrder() as $color) {
            $heroHex = $this->game->hexMap->getCharacterHex($this->game->getHeroTokenId($color));
            if ($heroHex === null || $this->game->hexMap->isInGrimheim($heroHex)) {
                continue;
            }
            $this->maneuverAround($heroHex, $clockwise);
            if ($this->game->isWellDestroyed()) {
                return;
            }
        }
    }

    /**
     * Simultaneous rotation of every monster currently adjacent to $heroHex.
     * Snapshot first, lift everyone off the occupancy map, then re-place each
     * at its destination — so members of the same group don't block each
     * other (FORUM #2). DB writes only happen for monsters that actually move.
     */
    private function maneuverAround(string $heroHex, bool $clockwise): void {
        $moves = [];
        foreach ($this->game->hexMap->getAdjacentHexes($heroHex) as $adj) {
            $monsterId = $this->game->hexMap->isOccupiedByCharacterType($adj, "monster");
            if ($monsterId === null) {
                continue;
            }
            $target = $this->game->hexMap->rotateAroundCenter($heroHex, $adj, $clockwise);
            if ($target === null) {
                continue; // off-map ring position
            }
            $moves[] = ["monster" => $monsterId, "from" => $adj, "to" => $target];
        }
        if (!$moves) {
            return;
        }

        // Lift rotating monsters off the occupancy map — DB still points at $from,
        // so a no-op (stay-put) needs only an occupancy restore, not a DB write.
        foreach ($moves as $move) {
            $this->game->hexMap->moveCharacterOnMap($move["monster"], null);
        }

        foreach ($moves as $move) {
            $this->applyOneMove($move["monster"], $move["from"], $move["to"]);
            if ($this->game->isWellDestroyed()) {
                return;
            }
        }
    }

    private function applyOneMove(string $monsterId, string $fromHex, string $toHex): void {
        if ($this->game->hexMap->isInGrimheim($toHex)) {
            $this->game->effect_monsterEntersGrimheim($monsterId);
            return;
        }
        // Heroes still on the map (and monsters from outside the rotating group)
        // block the destination. Stay put — restore occupancy to fromHex.
        if ($this->game->hexMap->isOccupied($toHex)) {
            $this->game->hexMap->moveCharacterOnMap($monsterId, $fromHex);
            return;
        }
        $this->game->getMonster($monsterId)->moveTo($toHex, clienttranslate('${char_name} maneuvers around the hero'));
    }
}
