<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\Operation;

/**
 * End of monster turn — check for end of game, then start next player round.
 *
 * Runs after all monster movement, attacks, and reinforcements are complete.
 */
class Op_endOfMonsterTurn extends Operation {
    function resolve(): void {
        // Sweep the monster die back to supply so its rolled side doesn't leak
        // into hero-turn code paths that read getMonsterDieSide().
        if ($this->game->tokens->getTokenLocation("die_monster") === "display_monsterturn") {
            $this->game->tokens->dbSetTokenLocation("die_monster", "supply_die_monster", 6, "");
        }

        if ($this->game->isEndOfGame()) {
            $this->game->handleEndOfGame();
            return;
        }

        // Start next round with the first player
        $firstPlayerId = $this->game->getFirstPlayer();
        $this->game->machine->queue("turnStart", $this->game->custom_getPlayerColorById($firstPlayerId));
    }
}
