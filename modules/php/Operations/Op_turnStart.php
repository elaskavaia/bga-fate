<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Model\Event;
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
 * Game::stGameDispatch (next player's turn).
 */
class Op_turnStart extends Operation {
    public function auto(): bool {
        $this->game->switchActivePlayer($this->getPlayerId(), true);
        $this->game->customUndoSavepoint($this->getPlayerId(), 1);
        return parent::auto();
    }
    function resolve(): void {
        $this->queueTrigger(Event::TurnStart);
        $this->queue("turn");
    }
}
