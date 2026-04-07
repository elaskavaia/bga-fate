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

use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 * gain attribute (temp)
 */
class Op_gainAtt extends CountableOperation {
    function getAttribute() {
        return $this->getParam(0, "strength");
    }

    function resolve(): void {
        $owner = $this->getOwner();
        $amount = (int) $this->getCount();
        $this->game->tokens->incTrackerValue($owner, $this->getAttribute(), $amount);
    }
}
