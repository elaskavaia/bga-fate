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

use Bga\Games\Fate\Model\Trigger;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Single hex step of a hero move. Queued by Op_move once per path hex so that
 * per-step triggers (TStep) fire after each moveTo, giving cards like
 * Treetreader II a chance to react to the hex the hero is currently on.
 *
 * Data:
 * - hex: destination hex (required)
 * - final: true for the last step of a move (emits default log message and the
 *   caller-chosen trigger — Move or ActionMove); false for intermediate steps
 *   (silent moveTo, Step trigger)
 */
class Op_step extends Operation {
    function resolve(): void {
        $hex = $this->getDataField("hex", "");
        $this->game->systemAssert("ERR:step:missingHex", $hex !== "");
        $isFinal = (bool) $this->getDataField("final", false);
        $hero = $this->game->getHero($this->getOwner());
        if ($isFinal) {
            $hero->moveTo($hex);
            // Emit the most specific trigger on the final step; ActionMove chains through Move
            $trigger = $this->getReason() == "Op_actionMove" ? Trigger::ActionMove : Trigger::Move;
        } else {
            $hero->moveTo($hex, ""); // suppressed notif
            $trigger = Trigger::Step;
        }

        $this->queueTrigger($trigger);
    }
}
