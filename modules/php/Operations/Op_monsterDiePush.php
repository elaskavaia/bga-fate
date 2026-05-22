<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\Operation;

/**
 * Monster Die `push` side: every hero adjacent to ≥1 monster is pushed one hex
 * toward Grimheim, following the same per-hex `dir` tag monsters use during
 * movement (HexMap::getMonsterNextHex). Heroes already in Grimheim are skipped.
 *
 * A3: if the next hex is missing (`null`) or occupied, the hero stays put — no
 * collision damage, no alt-target. Heroes entering Grimheim is a regular move
 * (no house destruction; that rule is monster-specific).
 *
 * Queued from Op_rollMonsterDie when side 4 (`push`) is rolled.
 */
class Op_monsterDiePush extends Operation {
    function resolve(): void {
        foreach ($this->game->getPlayerColors() as $color) {
            $heroId = $this->game->getHeroTokenId($color);
            $heroHex = $this->game->hexMap->getCharacterHex($heroId);
            if ($heroHex === null || $this->game->hexMap->isInGrimheim($heroHex)) {
                continue;
            }
            if (!$this->heroIsAdjacentToMonster($heroHex)) {
                continue;
            }
            $nextHex = $this->game->hexMap->getMonsterNextHex($heroHex);
            if ($nextHex === null || $this->game->hexMap->isOccupied($nextHex)) {
                continue;
            }
            $hero = $this->game->getHero($color);
            // Mountains/lakes block heroes per the normal terrain rules — push can't
            // shove them onto a hex they couldn't otherwise stand on (FORUM #4).
            if ($this->game->hexMap->isImpassable($nextHex, $hero)) {
                continue;
            }
            $hero->moveTo($nextHex, clienttranslate('${char_name} is pushed toward Grimheim'));
        }
    }

    private function heroIsAdjacentToMonster(string $heroHex): bool {
        foreach ($this->game->hexMap->getAdjacentHexes($heroHex) as $adj) {
            if ($this->game->hexMap->isOccupiedByCharacterType($adj, "monster") !== null) {
                return true;
            }
        }
        return false;
    }
}
