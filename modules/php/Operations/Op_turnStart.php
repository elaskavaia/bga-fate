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
 * turnStart: Fires trigger(turnStart) at the beginning of a player's turn,
 * then queues the main `turn` operation.
 *
 * Behaviour:
 * - Automated: no user choice
 * - Fires queueTrigger("turnStart") for cards with passive start-of-turn effects
 *   (e.g. Fortified: heal 1, Tiara: gain 1 gold each turn)
 * - Then queues "turn" to begin the player's actual action selection
 *
 * Used by: Game::setupGameTables (initial turn), Op_endOfMonsterTurn (next round),
 * Game::queueNextTurnOrEnd (next player's turn).
 */
class Op_turnStart extends Operation {
    function resolve(): void {
        // Solo has no seat switch to anchor a barrier, so undo would cross the monster turn.
        // Turn 1 (rune stone on step 1) is covered by the immediate setup barrier.
        if ($this->game->isSolo() && $this->game->tokens->getTokenState("rune_stone") > 1) {
            $this->game->customUndoSavepoint($this->getPlayerId(), 1);
        }
        $this->queueTrigger(Trigger::TurnStart);
        $this->queue("turn", null, ["reason" => ""]);
    }
}
