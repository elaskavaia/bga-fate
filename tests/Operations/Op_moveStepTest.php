<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;

/**
 * Tests for Op_moveStep - the budgeted, step-by-step move loop used when the hero has an
 * active per-step incentive. Hero starts at hex_11_8 (open plains): hex_12_8 is distance 1,
 * hex_13_7 is distance 2.
 */
final class Op_moveStepTest extends AbstractOpTestCase {
    private string $heroHex = "hex_11_8";

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", $this->heroHex);
        $this->game->hexMap->invalidateOccupancy();
    }

    private function heroHex(): string {
        return $this->game->tokens->getTokenLocation("hero_1");
    }

    private function pendingTypes(): array {
        return array_map(fn($row) => $row["type"] ?? "", $this->game->machine->getTopOperations($this->owner));
    }

    /** The top-rank queued op of $type, or null if the loop ended without re-queuing one. */
    private function findQueuedOp(string $type): ?Operation {
        foreach ($this->game->machine->getTopOperations($this->owner) as $row) {
            if (($row["type"] ?? "") === $type) {
                return $this->game->machine->instantiateOperationFromDbRow($row);
            }
        }
        return null;
    }

    /** Every queued op type (not just the top-rank ones), so a trigger behind a step is visible. */
    private function allQueuedTypes(): array {
        return array_map(fn($row) => $row["type"] ?? "", $this->game->machine->getAllOperations($this->owner));
    }

    public function testFirstPromptOffersReachableWithoutEndMove(): void {
        $op = $this->createOp("moveStep", ["budget" => 3, "moved" => 0]);
        $targets = $op->getArgsTarget();
        $this->assertContains("hex_12_8", $targets, "reachable adjacent hex is offered");
        $this->assertContains("hex_13_7", $targets, "a far hex is still offered (step + direct)");
        $this->assertNotContains("endOfMove", $targets, "no early stop before the first step");
    }

    public function testEndMoveOfferedAfterAtLeastOneStep(): void {
        $op = $this->createOp("moveStep", ["budget" => 2, "moved" => 1]);
        $targets = $op->getArgsTarget();
        $this->assertContains("endOfMove", $targets);
        $this->assertContains("hex_12_8", $targets);
    }

    public function testExhaustedBudgetOffersOnlyEndMove(): void {
        $op = $this->createOp("moveStep", ["budget" => 0, "moved" => 1]);
        $this->assertEquals(["endOfMove"], $op->getArgsTarget());
    }

    public function testAdjacentStepMovesHeroAndContinues(): void {
        $this->createOp("moveStep", ["budget" => 3, "moved" => 0, "reason" => "Op_actionMove"]);
        $this->call_resolve("hex_12_8");
        $this->dispatchAll();
        $this->assertEquals("hex_12_8", $this->heroHex(), "hero took the single step");
        $this->assertContains("moveStep", $this->pendingTypes(), "loop continues with the remaining budget");
    }

    public function testFarClickConsumesMultipleStepsThenContinues(): void {
        $this->createOp("moveStep", ["budget" => 3, "moved" => 0, "reason" => "Op_actionMove"]);
        $this->call_resolve("hex_13_7"); // distance 2
        $this->dispatchAll();
        $this->assertEquals("hex_13_7", $this->heroHex(), "far click walks the whole path");
        $this->assertContains("moveStep", $this->pendingTypes(), "1 budget remains, so the loop continues");
    }

    public function testBudgetExhaustionEndsWithoutReprompt(): void {
        $this->createOp("moveStep", ["budget" => 1, "moved" => 0, "reason" => "Op_actionMove"]);
        $this->call_resolve("hex_12_8");
        $types = $this->allQueuedTypes();
        $this->assertNotContains("moveStep", $types, "no re-prompt once the budget is spent");
        $this->assertContains("trigger(TActionMove)", $types, "the closing ActionMove trigger fires");
    }

    public function testEndMoveFiresActionMoveTrigger(): void {
        $this->createOp("moveStep", ["budget" => 2, "moved" => 1, "reason" => "Op_actionMove"]);
        $this->call_resolve("endOfMove");
        $types = $this->pendingTypes();
        $this->assertContains("trigger(TActionMove)", $types, "ending the move emits ActionMove");
        $this->assertNotContains("moveStep", $types, "the loop stops on End Move");
    }

    /** Puts Wrecking Ball II on the tableau and a monster on an adjacent hex so a Wrecking Ball target exists. */
    private function setUpWreckingBall(): void {
        $this->game->tokens->moveToken("card_ability_4_8", $this->getPlayersTableau());
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();
    }

    public function testWreckingTargetOfferedAtEveryPrompt(): void {
        $this->setUpWreckingBall();
        $first = $this->createOp("moveStep", ["budget" => 3, "moved" => 0]);
        $this->assertContains("hex_12_8", $first->getArgsTarget(), "occupied adjacent hex is a Wrecking Ball move target");

        $later = $this->createOp("moveStep", ["budget" => 1, "moved" => 2]);
        $this->assertContains("hex_12_8", $later->getArgsTarget(), "still offered mid-loop");
    }

    public function testOccupiedHexNotOfferedWithoutWreckingCard(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();
        $op = $this->createOp("moveStep", ["budget" => 3, "moved" => 0]);
        $this->assertNotContains("hex_12_8", $op->getArgsTarget(), "no moving into occupied hexes without the card");
    }

    public function testDistantWreckingTargetOfferedWithinBudget(): void {
        $this->game->tokens->moveToken("card_ability_4_8", $this->getPlayersTableau());
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7"); // distance 2: walk 1 + move-in 1
        $this->game->hexMap->invalidateOccupancy();
        $op = $this->createOp("moveStep", ["budget" => 3, "moved" => 0]);
        $this->assertContains("hex_13_7", $op->getArgsTarget(), "occupied hex within budget is a Wrecking Ball move target");
    }

    public function testDistantWreckingTargetNotOfferedWhenBudgetTooSmall(): void {
        $this->game->tokens->moveToken("card_ability_4_8", $this->getPlayersTableau());
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7"); // needs 2 budget
        $this->game->hexMap->invalidateOccupancy();
        $op = $this->createOp("moveStep", ["budget" => 1, "moved" => 2]);
        $this->assertNotContains("hex_13_7", $op->getArgsTarget(), "walk+move-in does not fit in 1 budget");
    }

    public function testDistantWreckingMoveAutoRoutesAndSpendsWalkSteps(): void {
        $this->game->tokens->moveToken("card_ability_4_8", $this->getPlayersTableau());
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $this->game->hexMap->invalidateOccupancy();
        $this->createOp("moveStep", ["budget" => 3, "moved" => 0, "reason" => "Op_actionMove"]);
        $this->call_resolve("hex_13_7");
        $this->dispatchAll(); // walk step(s) resolve; push phase becomes current

        $this->assertEquals("hex_13_7", $this->heroHex(), "Boldur walked adjacent and moved in");

        $wrecking = $this->findAnyQueuedOp("c_wrecking");
        $this->assertNotNull($wrecking, "push phase is queued");
        $this->assertEquals("monster_goblin_1", $wrecking->getDataField("displaced"));

        $follow = $this->findAnyQueuedOp("moveStep");
        $this->assertNotNull($follow, "the move loop continues after the wrecking move");
        $this->assertEquals(1, $follow->getDataField("budget"), "walk (1) + move-in (1) spent from budget 3");
        $this->assertEquals(2, $follow->getDataField("moved"));
    }

    public function testWreckingTargetNotOfferedOnceBudgetIsSpent(): void {
        $this->setUpWreckingBall();
        $op = $this->createOp("moveStep", ["budget" => 0, "moved" => 3]);
        $this->assertEquals(["endOfMove"], $op->getArgsTarget());
    }

    public function testHeroOccupiedHexIsWreckingTarget(): void {
        // "Character" includes heroes (designer ruling) - a hero on an adjacent hex is a valid Wrecking Ball target.
        $this->game->tokens->moveToken("card_ability_4_8", $this->getPlayersTableau());
        $this->game->tokens->moveToken("hero_2", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();
        $op = $this->createOp("moveStep", ["budget" => 3, "moved" => 0]);
        $this->assertContains("hex_12_8", $op->getArgsTarget(), "hero-occupied adjacent hex is a Wrecking Ball move target");
    }

    /** First queued op of $type anywhere in the queue (not just the top rank). */
    private function findAnyQueuedOp(string $type): ?Operation {
        foreach ($this->game->machine->getAllOperations($this->owner) as $row) {
            if (($row["type"] ?? "") === $type) {
                return $this->game->machine->instantiateOperationFromDbRow($row);
            }
        }
        return null;
    }

    public function testWreckingMoveStepsInQueuesPushAndContinuesLoop(): void {
        $this->setUpWreckingBall();
        $this->createOp("moveStep", ["budget" => 2, "moved" => 1, "reason" => "Op_actionMove"]);
        $this->call_resolve("hex_12_8");
        $this->dispatchAll(); // run the step; the push phase becomes the current prompt

        $this->assertEquals("hex_12_8", $this->heroHex(), "Boldur stepped into the occupied hex");

        $wrecking = $this->findAnyQueuedOp("c_wrecking");
        $this->assertNotNull($wrecking, "push phase is queued");
        $this->assertEquals("monster_goblin_1", $wrecking->getDataField("displaced"), "the occupant is displaced");

        $follow = $this->findAnyQueuedOp("moveStep");
        $this->assertNotNull($follow, "the move loop continues after the wrecking move");
        $this->assertEquals(1, $follow->getDataField("budget"), "the move-in costs 1 budget");
        $this->assertEquals(2, $follow->getDataField("moved"), "the move-in counts as a step taken");
        $this->assertEquals("Op_actionMove", $follow->getReason(), "reason propagates so the closing trigger is ActionMove");
    }

    public function testBackAndForthReEntryIsAllowed(): void {
        // Step to hex_12_8, then the start hex must be offered again so the player can step back
        // (re-entering areas is the whole point of step mode).
        $this->createOp("moveStep", ["budget" => 3, "moved" => 0, "reason" => "Op_actionMove"]);
        $this->call_resolve("hex_12_8");
        $this->dispatchAll();

        $next = $this->findQueuedOp("moveStep");
        $this->assertNotNull($next, "a follow-up moveStep should be queued");
        $this->assertContains($this->heroHex, $next->getArgsTarget(), "the hex just left is offered again (back-and-forth)");
    }
    /**
     * BGA #234817 "Move next to enemy costs all movement points" - reporter clarified: in
     * step-by-step move mode (driven by a TStep quest / onStep card) the move "automatically ENDS"
     * with budget remaining as soon as (1) the hero steps next to a monster, or (2) a step
     * completes a quest. The loop can only end early if the follow-up moveStep collapses to a
     * single choice ("End Move"), which happens only when getPossibleMoves offers no reachable hex.
     *
     * RESULT: neither sub-claim reproduces server-side. The two tests below are GREEN and assert
     * the CORRECT (non-buggy) behavior - the loop keeps offering destinations. They are regression
     * guards, not a captured bug. If the reported symptom is real it lives in the TypeScript client
     * (the server never auto-ends here while free hexes remain). Should a future change make the
     * server end early with budget + free hexes left, these assertions flip red.
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
     * BGA #234817 sub-claim 1: stepping next to a monster with free hexes still in budget. Open
     * plains x=11..15,y=7..9; hero starts hex_12_8, monster parked at hex_14_8, so stepping east to
     * hex_13_8 lands the hero directly adjacent while budget (2) and free hexes remain all around.
     */
    public function testStepNextToMonsterDoesNotEndMovePrematurely(): void {
        $this->initAlvaOnEmptyMap();
        $this->game->tokens->moveToken($this->heroId(), "hex_12_8");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_14_8");
        $this->game->hexMap->invalidateOccupancy();

        $this->createOp("moveStep", ["budget" => 3, "moved" => 0, "reason" => "Op_actionMove"]);
        $this->call_resolve("hex_13_8"); // step adjacent to the monster
        $this->dispatchAll();

        $this->assertEquals("hex_13_8", $this->game->tokens->getTokenLocation($this->heroId()), "hero took the single step next to the monster");

        $hero = $this->game->getHero($this->owner);
        $reachable = $this->game->hexMap->getReachableHexes("hex_13_8", 2, $hero);
        $this->assertNotEmpty($reachable, "reachable hexes remain around the adjacent monster");
        $this->assertArrayNotHasKey("hex_14_8", $reachable, "cannot stop on the monster's hex");

        $follow = $this->findQueuedOp("moveStep");
        $this->assertNotNull($follow, "the loop re-queues a follow-up moveStep (move did NOT end)");
        $targets = $follow->getArgsTarget();
        $this->assertGreaterThan(1, count($targets), "more than just End Move is offered -> no premature auto-end");
        $this->assertContains("endOfMove", $targets, "End Move is offered");
        $freeTargets = array_values(array_filter($targets, fn($t) => $t !== "endOfMove"));
        $this->assertNotEmpty($freeTargets, "at least one free destination hex is still offered next to the monster");
    }

    /**
     * BGA #234817 sub-claim 2: a step completes a quest (Belt of Youth: enter 8 forest areas).
     * Deck-top = card_equip_2_22 seeded with 7 trackers; hero on forest hex_8_3, so stepping into
     * the adjacent forest hex_9_3 is the 8th forest and fires gainEquip.
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

        $this->createOp("moveStep", ["budget" => 3, "moved" => 0, "reason" => "Op_actionMove"]);
        $this->call_resolve("hex_9_3"); // adjacent forest -> 8th forest -> quest completes
        $this->dispatchAll();

        $this->assertEquals("hex_9_3", $this->game->tokens->getTokenLocation($this->heroId()), "hero took the completing step");
        $this->assertEquals("tableau_" . $this->owner, $this->game->tokens->getTokenLocation($belt), "quest completed mid-step (Belt gained)");

        $follow = $this->findQueuedOp("moveStep");
        $this->assertNotNull($follow, "the loop continues after quest completion: " . implode(",", $this->allQueuedTypes()));
        $targets = $follow->getArgsTarget();
        $this->assertGreaterThan(1, count($targets), "destinations remain after quest completion -> no premature auto-end");
        $this->assertContains("endOfMove", $targets, "End Move is offered");
    }
}
