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
 * spawn(<type>): pull N monster tokens of the given type from supply_monster
 * and place them on free hexes adjacent to the acting hero, walking clockwise
 * from the hero's first neighbour. Stops when either the supply pool or the
 * free-hex ring is exhausted — silent partial spawn.
 *
 * Used by quest side-effects:
 *   killed(trollkin):2spawn(brute):...      // Leather Purse
 *   in(TrollCaves):spawn(troll):gainEquip   // Elven Arrows (sketch)
 *
 * Params:
 *  - count (Countable prefix): number of monsters to spawn (default 1)
 *  - param(0): monster type stem, e.g. "brute" → matches monster_brute_*
 */
class Op_spawn extends CountableOperation {
    function resolve(): void {
        $type = $this->getParam(0, "");
        $this->game->systemAssert("ERR:spawn:noType", $type !== "");
        $count = (int) $this->getCount();

        $heroId = $this->game->getHeroTokenId($this->getOwner());
        $heroHex = $this->game->hexMap->getCharacterHex($heroId);
        $this->game->systemAssert("ERR:spawn:noHeroHex:$heroId", $heroHex !== null);

        $adjHexes = $this->game->hexMap->getAdjacentHexes($heroHex);
        $supplyTokens = $this->game->tokens->getTokensOfTypeInLocation("monster_$type", "supply_monster");

        $placed = 0;
        foreach ($adjHexes as $hex) {
            if ($placed >= $count) {
                break;
            }
            if ($this->game->hexMap->getCharacterOnHex($hex) !== null) {
                continue;
            }
            $monsterId = array_key_first($supplyTokens);
            if ($monsterId === null) {
                break;
            }
            unset($supplyTokens[$monsterId]);
            $this->game->getMonster($monsterId)->moveTo($hex, clienttranslate('${char_name} spawned adjacent to ${char_name2}'), [
                "char_name2" => $heroId,
            ]);
            $placed++;
        }
    }
}
