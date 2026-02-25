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

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Monster turn operation.
 * Runs after all players have completed their turns.
 *
 * Iteration 0: Just advances the time track and checks win condition.
 * Later iterations will add: monster movement, monster attacks, reinforcements.
 */
class Op_turnMonster extends Operation {
    function resolve(): void {
        $this->advanceTimeTrack();
        $this->queueNextRound();
    }

    private function advanceTimeTrack(): void {
        $currentStep = $this->game->tokens->db->getTokenState("rune_stone");
        $nextStep = $currentStep + 1;
        $maxSteps = Material::TIME_TRACK_SHORT_LENGTH;

        $this->game->tokens->dbSetTokenLocation(
            "rune_stone",
            "timetrack_1", // short track
            $nextStep,
            clienttranslate('Rune Stone: time advances to step ${step} of ${max}'),
            [
                "step" => $nextStep,
                "max" => $maxSteps,
            ]
        );
    }

    private function queueNextRound(): void {
        if ($this->game->isEndOfGame()) {
            if ($this->game->isHeroesWin()) {
                // Time track completed — players win!
                $this->game->notify->all("message", clienttranslate("The time track has reached the end. Freyja returns! You win!"), []);
            } else {
                $this->game->notify->all("message", clienttranslate("The heroes have failed. The monster wins!"), []);
            }
            return;
        }

        // Start next round with the first player
        $firstPlayerId = $this->game->getFirstPlayer();
        $this->game->machine->queue("turn", $this->game->custom_getPlayerColorById($firstPlayerId));
    }
}
