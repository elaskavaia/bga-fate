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
 * works as a fast "go there"), occupied hexes Wrecking Ball can move into within the budget
 * is on the tableau (auto-route adjacent, step in, push the occupant, deal 1 damage —
 * see Op_c_wrecking), PLUS an "End Move" sentinel. After resolving a click the loop
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
        return clienttranslate('Choose next step or end the move (${count} left)');
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
            if ($hero->getWreckingCard() !== null) {
                $targets = array_merge($targets, $this->getWreckingTargets($budget));
            }
        }
        // Offer the early-stop only after at least one step (keeps the "move at least 1 area" minimum).
        if ($this->getMoved() >= 1) {
            $targets["endOfMove"] = ["q" => 0, "name" => clienttranslate("End Move")];
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

        // Wrecking Ball move into an occupied hex: walk adjacent to the occupied hex if needed (auto-route), step
        // into it (transient multi-occupancy), then c_wrecking pushes the occupant and deals
        // the damage, and the loop continues with the remaining budget.
        $occupant = $hero->getWreckingCard() !== null ? $this->game->hexMap->getCharacterOnHex($arg) : null;
        if ($occupant !== null) {
            $stepsUsed = 1;
            if ($this->game->hexMap->getHexDistance($hero->getHex(), $arg) > 1) {
                $approach = $this->findWreckingApproach($arg);
                $path = $this->game->hexMap->getPath($hero->getHex(), $approach, $hero);
                foreach ($path as $hex) {
                    $this->queue("step", null, [
                        "hex" => $hex,
                        "final" => false,
                        "reason" => $this->getReason(),
                    ]);
                }
                $stepsUsed += count($path);
            }
            $this->queue("step", null, [
                "hex" => $arg,
                "final" => false,
                "reason" => $this->getReason(),
            ]);
            $this->queue("c_wrecking", null, ["displaced" => $occupant]);
            $this->queue("moveStep", null, [
                "budget" => $this->getBudget() - $stepsUsed,
                "moved" => $this->getMoved() + $stepsUsed,
                "reason" => $this->getReason(),
            ]);
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

    /**
     * Wrecking Ball targets: occupied hexes the hero can reach and move into within $budget -
     * adjacent to the current hex, or to any hex reachable with budget-1 steps (the move-in
     * itself costs 1). Grimheim is excluded: it is not an "occupied area" (designer ruling),
     * and a hex inside it cannot serve as an approach either (entering ends the move).
     */
    private function getWreckingTargets(int $budget): array {
        $hero = $this->game->getHero($this->getOwner());
        $hexMap = $this->game->hexMap;
        $sources = [$hero->getHex() => 0];
        if ($budget > 1) {
            $sources += $hexMap->getReachableHexes($hero->getHex(), $budget - 1, $hero);
        }
        $targets = [];
        foreach ($sources as $from => $dist) {
            if ($dist > 0 && $hexMap->isInGrimheim($from)) {
                continue;
            }
            foreach ($hexMap->getAdjacentHexes($from) as $hex) {
                if (isset($targets[$hex])) {
                    continue;
                }
                if ($hexMap->isInGrimheim($hex) || $hexMap->isImpassable($hex, $hero)) {
                    continue;
                }
                if ($hexMap->getCharacterOnHex($hex) !== null) {
                    $targets[$hex] = true;
                }
            }
        }
        return array_keys($targets);
    }

    /** Closest reachable hex adjacent to the Wrecking Ball target, to walk to before moving in. */
    private function findWreckingApproach(string $target): string {
        $hero = $this->game->getHero($this->getOwner());
        $hexMap = $this->game->hexMap;
        $reach = $hexMap->getReachableHexes($hero->getHex(), $this->getBudget() - 1, $hero);
        $best = null;
        $bestDist = PHP_INT_MAX;
        foreach ($hexMap->getAdjacentHexes($target) as $hex) {
            $dist = $reach[$hex] ?? null;
            if ($dist === null || $hexMap->isInGrimheim($hex)) {
                continue;
            }
            if ($dist < $bestDist) {
                $best = $hex;
                $bestDist = $dist;
            }
        }
        $this->game->systemAssert("ERR:moveStep:noWreckingApproach:$target", $best !== null);
        return $best;
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
