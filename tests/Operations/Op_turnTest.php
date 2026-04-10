<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_turn;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_turnTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
        $this->game->clearHand(); // no random event card
    }

    private function createOp(): Op_turn {
        /** @var Op_turn */
        $op = $this->game->machine->instanciateOperation("turn", PCOLOR);
        return $op;
    }

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
    // getPossibleMoves
    // -------------------------------------------------------------------------

    public function testAllMainActionsOfferedAtStart(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        $this->assertArrayHasKey("actionMove", $moves);
        $this->assertArrayHasKey("actionAttack", $moves);
        $this->assertArrayHasKey("actionPrepare", $moves);
        $this->assertArrayHasKey("actionFocus", $moves);
        $this->assertArrayHasKey("actionMend", $moves);
        $this->assertArrayHasKey("actionPractice", $moves);
    }

    public function testMainActionsAvailableAtStart(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        $this->assertEquals(Material::RET_OK, $moves["actionPractice"]["q"]);
        $this->assertEquals(Material::RET_OK, $moves["actionMove"]["q"]);
    }

    public function testFreeActionsOfferedAtStart(): void {
        $this->game->tokens->moveToken("card_event_1_27", "hand_" . PCOLOR);
        // Add damage so heal(self) from Rest card has valid targets
        $this->game->effect_moveCrystals("hero_1", "red", 3, "hero_1", ["message" => ""]);
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        // Free actions are inlined — individual cards appear with "action" => "playEvent"
        $this->assertArrayHasKey("card_event_1_27", $moves);
        $this->assertEquals("playEvent", $moves["card_event_1_27"]["action"]);
    }

    public function testFreeActionsStillOfferedAfterBothMainActionsTaken(): void {
        $this->game->tokens->moveToken("card_event_1_27", "hand_" . PCOLOR);
        // Add damage so heal(self) from Rest card has valid targets
        $this->game->effect_moveCrystals("hero_1", "red", 3, "hero_1", ["message" => ""]);
        $this->simulateBothActionsTaken("actionPractice", "actionMove");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        $this->assertArrayHasKey("card_event_1_27", $moves);
        $this->assertEquals("playEvent", $moves["card_event_1_27"]["action"]);
    }

    public function testAlreadyTakenActionIsNotApplicable(): void {
        $this->simulateActionTaken("actionPractice");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        $this->assertEquals(Material::ERR_NOT_APPLICABLE, $moves["actionPractice"]["q"]);
    }

    public function testOtherActionsStillAvailableAfterOneTaken(): void {
        $this->simulateActionTaken("actionPractice");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        $this->assertEquals(Material::RET_OK, $moves["actionMove"]["q"]);
        $this->assertNotEquals(Material::ERR_NOT_APPLICABLE, $moves["actionAttack"]["q"]);
    }

    public function testNoMainActionsOfferedAfterBothTaken(): void {
        $this->simulateBothActionsTaken("actionPractice", "actionMove");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        $this->assertArrayNotHasKey("actionPractice", $moves);
        $this->assertArrayNotHasKey("actionMove", $moves);
        $this->assertArrayNotHasKey("actionAttack", $moves);
    }

    // -------------------------------------------------------------------------
    // getPrompt
    // -------------------------------------------------------------------------

    public function testPromptFirstAction(): void {
        $op = $this->createOp();
        $this->assertEquals("Select your first action or free action", $op->getPrompt());
    }

    public function testPromptSecondAction(): void {
        $this->simulateActionTaken("actionPractice");
        $op = $this->createOp();
        $this->assertEquals("Select your second action or free action", $op->getPrompt());
    }

    public function testPromptNoValidActionsRemain(): void {
        $this->simulateBothActionsTaken("actionPractice", "actionMove");
        $op = $this->createOp();
        $this->assertEquals("Confirm end of turn", $op->getPrompt());
    }

    // -------------------------------------------------------------------------
    // canSkip
    // -------------------------------------------------------------------------

    public function testCannotSkipAtStart(): void {
        $op = $this->createOp();
        $this->assertFalse($op->canSkip());
    }

    public function testCannotSkipWithOneActionRemaining(): void {
        $this->simulateActionTaken("actionPractice");
        $op = $this->createOp();
        $this->assertFalse($op->canSkip());
    }

    public function testCanSkipWhenNoActionsRemaining(): void {
        $this->simulateBothActionsTaken("actionPractice", "actionMove");
        $op = $this->createOp();
        $this->assertTrue($op->canSkip());
    }

    // -------------------------------------------------------------------------
    // resolve: main action
    // -------------------------------------------------------------------------

    public function testResolveMainActionQueuesActionOp(): void {
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "actionPractice"]);

        $top = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertNotNull($top);
        $this->assertEquals("actionPractice", $top->getType());
    }

    public function testResolveMainActionMovesMarker(): void {
        $markerKey = "marker_" . PCOLOR . "_1";

        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "actionPractice"]);

        $location = $this->game->tokens->getTokenLocation($markerKey);
        $this->assertEquals("aslot_" . PCOLOR . "_actionPractice", $location);
    }

    public function testResolveFirstActionDecrementsRemaining(): void {
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "actionPractice"]);

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
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "actionPractice"]);
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
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "actionPractice"]);
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
    // getPossibleMoves: delegate sub-targets
    // -------------------------------------------------------------------------

    public function testPossibleMovesIncludesDelegateTargets(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

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
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertEquals(0, count($moves));
    }

    // -------------------------------------------------------------------------
    // resolve: delegate target
    // -------------------------------------------------------------------------

    public function testResolveDelegateTargetQueuesAction(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        $hexTarget = null;
        foreach ($moves as $key => $value) {
            if (str_starts_with($key, "hex_") && ($value["action"] ?? "") === "actionMove") {
                $hexTarget = $key;
                break;
            }
        }
        $this->assertNotNull($hexTarget, "Expected a hex delegate target");

        $op->action_resolve([Operation::ARG_TARGET => $hexTarget]);

        $top = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertNotNull($top);
        $this->assertEquals("actionMove", $top->getType());
    }

    // -------------------------------------------------------------------------
    // skip (end turn early)
    // -------------------------------------------------------------------------

    public function testSkipQueuesEndOfTurn(): void {
        $this->simulateBothActionsTaken("actionPractice", "actionMove");
        $op = $this->createOp();
        $op->skip();

        $top = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertNotNull($top);
        $this->assertEquals("turnEnd", $top->getType());
    }

    // -------------------------------------------------------------------------
    // getExtraArgs
    // -------------------------------------------------------------------------

    public function testExtraArgsAtStart(): void {
        $op = $this->createOp();
        $args = $op->getExtraArgs();

        $this->assertEquals(2, $args["actions_remaining"]);
        $this->assertEmpty($args["actions_taken"]);
    }

    public function testExtraArgsAfterOneAction(): void {
        $this->simulateActionTaken("actionMove");
        $op = $this->createOp();
        $args = $op->getExtraArgs();

        $this->assertEquals(1, $args["actions_remaining"]);
        $this->assertEquals(["actionMove"], $args["actions_taken"]);
    }
}
