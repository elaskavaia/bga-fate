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
        if ($this->game->isEndOfGame()) {
            return;
        }
        $monsters = $this->game->hexMap->getMonstersOnMap();
        $queued = 0;
        foreach ($monsters as $m) {
            $hex = $this->game->hexMap->getCharacterHex($m["key"]);
            if ($hex === null) {
                continue;
            }
            $monster = $this->game->getMonster($m["key"]);
            if ($this->game->hexMap->isCharacterTypeInRange($hex, $monster->getAttackRange(), "hero")) {
                $this->queue("monsterAttack", null, ["char" => $m["key"]]);
                $queued++;
            }
        }

        // Announce a wasted attack-side roll so players see why nothing happened.
        if ($queued === 0 && $this->game->getMonsterDieSide() === "attack") {
            $this->game->notify->all("message", clienttranslate('Monsters get +1 strength but have no targets this turn'), []);
        }
    }
}
