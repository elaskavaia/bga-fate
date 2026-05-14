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

        $monsters = $this->game->hexMap->getMonstersOnMap();

        foreach ($monsters as $m) {
            $monsterId = $m["key"];
            $hex = $this->game->hexMap->getCharacterHex($monsterId);

            // Seer of Odin (II) attacks every hero regardless of range — bypass the range gate.
            if (
                $monsterId === "monster_legend_2_2" ||
                $this->game->hexMap->isCharacterTypeInRange($hex, $this->game->getMonster($monsterId)->getAttackRange(), "hero")
            ) {
                $this->queue("monsterAttack", null, ["char" => $monsterId]);
            }
        }
    }
}
