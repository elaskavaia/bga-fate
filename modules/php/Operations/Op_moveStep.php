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
 * moveStep: budgeted, step-by-step variant of the move action used when the hero has an
 * active per-step (TStep) incentive (a quest like Raven's Claw, or a tableau card like
 * Treetreader II). See DESIGN.md "Step-by-step Move".
 *
 * Each prompt offers every hex reachable within the remaining budget (so a far click still
 * works as a fast "go there") PLUS an "End Move" sentinel. After resolving a click the loop
 * re-queues itself with the reduced budget, letting the player keep stepping deliberately
 * (including back and forth to re-enter forests) until the budget runs out or they end the
 * move. All hops are emitted as non-final Op_step (TStep); the final ActionMove/Move trigger
 * fires once, on "End Move" / budget exhaustion / entering Grimheim.
 *
 * Data:
 * - budget: steps remaining (starts at Hero::getNumberOfMoves()).
 * - moved: steps taken so far; "End Move" is only offered once moved >= 1 so the action keeps
 *   the "[1,N]move" minimum of one area.
 * - target: optional preset for the first hop (passed by Op_actionMove from the action click).
 * - reason: propagated so the closing trigger is ActionMove (vs Move).
 *
 * Queued by: Op_actionMove (when Hero has a step incentive).
 */
class Op_moveStep extends Operation {
    private function getBudget(): int {
        return (int) $this->getDataField("budget", 0);
    }

    private function getMoved(): int {
        return (int) $this->getDataField("moved", 0);
    }

    function getPrompt() {
        return clienttranslate('Move: choose where to go (${count} step(s) left) or end the move');
    }

    function getExtraArgs() {
        return ["count" => $this->getBudget()];
    }

    function getPossibleMoves(): array {
        $preset = $this->getDataField("target", "");
        if ($preset !== "") {
            return [$preset];
        }
        $hero = $this->game->getHero($this->getOwner());
        $budget = $this->getBudget();

        $targets = [];
        if ($budget > 0) {
            $targets = array_keys($this->game->hexMap->getReachableHexes($hero->getHex(), $budget, $hero));
        }
        // Offer the early-stop only after at least one step (keeps the "move at least 1 area" minimum).
        if ($this->getMoved() >= 1) {
            $targets["endOfMove"] = ["q" => 0, clienttranslate("End Move")];
        }
        return $targets;
    }

    function resolve(): void {
        $hero = $this->game->getHero($this->getOwner());
        $arg = $this->getDataField("target", "") ?: $this->getCheckedArg();

        if ($arg === "endOfMove") {
            $this->queueFinalTrigger();
            return;
        }

        // Entering Grimheim sends the hero home and ends the move.
        $enteringGrimheim = $this->game->hexMap->isInGrimheim($arg);
        $target = $enteringGrimheim ? $hero->getRulesFor("location", $arg) : $arg;

        $path = $this->game->hexMap->getPath($hero->getHex(), $target, $hero);
        foreach ($path as $hex) {
            $this->queue("step", null, [
                "hex" => $hex,
                "final" => false, // intermediate hop; the closing trigger fires once, below
                "reason" => $this->getReason(),
            ]);
        }

        $remaining = $this->getBudget() - count($path);
        if ($enteringGrimheim || $remaining <= 0) {
            $this->queueFinalTrigger();
            return;
        }
        $this->queue("moveStep", null, [
            "budget" => $remaining,
            "moved" => $this->getMoved() + count($path),
            "reason" => $this->getReason(),
        ]);
    }

    private function queueFinalTrigger(): void {
        $trigger = $this->getReason() === "Op_actionMove" ? Trigger::ActionMove : Trigger::Move;
        $this->queueTrigger($trigger);
    }

    public function isTrivial(): bool {
        return $this->isOneChoice();
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
