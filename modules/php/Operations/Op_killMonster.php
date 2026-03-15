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

/**
 * killMonster: Kill a target monster matching a filter condition (rank, health, range).
 * Used by: Back Down (killMonster(inRange,'rank<=2')), Short Temper, Heat Death, In Charge.
 *
 * param(0): range filter — "inRange", "adj"
 * param(1): condition filter — e.g. 'rank<=2', 'adj && healthRem<=2'
 */
class Op_killMonster extends Operation {
    function resolve(): void {
        // TODO: implement
    }
}
