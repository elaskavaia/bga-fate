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
 * Steps (per rules): 1. Advance time marker, 2. Monster dice (skip), 3. Monsters move (TODO),
 * 4. Monsters attack (TODO), 5. Reinforcements (if on axes spot).
 */
class Op_turnMonster extends Operation {
    function resolve(): void {
        $this->cleanupMonsterDisplay();
        $this->advanceTimeTrack();
        $this->queueReinforcements();
        $this->queueNextRound();
    }

    private function cleanupMonsterDisplay(): void {
        $cards = $this->game->tokens->getTokensOfTypeInLocation("card_monster", "display_monsterturn");
        foreach ($cards as $card) {
            $cardId = $card["key"];
            $deck = $this->game->tokens->getRulesFor($cardId, "location");
            $minState = $this->game->tokens->db->getExtremePosition(false, $deck);
            $this->game->tokens->dbSetTokenLocation($cardId, $deck, $minState - 1, ""); // no notify text
        }
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

    private function queueReinforcements(): void {
        $step = $this->game->tokens->db->getTokenState("rune_stone");
        $track = $this->game->tokens->db->getTokenLocation("rune_stone");
        $slotId = "slot_{$track}_{$step}";
        $spotType = $this->game->tokens->getRulesFor($slotId, "r", "tm_yellow_shield");
        if ($spotType === "tm_yellow_axes") {
            $this->queue("reinforcement", null, ["deck" => "deck_monster_yellow"]);
        } elseif ($spotType === "tm_red_axes") {
            $this->queue("reinforcement", null, ["deck" => "deck_monster_red"]);
        }
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
