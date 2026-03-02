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
 * Practice action: add 1 experience (yellow crystal) to the player board.
 */
class Op_actionPractice extends Operation {
    function resolve(): void {
        // - Increment experience counter on player board
        $owner = $this->getOwner();
        $this->game->effect_moveCrystals("yellow", 1, "tableau_$owner");
    }
}
