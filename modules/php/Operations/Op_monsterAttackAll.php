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
    public function auto(): bool {
        return true;
    }

    function resolve(): void {
        if ($this->game->isEndOfGame()) {
            return;
        }
        $monsters = $this->game->hexMap->getMonstersOnMap();
        foreach ($monsters as $m) {
            $hex = $this->game->hexMap->getCharacterHex($m["id"]);
            if ($hex === null) {
                continue;
            }
            $monster = $this->game->getMonster($m["id"]);
            if ($this->game->hexMap->isCharacterTypeInRange($hex, $monster->getAttackRange(), "hero")) {
                $this->queue("monsterAttack", null, ["char" => $m["id"]]);
            }
        }
    }
}
