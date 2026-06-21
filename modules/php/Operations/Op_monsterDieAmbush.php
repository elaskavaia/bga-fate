<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\Operation;

/**
 * Monster Die `ambush` side: each hero gets a goblin spawned on an adjacent
 * hex. Heroes in Grimheim are skipped (A6 of MDICE.md). Empty supply / no free
 * adjacent hex is also a silent skip — Op_spawn already handles both.
 *
 * Queued from Op_rollMonsterDie when side 6 (`ambush`) is rolled.
 *
 * Per A7: a single op handles all heroes by queueing one Op_spawn(goblin) per
 * hero (owner = hero's color). Each hero's player then places the goblin on a
 * chosen adjacent hex (RULES.md "Ambush"), matching the Leather Purse / Elven
 * Arrows spawn flow.
 */
class Op_monsterDieAmbush extends Operation {
    function resolve(): void {
        foreach ($this->game->getPlayerColors() as $color) {
            $heroId = $this->game->getHeroTokenId($color);
            $heroHex = $this->game->hexMap->getCharacterHex($heroId);
            if ($heroHex === null || $this->game->hexMap->isInGrimheim($heroHex)) {
                continue;
            }
            $this->queue("spawn(goblin)", $color);
        }
    }
}
