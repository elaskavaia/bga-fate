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

use Bga\Games\Fate\OpCommon\Operation;

use function Bga\Games\Fate\getPart;

/**
 * Monster attack: a single monster attacks an adjacent hero.
 * Queued from Op_turnMonster with data ["char" => $monsterId].
 */
class Op_monsterAttack extends Operation {
    function resolve(): void {
        $monsterId = $this->getDataField("char", "");
        $this->game->systemAssert("Missing monster ID in monsterAttack", $monsterId);

        // Check monster is still alive (on the map)
        $monster = $this->game->getMonster($monsterId);
        $monsterHex = $monster->getHex();
        if ($monsterHex === null) {
            return; // Monster was killed or removed
        }

        // Calculate monster strength with faction bonus and queue roll pipeline
        $strength = $this->getMonsterStrength($monsterHex);

        $heroHex = $this->getDataField("target", ""); // defender  hex

        if (!$heroHex) {
            // Find heroes in attack range
            // Check via roll op whether this hero can be attacked on this hex
            $rollOp = $this->instanciateOperation("roll", null, ["attacker" => $monsterId]);
            $hexes = $rollOp->getArgs()["target"];

            if (empty($hexes)) {
                return; // No heroes to attack
            }

            // TODO: Hero selection — currently picks first
            // Rules may require different targeting logic (e.g. closest, random, player choice).
            $heroHex = $this->pickTarget($hexes);
        }

        $this->game->tokens->dbSetTokenLocation("marker_attack", $heroHex, 0, "");
        $this->queue("roll", null, [
            "attacker" => $monsterId,
            "target" => $heroHex,
            "count" => $strength,
        ]);
        $this->queue("endOfAttack");
    }

    /**
     * Pick first
     */
    private function pickTarget(array $hexes): string {
        return $hexes[0];
    }

    /**
     * Get monster attack strength including Trollkin faction bonus.
     * Trollkin monsters get +1 for each other adjacent Trollkin monster near the target hero.
     */
    private function getMonsterStrength(string $monsterHex): int {
        $monsterId = $this->game->hexMap->getCharacterOnHex($monsterHex);
        $strength = (int) $this->game->getRulesFor($monsterId, "strength", 1);
        $faction = $this->game->getRulesFor($monsterId, "faction", "");

        if ($faction === "trollkin") {
            //**Trollkin Effect:** All trollkin get +1 attack strength for each other trollkin adjacent to them.

            $adjacentHexes = $this->game->hexMap->getAdjacentHexes($monsterHex);
            foreach ($adjacentHexes as $hex) {
                $char = $this->game->hexMap->getCharacterOnHex($hex);
                if ($char !== null && $char !== $monsterId && getPart($char, 0) === "monster") {
                    $otherFaction = $this->game->getRulesFor($char, "faction", "");
                    if ($otherFaction === $faction) {
                        $strength++;
                    }
                }
            }
        }

        return $strength;
    }
}
