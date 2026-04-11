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

/**
 * killMonster: Instantly kill a target monster matching a filter condition.
 * Extends dealDamage — same target selection (range + filter), but deals enough
 * damage to guarantee a kill (monster's full health).
 *
 * Params:
 * - param(0): range specifier — "adj", "inRange", "inRangeN" (default "adj")
 * - param(1): filter expression — e.g. "'rank<=2'", "'healthRem<=2'" (default "true")
 *
 * Used by: Back Down (killMonster(inRange,'rank<=2')), Short Temper (killMonster(adj,'healthRem<=2')).
 */
class Op_killMonster extends Op_dealDamage {
    function getPrompt() {
        return clienttranslate("Choose a monster to kill");
    }

    function canSkip() {
        return true;
    }

    protected function getDamageAmount(string $defenderId): int {
        $monster = $this->game->getMonster($defenderId);
        return $monster->getHealth();
    }
}
