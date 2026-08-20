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
use Bga\Games\Fate\Model\Trigger;
use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 * Move hero up to X areas (the count). One operation, two interaction styles:
 *
 * - One-click: pick a destination, the shortest path is walked and the move ends.
 *   The last step is final (logs the arrival and fires the closing trigger).
 * - Step mode: the op re-queues itself after each click with the reduced count
 *   (data "moved" counts steps taken), offering every hex reachable within the
 *   remaining count, Wrecking Ball targets, and an "End Move" sentinel once at
 *   least one area was moved. All hops are non-final Op_step (TStep + encounter);
 *   the closing ActionMove/Move trigger fires once, from this op, on "End Move",
 *   count exhaustion, or entering Grimheim. See DESIGN.md "Step-by-step Move".
 *
 * Step mode is on when routing matters (hasStepIncentive: MA_PREF_CONFIRM_MOVE
 * preference, Wrecking Ball on the tableau, a quest_on=TStep quest, or a tableau
 * card reacting per step) and there is no destination filter - filtered moves
 * stay one-click for now (see PLAN_WRECKING_MOVE.md open question).
 *
 * "Move X" is always "up to X" per DESIGN.md #11 (designer-confirmed). All
 * reachable distances 1..count are offered; the player picks how far to go.
 * mcount governs skippability only: mcount=0 (e.g. "[0,2]move") means the whole
 * move can be declined before the first step; mcount>0 means at least one area
 * must be moved (in step mode "End Move" is gated on moved >= 1 for the same
 * reason).
 *
 * Params:
 * - param(0):
 *   - "locationOnly" — destinations restricted to hexes belonging to any named
 *     location (DarkForest, Grimheim, TempleRuins, …).
 *   - "<name>" — destinations restricted to hexes whose terrain or named
 *     location equals <name>, e.g. "forest" for Treetreader's move(forest).
 *
 * Data:
 * - moved: steps taken so far in the step loop.
 * - target: preset destination (Op_actionMove pass-through or scripted move).
 * - reason: propagated so the closing trigger is ActionMove (vs Move).
 *
 * Used by: actionMove ([1,N]move), Agility (2move), Maneuver (1move),
 * Fleetfoot (1move), Quick Reflexes (1move), Seek Shelter ([0,2]move(locationOnly)),
 * Treetreader (move(forest)).
 */
class Op_move extends CountableOperation {
    // Custom quests whose triggerQuest reacts to TStep but have no declarative quest_on=TStep
    // to read (their step logic lives in a bespoke override). Add new ones here.
    private const STEP_QUEST_CARDS = ["card_equip_4_16"]; // Shield - "enter Ogre Valley" branch

    private function getMoved(): int {
        return (int) $this->getDataField("moved", 0);
    }

    function getPrompt() {
        if ($this->isStepMode()) {
            return clienttranslate('Choose next step or end the move (${count} left)');
        }
        return clienttranslate("Choose where to move");
    }

