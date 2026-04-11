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
 * set attribute (temp)
 *
 * Automated op: no user input. Count becomes the new value, which can legitimately be 0
 * (e.g. "0setAtt(move)" to block movement for the rest of the turn). The default
 * CountableOperation::getPossibleMoves() returns empty when count==0 and canSkip()==true,
 * which would cause this op to auto-skip without resolving. Override to always present
 * a single confirm target so resolve() runs regardless of count.
 */
class Op_setAtt extends CountableOperation {
    function getAttribute() {
        return $this->getParam(0, "move");
    }

    function getPossibleMoves(): array {
        return ["confirm"];
    }

    function canSkip() {
        // Automated set: never skippable. Without this, CountableOperation::canSkip() returns
        // true whenever mcount==0, which would send "0setAtt(move)" to the player state instead
        // of auto-resolving.
        return false;
    }

    function resolve(): void {
        $owner = $this->getOwner();
        $amount = (int) $this->getCount();
        $prev = $this->game->tokens->getTrackerValue($owner, $this->getAttribute());
        $inc = $amount - $prev;
        // don't have setTrackerValue - use this hack
        $this->game->tokens->incTrackerValue($owner, $this->getAttribute(), $inc);
    }
}
