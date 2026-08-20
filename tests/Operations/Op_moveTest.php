<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Op_move covers both interaction styles: one-click (path walked, move ends) and
 * step mode (the op re-queues itself per click until "End Move" / count exhaustion).
 * Step mode turns on via hasStepIncentive (pref 102, Wrecking Ball, TStep quest or
 * tableau card) and is off for filtered moves. Hero starts at hex_11_8 (open
 * plains): hex_12_8 is distance 1, hex_13_7 is distance 2.
 */
final class Op_moveTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        // Assign hero 1 (Bjorn) to PCOLOR
        $this->game->tokens->moveToken("card_hero_1_1", $this->getPlayersTableau());
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->hexMap->invalidateOccupancy();
    }

    private function getHeroHex(): string {
        return $this->game->tokens->getTokenLocation("hero_1");
    }

    /** Turn on the "Confirm end of movement" preference — the plain step incentive. */
    private function enableStepMode(): void {
        $this->game->userPreferences->_set((int) $this->game->getActivePlayerId(), Material::MA_PREF_CONFIRM_MOVE, 1);
    }

    private function pendingTypes(): array {
        return array_map(fn($row) => $row["type"] ?? "", $this->game->machine->getTopOperations($this->owner));
    }

    /** Every queued op type (not just the top-rank ones), so a trigger behind a step is visible. */
    private function allQueuedTypes(): array {
        return array_map(fn($row) => $row["type"] ?? "", $this->game->machine->getAllOperations($this->owner));
    }

    /** First queued op of $type anywhere in the queue, or null. */
    private function findAnyQueuedOp(string $type): ?Operation {
        foreach ($this->game->machine->getAllOperations($this->owner) as $row) {
            if (($row["type"] ?? "") === $type) {
                return $this->game->machine->instantiateOperationFromDbRow($row);
            }
        }
        return null;
    }

    public function testMoveHero1ReachesAdjacent(): void {
        $this->createOp("1move");
        // hex_12_8 is adjacent plains, should be reachable
        $this->assertValidTarget("hex_12_8");
    }

    public function testMoveHero1DoesNotReachDistance2(): void {
        $this->createOp("1move");
        // hex_13_7 is distance 2 from hex_11_8
        $this->assertNotValidTarget("hex_13_7");
    }

    public function testMoveHeroNStepsIsUpToN(): void {
        // "Nmove" is "up to N" per DESIGN.md #11 (designer-confirmed). Any distance 1..N
        // is a valid pick; the player may not skip the action entirely (mcount=N>0), but
        // can choose to stop short of N hexes.
        $this->createOp("2move");
        $this->assertValidTarget("hex_12_8", "distance 1 is offered under up-to-N semantics");
        $this->assertValidTarget("hex_13_7", "distance 2 is offered");
    }

    public function testMoveHeroOptionalShowsAllDistances(): void {
        // [0,2]move is optional: show all reachable hexes
        $this->createOp("[0,2]move");
        // Should include both distance 1 and distance 2
        $this->assertValidTarget("hex_12_8"); // distance 1
        $this->assertValidTarget("hex_13_7"); // distance 2
    }

    public function testResolveMovesHero(): void {
        $this->createOp("1move");
        $target = $this->op->getArgsTarget()[0] ?? null;
        $this->assertNotNull($target);
        $this->call_resolve($target);
        $this->dispatchAll();
        $this->assertEquals($target, $this->getHeroHex());
    }

    public function testLocationOnlyFiltersToNamedLocationHexes(): void {
        // Hero starts at hex_11_8 (plains, no loc). Grimheim hexes (10_8, 10_9, 9_9, etc.) are
        // within 2 steps. Non-location plains like 12_8, 11_7 are also reachable but must be excluded.
        $this->createOp("[0,2]move(locationOnly)");
        $targets = $this->op->getArgsTarget();

        $this->assertNotEmpty($targets, "Should offer at least some Grimheim hexes within 2 steps of hex_11_8");
        foreach ($targets as $hexId) {
            $loc = $this->game->hexMap->getHexNamedLocation($hexId);
            $this->assertNotEquals("", $loc, "Hex $hexId should belong to a named location but has none");
        }
        // Spot-check: a known Grimheim hex within reach is offered
        $this->assertValidTarget("hex_10_8");
        // Spot-check: a known non-location adjacent hex is NOT offered
        $this->assertNotValidTarget("hex_12_8");
    }

    public function testLocationOnlyOptionalAllowsSkip(): void {
        // [0,N]move is optional — the player must be able to decline to move even when
        // the locationOnly filter is in effect. Staying put is expressed via canSkip(), not
        // by including the current hex in getPossibleMoves().
        $this->game->tokens->moveToken("hero_1", "hex_10_9");
        $this->createOp("[0,2]move(locationOnly)");
        $this->assertTrue($this->op->canSkip(), "Optional move with locationOnly must be skippable");
    }

    public function testLocationOnlyEmptyWhenNoReachableLocation(): void {
        // hex_13_6 is a plains hex with no named location. Within 2 steps nothing else has a loc
        // either (surrounding hexes are plains/mountain without loc — see map_material.csv).
        $this->game->tokens->moveToken("hero_1", "hex_13_6");
        $this->createOp("[0,2]move(locationOnly)");

        // Recompute the raw reachable set and assert none of them have a loc — if this assertion
        // fails, the test fixture (hero start hex) needs to change, not the production code.
        $reachable = $this->game->hexMap->getReachableHexes("hex_13_6", 2, $this->game->getHero(PCOLOR));
        foreach (array_keys($reachable) as $hexId) {
            $this->assertEquals(
                "",
                $this->game->hexMap->getHexNamedLocation($hexId),
                "Test fixture assumption broken: hex $hexId has a named location"
            );
        }
        $this->assertNoValidTargets("No location hexes reachable → moves should be empty");
    }

    public function testMoveHeroWithoutParamReturnsAllReachable(): void {
        // Regression check: without locationOnly, 2move returns both location and non-location hexes.
        $this->createOp("[0,2]move");
        $this->assertValidTarget("hex_12_8", "Non-location hex should be offered without the param");
        $this->assertValidTarget("hex_10_8", "Grimheim hex should still be offered without the param");
    }

    public function testResolveToGrimheimUsesHomeHex(): void {
        // Move hero adjacent to Grimheim
        $this->game->tokens->moveToken("hero_1", "hex_8_8");
        $this->createOp("1move");
        $moves = $this->op->getArgsTarget();
        // Find a Grimheim hex in moves
        $grimheimHex = null;
        foreach ($moves as $hex) {
            if ($this->game->hexMap->isInGrimheim($hex)) {
                $grimheimHex = $hex;
                break;
            }
        }
        $this->assertNotNull($grimheimHex, "Should be able to reach Grimheim from hex_8_8");
        $this->call_resolve($grimheimHex);
        $this->dispatchAll();
        // Hero should be at their home hex in Grimheim, not the clicked hex
        $heroHex = $this->getHeroHex();
        $this->assertTrue($this->game->hexMap->isInGrimheim($heroHex));
    }

    // -------------------------------------------------------------------------
    // step mode selection (prompt tells the mode apart)
    // -------------------------------------------------------------------------

    private const STEP_PROMPT = "end the move";

    public function testOneClickPromptWithoutStepIncentive(): void {
        $op = $this->createOp("move", ["count" => 3]);
        $this->assertEquals("Choose where to move", $op->getPrompt(), "no per-step incentive -> one-click move");
    }

    public function testStepModeWhenActiveQuestListensOnStep(): void {
        // Raven's Claw (card_equip_3_22) has quest_on=TStep; put it on top of the equipment deck.
        $this->game->tokens->moveToken("card_equip_3_22", "deck_equip_" . PCOLOR, 9999);
        $op = $this->createOp("move", ["count" => 3]);
        $this->assertStringContainsString(self::STEP_PROMPT, $op->getPrompt());
    }

    public function testStepModeWhenTableauCardListensOnStep(): void {
        // Treetreader II (card_ability_2_6) has an onStep hook.
        $this->game->tokens->moveToken("card_ability_2_6", "tableau_" . PCOLOR);
        $op = $this->createOp("move", ["count" => 3]);
        $this->assertStringContainsString(self::STEP_PROMPT, $op->getPrompt());
    }

    public function testHardcodedCustomStepQuestEnablesStepMode(): void {
        // Shield (card_equip_4_16) is a quest_on=custom quest whose triggerQuest reacts to Step;
        // it's caught via the hardcoded allowlist (no declarative quest_on=TStep to read).
        $this->game->tokens->moveToken("card_equip_4_16", "deck_equip_" . PCOLOR, 9999);
        $op = $this->createOp("move", ["count" => 3]);
        $this->assertStringContainsString(self::STEP_PROMPT, $op->getPrompt());
    }

    public function testCustomCardDoesNotEnableStepMode(): void {
        // Bloodline Crystal (card_equip_2_25) has on=custom but no onStep hook: it must NOT be
        // mistaken for a per-step listener (regression for the canTriggerEffectOn false-positive).
        $this->game->tokens->moveToken("card_equip_2_25", "tableau_" . PCOLOR);
        $op = $this->createOp("move", ["count" => 3]);
        $this->assertEquals("Choose where to move", $op->getPrompt(), "an on=custom card is not a step incentive");
    }

    public function testConfirmMovePreferenceEnablesStepMode(): void {
        $this->enableStepMode();
        $op = $this->createOp("move", ["count" => 3]);
        $this->assertStringContainsString(self::STEP_PROMPT, $op->getPrompt(), "preference forces step mode without any card incentive");
    }

    public function testWreckingBallAloneEnablesStepMode(): void {
        // Moving into occupied hexes is offered per step, so the card itself is a step incentive.
        $this->game->tokens->moveToken("card_ability_4_8", "tableau_" . PCOLOR); // Wrecking Ball II
        $op = $this->createOp("move", ["count" => 3]);
        $this->assertStringContainsString(self::STEP_PROMPT, $op->getPrompt());
    }

    public function testStepModeStaysOnAfterTheIncentiveDisappears(): void {
        // A TStep quest completing mid-move leaves the deck top, so by the next prompt there is no
        // incentive left; without stickiness the player would lose "End Move" and be forced to
        // spend the rest of the count (the op is not skippable).
        $op = $this->createOp("move", ["count" => 2, "moved" => 1]);
        $this->assertStringContainsString(self::STEP_PROMPT, $op->getPrompt());
        $this->assertContains("endOfMove", $op->getArgsTarget());
    }

    public function testFilteredMoveStaysOneClick(): void {
        // Destination filters constrain where the move ENDS, which step mode cannot express
        // yet — filtered moves keep the one-click flow (see PLAN_WRECKING_MOVE.md).
        $this->enableStepMode();
        $op = $this->createOp("move", ["count" => 2, "params" => "locationOnly"]);
        $this->assertEquals("Choose where to move", $op->getPrompt());
    }

    // -------------------------------------------------------------------------
    // step loop (ported from the former Op_moveStep)
    // -------------------------------------------------------------------------

    public function testFirstPromptOffersReachableWithoutEndMove(): void {
        $this->enableStepMode();
        $op = $this->createOp("move", ["count" => 3]);
        $targets = $op->getArgsTarget();
        $this->assertContains("hex_12_8", $targets, "reachable adjacent hex is offered");
        $this->assertContains("hex_13_7", $targets, "a far hex is still offered (step + direct)");
        $this->assertNotContains("endOfMove", $targets, "no early stop before the first step");
    }

    public function testEndMoveOfferedAfterAtLeastOneStep(): void {
        $this->enableStepMode();
        $op = $this->createOp("move", ["count" => 2, "moved" => 1]);
        $targets = $op->getArgsTarget();
        $this->assertContains("endOfMove", $targets);
        $this->assertContains("hex_12_8", $targets);
    }

    public function testExhaustedCountOffersOnlyEndMove(): void {
        $this->enableStepMode();
        $op = $this->createOp("move", ["count" => 0, "moved" => 1]);
        $this->assertEquals(["endOfMove"], $op->getArgsTarget());
    }

    public function testAdjacentStepMovesHeroAndContinues(): void {
        $this->enableStepMode();
        $this->createOp("move", ["count" => 3, "reason" => "Op_actionMove"]);
        $this->call_resolve("hex_12_8");
        $this->dispatchAll();
        $this->assertEquals("hex_12_8", $this->getHeroHex(), "hero took the single step");
        $this->assertContains("move", $this->pendingTypes(), "loop continues with the remaining count");
    }

    public function testFarClickConsumesMultipleStepsThenContinues(): void {
        $this->enableStepMode();
        $this->createOp("move", ["count" => 3, "reason" => "Op_actionMove"]);
        $this->call_resolve("hex_13_7"); // distance 2
        $this->dispatchAll();
        $this->assertEquals("hex_13_7", $this->getHeroHex(), "far click walks the whole path");
        $this->assertContains("move", $this->pendingTypes(), "1 count remains, so the loop continues");
    }

    public function testCountExhaustionEndsWithoutReprompt(): void {
        $this->enableStepMode();
        $this->createOp("move", ["count" => 1, "reason" => "Op_actionMove"]);
        $this->call_resolve("hex_12_8");
        $types = $this->allQueuedTypes();
        $this->assertNotContains("move", $types, "no re-prompt once the count is spent");
        $this->assertContains("trigger(TActionMove)", $types, "the closing ActionMove trigger fires");
    }

    public function testEndMoveFiresActionMoveTrigger(): void {
        $this->enableStepMode();
        $this->createOp("move", ["count" => 2, "moved" => 1, "reason" => "Op_actionMove"]);
        $this->call_resolve("endOfMove");
        $types = $this->pendingTypes();
        $this->assertContains("trigger(TActionMove)", $types, "ending the move emits ActionMove");
        $this->assertNotContains("move", $types, "the loop stops on End Move");
    }

    public function testBackAndForthReEntryIsAllowed(): void {
        // Step to hex_12_8, then the start hex must be offered again so the player can step back
        // (re-entering areas is the whole point of step mode).
        $this->enableStepMode();
        $this->createOp("move", ["count" => 3, "reason" => "Op_actionMove"]);
        $this->call_resolve("hex_12_8");
        $this->dispatchAll();

        $next = $this->findAnyQueuedOp("move");
        $this->assertNotNull($next, "a follow-up move should be queued");
        $this->assertContains("hex_11_8", $next->getArgsTarget(), "the hex just left is offered again (back-and-forth)");
    }

    // -------------------------------------------------------------------------
    // Wrecking Ball targets (step mode is implied by the card itself)
    // -------------------------------------------------------------------------

    /** Puts Wrecking Ball II on the tableau and a monster on an adjacent hex so a Wrecking Ball target exists. */
    private function setUpWreckingBall(): void {
        $this->game->tokens->moveToken("card_ability_4_8", $this->getPlayersTableau());
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();
    }

    public function testWreckingTargetOfferedAtEveryPrompt(): void {
        $this->setUpWreckingBall();
        $first = $this->createOp("move", ["count" => 3]);
        $this->assertContains("hex_12_8", $first->getArgsTarget(), "occupied adjacent hex is a Wrecking Ball move target");

        $later = $this->createOp("move", ["count" => 1, "moved" => 2]);
        $this->assertContains("hex_12_8", $later->getArgsTarget(), "still offered mid-loop");
    }

    public function testOccupiedHexNotOfferedWithoutWreckingCard(): void {
        $this->enableStepMode();
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();
        $op = $this->createOp("move", ["count" => 3]);
        $this->assertNotContains("hex_12_8", $op->getArgsTarget(), "no moving into occupied hexes without the card");
    }

    public function testDistantWreckingTargetOfferedWithinCount(): void {
        $this->game->tokens->moveToken("card_ability_4_8", $this->getPlayersTableau());
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7"); // distance 2: walk 1 + move-in 1
        $this->game->hexMap->invalidateOccupancy();
        $op = $this->createOp("move", ["count" => 3]);
        $this->assertContains("hex_13_7", $op->getArgsTarget(), "occupied hex within the count is a Wrecking Ball move target");
    }

    public function testDistantWreckingTargetNotOfferedWhenCountTooSmall(): void {
        $this->game->tokens->moveToken("card_ability_4_8", $this->getPlayersTableau());
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7"); // needs 2 count
        $this->game->hexMap->invalidateOccupancy();
        $op = $this->createOp("move", ["count" => 1, "moved" => 2]);
        $this->assertNotContains("hex_13_7", $op->getArgsTarget(), "walk+move-in does not fit in 1 count");
    }

    public function testDistantWreckingMoveAutoRoutesAndSpendsWalkSteps(): void {
        $this->game->tokens->moveToken("card_ability_4_8", $this->getPlayersTableau());
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $this->game->hexMap->invalidateOccupancy();
        $this->createOp("move", ["count" => 3, "reason" => "Op_actionMove"]);
        $this->call_resolve("hex_13_7");
        $this->dispatchAll(); // walk step(s) resolve; push phase becomes current

        $this->assertEquals("hex_13_7", $this->getHeroHex(), "Boldur walked adjacent and moved in");

        $wrecking = $this->findAnyQueuedOp("c_wrecking");
        $this->assertNotNull($wrecking, "push phase is queued");
        $this->assertEquals("monster_goblin_1", $wrecking->getDataField("displaced"));

        $follow = $this->findAnyQueuedOp("move");
        $this->assertNotNull($follow, "the move loop continues after the wrecking move");
        $this->assertEquals(1, $follow->getDataField("count"), "walk (1) + move-in (1) spent from count 3");
        $this->assertEquals(2, $follow->getDataField("moved"));
    }

    public function testWreckingTargetNotOfferedOnceCountIsSpent(): void {
        $this->setUpWreckingBall();
        $op = $this->createOp("move", ["count" => 0, "moved" => 3]);
        $this->assertEquals(["endOfMove"], $op->getArgsTarget());
    }

    public function testHeroOccupiedHexIsWreckingTarget(): void {
        // "Character" includes heroes (designer ruling) - a hero on an adjacent hex is a valid target.
        $this->game->tokens->moveToken("card_ability_4_8", $this->getPlayersTableau());
        $this->game->tokens->moveToken("hero_2", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();
        $op = $this->createOp("move", ["count" => 3]);
        $this->assertContains("hex_12_8", $op->getArgsTarget(), "hero-occupied adjacent hex is a Wrecking Ball move target");
    }

    public function testWreckingMoveStepsInQueuesPushAndContinuesLoop(): void {
        $this->setUpWreckingBall();
        $this->createOp("move", ["count" => 2, "moved" => 1, "reason" => "Op_actionMove"]);
        $this->call_resolve("hex_12_8");
        $this->dispatchAll(); // run the step; the push phase becomes the current prompt

        $this->assertEquals("hex_12_8", $this->getHeroHex(), "Boldur stepped into the occupied hex");

        $wrecking = $this->findAnyQueuedOp("c_wrecking");
        $this->assertNotNull($wrecking, "push phase is queued");
        $this->assertEquals("monster_goblin_1", $wrecking->getDataField("displaced"), "the occupant is displaced");

        $follow = $this->findAnyQueuedOp("move");
        $this->assertNotNull($follow, "the move loop continues after the wrecking move");
        $this->assertEquals(1, $follow->getDataField("count"), "the move-in costs 1 count");
        $this->assertEquals(2, $follow->getDataField("moved"), "the move-in counts as a step taken");
        $this->assertEquals("Op_actionMove", $follow->getReason(), "reason propagates so the closing trigger is ActionMove");
    }

    // -------------------------------------------------------------------------
    // BGA #234817 regression guards (ported from the former Op_moveStep tests)
    // -------------------------------------------------------------------------

    /**
     * BGA #234817 "Move next to enemy costs all movement points" - reporter clarified: in
     * step-by-step move mode the move "automatically ENDS" with count remaining as soon as
     * (1) the hero steps next to a monster, or (2) a step completes a quest. The loop can only
     * end early if the follow-up move collapses to a single choice ("End Move"), which happens
     * only when getPossibleMoves offers no reachable hex.
     *
     * RESULT: neither sub-claim reproduces server-side. The two tests below are GREEN and assert
     * the CORRECT (non-buggy) behavior - the loop keeps offering destinations. They are regression
     * guards, not a captured bug.
     */
    private function initAlvaOnEmptyMap(): void {
        $this->init(2); // Alva (hero_2) - owns the Belt of Youth forest quest
        foreach (["hero_1", "hero_2", "hero_3", "hero_4"] as $token) {
            $this->game->tokens->moveToken($token, "limbo");
        }
        foreach ($this->game->tokens->getTokensOfTypeInLocation(null, "hex%") as $token) {
            if (str_starts_with($token["key"], "monster")) {
                $this->game->tokens->moveToken($token["key"], "limbo");
            }
        }
        $this->game->hexMap->invalidateOccupancy();
    }

    private function heroId(): string {
        return $this->game->getHeroTokenId($this->owner);
    }

    /**
     * BGA #234817 sub-claim 1: stepping next to a monster with free hexes still in count. Open
     * plains x=11..15,y=7..9; hero starts hex_12_8, monster parked at hex_14_8, so stepping east to
     * hex_13_8 lands the hero directly adjacent while count (2) and free hexes remain all around.
     */
    public function testStepNextToMonsterDoesNotEndMovePrematurely(): void {
        $this->initAlvaOnEmptyMap();
        $this->enableStepMode();
        $this->game->tokens->moveToken($this->heroId(), "hex_12_8");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_14_8");
        $this->game->hexMap->invalidateOccupancy();

        $this->createOp("move", ["count" => 3, "reason" => "Op_actionMove"]);
        $this->call_resolve("hex_13_8"); // step adjacent to the monster
        $this->dispatchAll();

        $this->assertEquals("hex_13_8", $this->game->tokens->getTokenLocation($this->heroId()), "hero took the single step next to the monster");

        $hero = $this->game->getHero($this->owner);
        $reachable = $this->game->hexMap->getReachableHexes("hex_13_8", 2, $hero);
        $this->assertNotEmpty($reachable, "reachable hexes remain around the adjacent monster");
        $this->assertArrayNotHasKey("hex_14_8", $reachable, "cannot stop on the monster's hex");

        $follow = $this->findAnyQueuedOp("move");
        $this->assertNotNull($follow, "the loop re-queues a follow-up move (move did NOT end)");
        $targets = $follow->getArgsTarget();
        $this->assertGreaterThan(1, count($targets), "more than just End Move is offered -> no premature auto-end");
        $this->assertContains("endOfMove", $targets, "End Move is offered");
        $freeTargets = array_values(array_filter($targets, fn($t) => $t !== "endOfMove"));
        $this->assertNotEmpty($freeTargets, "at least one free destination hex is still offered next to the monster");
    }

    /**
     * BGA #234817 sub-claim 2: a step completes a quest (Belt of Youth: enter 8 forest areas).
     * Deck-top = card_equip_2_22 seeded with 7 trackers; hero on forest hex_8_3, so stepping into
     * the adjacent forest hex_9_3 is the 8th forest and fires gainEquip. Belt of Youth itself is
     * quest_on=TStep, so it doubles as the step incentive.
     */
    public function testStepCompletingQuestDoesNotEndMovePrematurely(): void {
        $this->initAlvaOnEmptyMap();
        $belt = "card_equip_2_22"; // Belt of Youth - in(forest):gainTracker,check('countTracker>=8'):gainEquip
        $this->game->tokens->moveToken($belt, "deck_equip_" . $this->owner, 9999); // force to deck top
        // Seed 7 trackers so the next forest entry (the 8th) completes the quest.
        $this->game->effect_moveCrystals($this->heroId(), "red", 7, $belt);
        $this->assertEquals(7, $this->game->evaluateExpression("countTracker", $this->owner), "quest primed one step short");

        $this->game->tokens->moveToken($this->heroId(), "hex_8_3"); // forest
        $this->game->hexMap->invalidateOccupancy();

        $this->createOp("move", ["count" => 3, "reason" => "Op_actionMove"]);
        $this->call_resolve("hex_9_3"); // adjacent forest -> 8th forest -> quest completes
        $this->dispatchAll();

        $this->assertEquals("hex_9_3", $this->game->tokens->getTokenLocation($this->heroId()), "hero took the completing step");
        $this->assertEquals("tableau_" . $this->owner, $this->game->tokens->getTokenLocation($belt), "quest completed mid-step (Belt gained)");

        $follow = $this->findAnyQueuedOp("move");
        $this->assertNotNull($follow, "the loop continues after quest completion: " . implode(",", $this->allQueuedTypes()));
        $targets = $follow->getArgsTarget();
        $this->assertGreaterThan(1, count($targets), "destinations remain after quest completion -> no premature auto-end");
        $this->assertContains("endOfMove", $targets, "End Move is offered");
    }
}
