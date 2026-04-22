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

use Bga\Games\Fate\OpCommon\Operation;

/**
 * performAction: Queue an additional main action.
 * Parameter specifies which action to queue (e.g., actionAttack, actionMend).
 * Used by: Speedy Attack, Rapid Strike, Trinket.
 */
class Op_performAction extends Operation {
    function getPossibleMoves() {
        $actionType = $this->getParam(0, "nop");
        $this->game->systemAssert("ERR:performAction:noActionType", $actionType !== null);
        return $this->getPossibleMovesDelegate($actionType);
    }
    function resolve(): void {
        $actionType = $this->getParam(0);
        $this->queue($actionType);
    }
}
