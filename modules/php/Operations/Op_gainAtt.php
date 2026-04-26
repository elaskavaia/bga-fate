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

use function Bga\Games\Fate\getPart;

/**
 * gain attribute (temp)
 */
class Op_gainAtt extends CountableOperation {
    public function getPossibleMoves() {
        return ["confirm"]; // overrid parent because we don't need to confirm count
    }
    function getAttribute() {
        return getPart($this->getType(), 1, true) ?: $this->getParam() ?: "strength";
    }

    function resolve(): void {
        $owner = $this->getOwner();
        $amount = (int) $this->getCount();
        $this->game->tokens->incTrackerValue($owner, $this->getAttribute(), $amount);
    }
}
