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
 * - final: true for the last step of a move (emits default log message and, after
 *   Step, the caller-chosen trigger - Move or ActionMove); false for intermediate
 *   steps (silent moveTo, Step trigger only)
 */
class Op_step extends Operation {
    function resolve(): void {
        $hex = $this->getDataField("hex", "");
        $this->game->systemAssert("ERR:step:missingHex", $hex !== "");
        $isFinal = (bool) $this->getDataField("final", false);
        $hero = $this->game->getHero($this->getOwner());
        $this->game->incPlayerStat($this->getOwner(), "game_moves");
        // Entering Grimheim always ends the move, but in step mode that last hop is not "final"
        if (!$this->game->hexMap->isInGrimheim((string) $hero->getHex()) && $this->game->hexMap->isInGrimheim($hex)) {
            $this->game->incPlayerStat($this->getOwner(), "game_grimheim_visits");
        }
        if ($isFinal) {
            $hero->moveTo($hex);
        } else {
            $hero->moveTo($hex, ""); // suppressed notif
        }

        $this->queue("encounter", $this->getOwner(), ["hex" => $hex]);
        $this->queueTrigger(Trigger::Step);
        if ($isFinal) {
            $this->queueTrigger($this->getReason() == "Op_actionMove" ? Trigger::ActionMove : Trigger::Move);
        }
    }
}
