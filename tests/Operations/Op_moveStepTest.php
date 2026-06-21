<?php

declare(strict_types=1);

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

    public function testBackAndForthReEntryIsAllowed(): void {
        // Step to hex_12_8, then the start hex must be offered again so the player can step back
        // (re-entering areas is the whole point of step mode).
        $this->createOp("moveStep", ["budget" => 3, "moved" => 0, "reason" => "Op_actionMove"]);
        $this->call_resolve("hex_12_8");
        $this->dispatchAll();

        $next = null;
        foreach ($this->game->machine->getTopOperations($this->owner) as $row) {
            if (($row["type"] ?? "") === "moveStep") {
                $next = $this->game->machine->instantiateOperationFromDbRow($row);
                break;
            }
        }
        $this->assertNotNull($next, "a follow-up moveStep should be queued");
        $this->assertContains($this->heroHex, $next->getArgsTarget(), "the hex just left is offered again (back-and-forth)");
    }
}
