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

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Model\Event;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Monster turn operation.
 * Runs after all players have completed their turns.
 *
 * Steps (per rules): 1. Advance time marker, 2. Monster dice (variant), 3. Monsters move,
 * 4. Monsters attack, 5. Reinforcements (if on axes/skull spot).
 */
class Op_turnMonster extends Operation {
    function resolve(): void {
        $this->cleanupMonsterDisplay();
        $this->advanceTimeTrack();
        $spotType = $this->getCurrentSpotType();
        $isChargeTurn = $spotType === "tm_red_skull";
        // TODO: Step 2 — Roll Monster Dice (variant rule for higher difficulty)
        // Effects: maneuver CW/CCW, attack +1, charge rank 1, push, ambush
        // Pre-movement trigger: Suppressive Fire and similar abilities
        foreach ($this->game->getPlayerColors() as $color) {
            $this->queueTrigger(Event::MonsterMove, $color);
        }
        $this->queue("monsterMoveAll", null, ["charge" => $isChargeTurn]);
        $this->queue("monsterAttackAll");
        if ($spotType === "tm_yellow_axes") {
            $this->queue("reinforcement", null, ["deck" => "deck_monster_yellow"]);
        } elseif ($spotType === "tm_red_axes") {
            $this->queue("reinforcement", null, ["deck" => "deck_monster_red"]);
        }
        $this->queue("endOfMonsterTurn");
    }

    /**
     * Get the time track spot type for the current step.
     */
    private function getCurrentSpotType(): string {
        $step = $this->game->tokens->getTokenState("rune_stone");
        $track = $this->game->tokens->getTokenLocation("rune_stone");
        $slotId = "slot_{$track}_{$step}";
        return $this->game->tokens->getRulesFor($slotId, "r", "tm_yellow_shield");
    }

    private function cleanupMonsterDisplay(): void {
        $cards = $this->game->tokens->getTokensOfTypeInLocation("card_monster", "display_monsterturn");
        foreach ($cards as $card) {
            $cardId = $card["key"];
            $deck = $this->game->tokens->getRulesFor($cardId, "location");
            $minState = $this->game->tokens->getExtremePosition(false, $deck);
            $this->game->tokens->dbSetTokenLocation($cardId, $deck, $minState - 1, ""); // no notify text
        }
    }

    private function advanceTimeTrack(): void {
        $currentStep = $this->game->tokens->getTokenState("rune_stone");
        $nextStep = $currentStep + 1;
        $maxSteps = $this->game->getTimeTrackLength();

        $this->game->tokens->dbSetTokenLocation(
            "rune_stone",
            "timetrack_1", // TODO: support timetrack_2 for long track variant
            $nextStep,
            clienttranslate('Rune Stone advances to step ${step} of ${max}'),
            [
                "step" => $nextStep,
                "max" => $maxSteps,
            ]
        );
    }
}
