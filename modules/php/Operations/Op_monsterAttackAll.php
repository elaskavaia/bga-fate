<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\Operation;

/**
 * Queue monster attacks for all monsters adjacent to heroes.
 *
 * Automated operation — iterates all monsters on the map and queues
 * a monsterAttack operation for each one that has a hero in attack range.
 * Must run after monsterMoveAll so positions are up to date.
 */
class Op_monsterAttackAll extends Operation {
    function resolve(): void {
        if ($this->game->isWellDestroyed()) {
            return;
        }

        // Bucket each monster by the hero it would attack so consecutive
        // monsterAttack ops resolve against the same defender. Seer of Odin (II)
        // has no single target and goes into its own auto-indexed slot.
        $byHero = [];
        foreach ($this->game->hexMap->getMonstersOnMap() as $m) {
            $monsterId = $m["key"];
            if ($monsterId === "monster_legend_2_2") {
                $byHero[][] = $monsterId;
                continue;
            }
            /** @var Op_monsterAttack $attackOp */
            $attackOp = $this->instantiateOperation("monsterAttack", null, ["char" => $monsterId]);
            $heroId = $attackOp->findHeroTarget();
            if ($heroId === null) {
                continue;
            }
            $byHero[$heroId][] = $monsterId;
        }

        foreach ($byHero as $heroId => $monsterIds) {
            foreach ($monsterIds as $monsterId) {
                $this->queue("monsterAttack", null, ["char" => $monsterId, "target" => $this->game->hexMap->getCharacterHex($heroId)]);
            }
        }
    }
}
