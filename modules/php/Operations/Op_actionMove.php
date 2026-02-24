<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
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
 * Move action: hero moves up to 3 areas on the hex board.
 */
class Op_actionMove extends Operation {
    function getPossibleMoves() {
        // TODO: calculate reachable hexes for the active hero
        // - Hero can move up to 3 areas
        // - Cannot move through occupied areas (other heroes or monsters)
        // - Cannot move into mountains or lakes
        // - Entering Grimheim ends movement
        // - Exiting Grimheim can go to any adjacent non-mountain area
        return ["confirm"];
    }

    function resolve(): void {
        // TODO: implement move resolution
        // - Move hero token to selected hex
        // - Animate movement along path on client
    }
}