    function getPossibleMoves(): array {
        $target = $this->getDataField("target", "");
        if ($target) {
            return [$target];
        }
        $hero = $this->game->getHero($this->getOwner());
        $remaining = (int) $this->getCount();
        $stepMode = $this->isStepMode();

        $targets = [];
        if ($remaining > 0) {
            $reachable = $this->game->hexMap->getReachableHexes($hero->getHex(), $remaining, $hero);

            $filter = $this->getParam(0, "");
            if ($filter === "locationOnly") {
                $reachable = array_filter(
                    $reachable,
                    fn($_dist, $hexId) => $this->game->hexMap->getHexNamedLocation($hexId) !== "",
                    ARRAY_FILTER_USE_BOTH
                );
            } elseif ($filter !== "") {
                $reachable = array_filter(
                    $reachable,
                    fn($_dist, $hexId) => $this->game->hexMap->getHexTerrain($hexId) === $filter ||
                        $this->game->hexMap->getHexNamedLocation($hexId) === $filter,
                    ARRAY_FILTER_USE_BOTH
                );
            }
            $targets = array_keys($reachable);

            if ($stepMode && $hero->getWreckingCard() !== null) {
                $targets = array_merge($targets, $this->getWreckingTargets($remaining));
            }
        }
        // Offer the early-stop only after at least one step (keeps the "move at least 1 area" minimum).
        if ($stepMode && $this->getMoved() >= 1) {
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

        if (!$this->isStepMode()) {
            // One-click: walk the whole path now; the final step logs the arrival
            // and fires the closing trigger (Op_step reads the reason).
            if ($this->game->hexMap->isInGrimheim($arg)) {
                $arg = $hero->getRulesFor("location", $arg);
            }
            $from = $hero->getHex();
            $path = $this->game->hexMap->getPath($from, $arg, $hero);
            // A move that queues no steps would spend the action for nothing (BGA #235653).
            $this->game->systemAssert("ERR:move:emptyPath:$from:$arg", count($path) > 0);
            foreach ($path as $hex) {
                $this->queue("step", null, [
                    "hex" => $hex,
                    "final" => $hex === $arg,
                    "reason" => $this->getReason(),
                ]);
            }
            return;
        }

        // Wrecking Ball move into an occupied hex: walk adjacent if needed (auto-route),
        // step in (transient multi-occupancy), then c_wrecking pushes the occupant and
        // deals the damage, and the loop continues with the remaining count.
        $occupant = $hero->getWreckingCard() !== null ? $this->game->hexMap->getCharacterOnHex($arg) : null;
        if ($occupant !== null) {
            $stepsUsed = 1;
            if ($this->game->hexMap->getHexDistance($hero->getHex(), $arg) > 1) {
                $approach = $this->findWreckingApproach($arg);
                $path = $this->game->hexMap->getPath($hero->getHex(), $approach, $hero);
                foreach ($path as $hex) {
                    $this->queueStep($hex);
                }
                $stepsUsed += count($path);
            }
            $this->queueStep($arg);
            $this->queue("c_wrecking", null, ["displaced" => $occupant]);
            $this->queueRemainingMove($stepsUsed);
            return;
        }

        // Entering Grimheim sends the hero home and ends the move.
        $enteringGrimheim = $this->game->hexMap->isInGrimheim($arg);
        $target = $enteringGrimheim ? $hero->getRulesFor("location", $arg) : $arg;

        $path = $this->game->hexMap->getPath($hero->getHex(), $target, $hero);
        foreach ($path as $hex) {
            $this->queueStep($hex);
        }

        $remaining = (int) $this->getCount() - count($path);
        if ($enteringGrimheim || $remaining <= 0) {
            $this->queueFinalTrigger();
            return;
        }
        $this->queueRemainingMove(count($path));
    }

    /** Intermediate hop; the closing trigger fires once, from this op. */
    private function queueStep(string $hex): void {
        $this->queue("step", null, [
            "hex" => $hex,
            "final" => false,
            "reason" => $this->getReason(),
        ]);
    }

    private function queueRemainingMove(int $stepsUsed): void {
        $this->queue("move", null, [
            "count" => (int) $this->getCount() - $stepsUsed,
            "moved" => $this->getMoved() + $stepsUsed,
            "reason" => $this->getReason(),
        ]);
    }

    /**
     * Step mode: routing matters and there is no destination filter (filtered moves stay one-click).
     * Sticky once the loop started: an incentive can disappear mid-move (a TStep quest completes and
     * leaves the deck top), and dropping to one-click there would strand the player with no "End Move".
     */
    protected function isStepMode(): bool {
        return $this->getParam(0, "") === "" && ($this->getMoved() >= 1 || $this->hasStepIncentive());
    }

    /**
     * True when a deliberate route matters this move: the player asked for step mode via the
     * MA_PREF_CONFIRM_MOVE preference, Wrecking Ball is on the tableau (moving into occupied
     * hexes is offered per step), the active quest fires per step, or a tableau card reacts
     * on each step. See DESIGN.md "Step-by-step Move".
     */
    private function hasStepIncentive(): bool {
        $owner = $this->getOwner();
        $hero = $this->game->getHero($owner);
        if ($this->game->getUserPreference($this->getPlayerId(), Material::MA_PREF_CONFIRM_MOVE) == 1) {
            return true;
        }
        if ($hero->getWreckingCard() !== null) {
            return true;
        }
        // Active quest (top of the equipment deck) that advances per step: declarative
        // quest_on=TStep, or a hardcoded custom quest whose step logic is in a bespoke override.
        $top = $this->game->tokens->getTokenOnTop("deck_equip_$owner");
        if ($top !== null) {
            $questOn = (string) $this->game->material->getRulesFor($top["key"], "quest_on", "");
            if ($this->hasStepListeners($questOn) || in_array($top["key"], self::STEP_QUEST_CARDS, true)) {
                return true;
            }
        }
        // Tableau card that reacts to each step: a bespoke onStep hook (Treetreader II) or a
        // declarative on=TStep. NOT canTriggerEffectOn(Step) -- that is lenient for on=custom
        // cards (Wrecking Ball, Bloodline Crystal) and would false-positive on them.
        foreach ($hero->getTableauCards() as $card) {
            if (method_exists($this->game->instantiateCard($card, $this), "onStep")) {
                return true;
            }
            if ($this->hasStepListeners((string) $this->game->material->getRulesFor($card["key"], "on", ""))) {
                return true;
            }
        }
        return false;
    }

    private function hasStepListeners(string $on): bool {
        if ($on === "") {
            return false;
        }
        foreach (Trigger::Step->chain() as $t) {
            if ($on === $t->value) {
                return true;
            }
        }
        return false;
    }

    /**
     * Wrecking Ball targets: occupied hexes the hero can reach and move into within $remaining -
     * adjacent to the current hex, or to any hex reachable with remaining-1 steps (the move-in
     * itself costs 1). Grimheim is excluded: it is not an "occupied area" (designer ruling),
     * and a hex inside it cannot serve as an approach either (entering ends the move).
     */
    private function getWreckingTargets(int $remaining): array {
        $hero = $this->game->getHero($this->getOwner());
        $hexMap = $this->game->hexMap;
        $sources = [$hero->getHex() => 0];
        if ($remaining > 1) {
            $sources += $hexMap->getReachableHexes($hero->getHex(), $remaining - 1, $hero);
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
        $reach = $hexMap->getReachableHexes($hero->getHex(), (int) $this->getCount() - 1, $hero);
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
        $this->game->systemAssert("ERR:move:noWreckingApproach:$target", $best !== null);
        return $best;
    }

    private function queueFinalTrigger(): void {
        $trigger = $this->getReason() === "Op_actionMove" ? Trigger::ActionMove : Trigger::Move;
        $this->queueTrigger($trigger);
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
