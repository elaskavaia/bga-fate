<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_turn;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_turnTest extends AbstractOpTestCase {
    /** Simulate one action already taken by placing marker 1 in its aslot */
    private function simulateActionTaken(string $actionType): void {
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_1", "aslot_" . PCOLOR . "_" . $actionType);
    }

    /** Simulate both actions taken */
    private function simulateBothActionsTaken(string $action1, string $action2): void {
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_1", "aslot_" . PCOLOR . "_" . $action1);
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_2", "aslot_" . PCOLOR . "_" . $action2);
    }

    // -------------------------------------------------------------------------
    // Testing possible moves
    // -------------------------------------------------------------------------

    public function testAllMainActionsOfferedAtStart(): void {
        $this->assertValidTarget("actionMove");
        $this->assertNotValidTarget("actionAttack");
        $this->assertValidTarget("actionPrepare");
        $this->assertValidTarget("actionFocus");
        $this->assertNotValidTarget("actionMend");
        $this->assertValidTarget("actionPractice");
    }

    public function testMainActionsAvailableAtStart(): void {
        $this->assertValidTarget("actionPractice");
        $this->assertValidTarget("actionMove");
    }

    public function testFreeActionsOfferedAtStart(): void {
        $this->game->tokens->moveToken("card_event_1_27", "hand_" . PCOLOR);
        // Add damage so heal(self) from Rest card has valid targets
        $this->game->effect_moveCrystals("hero_1", "red", 3, "hero_1", ["message" => ""]);

        // Free actions are inlined — individual cards appear with "action" => "playEvent"
        $info = $this->getTargetInfo("card_event_1_27");
        $this->assertEquals("playEvent", $info["action"]);
    }

    public function testFreeActionsStillOfferedAfterBothMainActionsTaken(): void {
        $this->game->tokens->moveToken("card_event_1_27", "hand_" . PCOLOR);
        // Add damage so heal(self) from Rest card has valid targets
        $this->game->effect_moveCrystals("hero_1", "red", 3, "hero_1", ["message" => ""]);
        $this->simulateBothActionsTaken("actionPractice", "actionMove");

        $info = $this->getTargetInfo("card_event_1_27");
        $this->assertEquals("playEvent", $info["action"]);
    }

    public function testAlreadyTakenActionIsNotApplicable(): void {
        $this->simulateActionTaken("actionPractice");
        $this->assertTargetError("actionPractice", Material::ERR_NOT_APPLICABLE);
    }

    public function testOtherActionsStillAvailableAfterOneTaken(): void {
        $this->simulateActionTaken("actionPractice");
        $this->assertValidTarget("actionMove");
        $this->assertValidTarget("actionFocus");
    }

    public function testNoMainActionsOfferedAfterBothTaken(): void {
        $this->simulateBothActionsTaken("actionPractice", "actionMove");
        $this->assertNotValidTarget("actionPractice");
        $this->assertNotValidTarget("actionMove");
        $this->assertNotValidTarget("actionAttack");
    }

    // -------------------------------------------------------------------------
    // getPrompt
    // -------------------------------------------------------------------------

    public function testPromptFirstAction(): void {
        $op = $this->op;
        $this->assertEquals("Select your first action or free action", $op->getPrompt());
    }

    public function testPromptSecondAction(): void {
        $this->simulateActionTaken("actionPractice");
        $op = $this->op;
        $this->assertEquals("Select your second action or free action", $op->getPrompt());
    }

    public function testPromptNoValidActionsRemain(): void {
        $this->simulateBothActionsTaken("actionPractice", "actionMove");
        $op = $this->op;
        $this->assertEquals("Confirm end of turn", $op->getPrompt());
    }

    // -------------------------------------------------------------------------
    // canSkip
    // -------------------------------------------------------------------------

    public function testCannotSkipAtStart(): void {
        $op = $this->op;
        $this->assertFalse($op->canSkip());
    }

    public function testCannotSkipWithOneActionRemaining(): void {
        $this->simulateActionTaken("actionPractice");
        $op = $this->op;
        $this->assertFalse($op->canSkip());
    }

    public function testCanSkipWhenNoActionsRemaining(): void {
        $this->simulateBothActionsTaken("actionPractice", "actionMove");
        $op = $this->op;
        $this->assertTrue($op->canSkip());
    }

    // -------------------------------------------------------------------------
    // resolve: main action
    // -------------------------------------------------------------------------

    public function testResolveMainActionQueuesActionOp(): void {
        $op = $this->op;
        $this->call_resolve("actionPractice");

        $top = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertNotNull($top);
        $this->assertEquals("actionPractice", $top->getType());
    }

    public function testResolveMainActionMovesMarker(): void {
        $markerKey = "marker_" . PCOLOR . "_1";

        $op = $this->op;
        $this->call_resolve("actionPractice");

        $location = $this->game->tokens->getTokenLocation($markerKey);
        $this->assertEquals("aslot_" . PCOLOR . "_actionPractice", $location);
    }

    public function testResolveFirstActionDecrementsRemaining(): void {
        $op = $this->op;
        $this->call_resolve("actionPractice");

        $this->game->machine->dispatchAll(); // run actionPractice
        $turnOp = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertNotNull($turnOp);
        $this->assertEquals("turn", $turnOp->getType());
        $args = $turnOp->getExtraArgs();
        $this->assertEquals(1, $args["actions_remaining"]);
        $this->assertContains("actionPractice", $args["actions_taken"]);
    }

    public function testResolveSecondMarkerPlacedForSecondAction(): void {
        // First action
        $op = $this->op;
        $this->call_resolve("actionPractice");
        $this->game->machine->dispatchAll(); // run actionPractice, returns to turn

        // Second action
        $turnOp = $this->game->machine->createTopOperationFromDbForOwner(null);
        $turnOp->action_resolve([Operation::ARG_TARGET => "actionMove"]);

        $marker2 = "marker_" . PCOLOR . "_2";
        $location = $this->game->tokens->getTokenLocation($marker2);
        $this->assertEquals("aslot_" . PCOLOR . "_actionMove", $location);
    }

    // -------------------------------------------------------------------------
    // resolve: duplicate action rejected
    // -------------------------------------------------------------------------

    public function testResolveDuplicateMainActionThrows(): void {
        // Take practice as first action
        $op = $this->op;
        $this->call_resolve("actionPractice");
        $this->game->machine->dispatchAll();

        // Try to take practice again as second action — should throw
        $turnOp = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->expectException(\Bga\GameFramework\UserException::class);
        $turnOp->action_resolve([Operation::ARG_TARGET => "actionPractice"]);
    }

    // -------------------------------------------------------------------------
    // resolve: free action
    // -------------------------------------------------------------------------

    // DO NOT REMOVE comment - waiting for impl
    // public function testResolveFreeActionQueuesIt(): void { ... }
    // public function testResolveFreeActionDoesNotConsumeMainAction(): void { ... }

    // -------------------------------------------------------------------------
    // Testing possible moves: delegate sub-targets
    // -------------------------------------------------------------------------

    public function testPossibleMovesIncludesDelegateTargets(): void {
        $op = $this->op;
        $moves = $op->getArgsInfo();

        $hasDelegateTarget = false;
        foreach ($moves as $key => $value) {
            if (str_starts_with($key, "hex_") && isset($value["action"])) {
                $hasDelegateTarget = true;
                $this->assertEquals("actionMove", $value["action"]);
                $this->assertEquals(0, $value["q"]);
                break;
            }
        }
        $this->assertTrue($hasDelegateTarget, "Expected at least one hex delegate target from actionMove");
    }

    public function testDelegateTargetsNotPresentWhenNoActionsRemaining(): void {
        $this->simulateBothActionsTaken("actionPractice", "actionMove");
        $this->assertNoValidTargets();
    }

    // -------------------------------------------------------------------------
    // resolve: delegate target
    // -------------------------------------------------------------------------

    public function testResolveDelegateTargetQueuesAction(): void {
        $op = $this->op;
        $moves = $op->getArgsInfo();

        $hexTarget = null;
        foreach ($moves as $key => $value) {
            if (str_starts_with($key, "hex_") && ($value["action"] ?? "") === "actionMove") {
                $hexTarget = $key;
                break;
            }
        }
        $this->assertNotNull($hexTarget, "Expected a hex delegate target");

        $this->call_resolve($hexTarget);

        $top = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertNotNull($top);
        $this->assertEquals("actionMove", $top->getType());
    }

    // -------------------------------------------------------------------------
    // skip (end turn early)
    // -------------------------------------------------------------------------

    public function testSkipQueuesEndOfTurn(): void {
        $this->simulateBothActionsTaken("actionPractice", "actionMove");
        $op = $this->op;
        $op->skip();

        $top = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertNotNull($top);
        $this->assertEquals("turnEnd", $top->getType());
    }

    // -------------------------------------------------------------------------
    // getExtraArgs
    // -------------------------------------------------------------------------

    public function testExtraArgsAtStart(): void {
        $op = $this->op;
        $args = $op->getExtraArgs();

        $this->assertEquals(2, $args["actions_remaining"]);
        $this->assertEmpty($args["actions_taken"]);
    }

    public function testExtraArgsAfterOneAction(): void {
        $this->simulateActionTaken("actionMove");
        $op = $this->op;
        $args = $op->getExtraArgs();

        $this->assertEquals(1, $args["actions_remaining"]);
        $this->assertEquals(["actionMove"], $args["actions_taken"]);
    }
}
