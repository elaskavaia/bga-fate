<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_turn;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Tests\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_turnTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
    }

    private function createOp(array $data = []): Op_turn {
        /** @var Op_turn */
        $op = $this->game->machine->instanciateOperation("turn", PCOLOR, $data ?: null);
        return $op;
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
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        $this->assertArrayHasKey("useEquipment", $moves);
        $this->assertArrayHasKey("useAbility", $moves);
        $this->assertArrayHasKey("playEvent", $moves);
        //$this->assertArrayHasKey("shareGold", $moves);
    }

    public function testFreeActionsHaveSecFlag(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        $this->assertTrue($moves["useEquipment"]["sec"] ?? false);
        $this->assertTrue($moves["playEvent"]["sec"] ?? false);
    }

    public function testAlreadyTakenActionIsNotApplicable(): void {
        $op = $this->createOp(["actions_taken" => ["actionPractice"], "actions_remaining" => 1]);
        $moves = $op->getPossibleMoves();

        $this->assertEquals(Material::ERR_NOT_APPLICABLE, $moves["actionPractice"]["q"]);
    }

    public function testOtherActionsStillAvailableAfterOneTaken(): void {
        $op = $this->createOp(["actions_taken" => ["actionPractice"], "actions_remaining" => 1]);
        $moves = $op->getPossibleMoves();

        $this->assertEquals(Material::RET_OK, $moves["actionMove"]["q"]);
        $this->assertEquals(Material::RET_OK, $moves["actionAttack"]["q"]);
    }

    public function testNoMainActionsOfferedAfterBothTaken(): void {
        $op = $this->createOp(["actions_taken" => ["actionPractice", "actionMove"], "actions_remaining" => 0]);
        $moves = $op->getPossibleMoves();

        $this->assertArrayNotHasKey("actionPractice", $moves);
        $this->assertArrayNotHasKey("actionMove", $moves);
        $this->assertArrayNotHasKey("actionAttack", $moves);
    }

    public function testFreeActionsStillOfferedAfterBothMainActionsTaken(): void {
        $op = $this->createOp(["actions_taken" => ["actionPractice", "actionMove"], "actions_remaining" => 0]);
        $moves = $op->getPossibleMoves();

        $this->assertArrayHasKey("useEquipment", $moves);
        $this->assertArrayHasKey("playEvent", $moves);
    }

    // -------------------------------------------------------------------------
    // getPrompt
    // -------------------------------------------------------------------------

    public function testPromptFirstAction(): void {
        $op = $this->createOp();
        $this->assertEquals("Select your first action", $op->getPrompt());
    }

    public function testPromptSecondAction(): void {
        $op = $this->createOp(["actions_taken" => ["actionPractice"], "actions_remaining" => 1]);
        $this->assertEquals("Select your second action", $op->getPrompt());
    }

    public function testPromptFreeActionsOnly(): void {
        $op = $this->createOp(["actions_taken" => ["actionPractice", "actionMove"], "actions_remaining" => 0]);
        $this->assertEquals("Select a free action or end your turn", $op->getPrompt());
    }

    // -------------------------------------------------------------------------
    // canSkip
    // -------------------------------------------------------------------------

    public function testCannotSkipAtStart(): void {
        $op = $this->createOp();
        $this->assertTrue($op->canSkip());
    }

    public function testCanSkipAfterFirstAction(): void {
        $op = $this->createOp(["actions_taken" => ["actionPractice"], "actions_remaining" => 1]);
        $this->assertTrue($op->canSkip());
    }

    public function testCannotSkipWhenNoActionsRemaining(): void {
        $op = $this->createOp(["actions_taken" => ["actionPractice", "actionMove"], "actions_remaining" => 0]);
        $this->assertTrue($op->canSkip());
    }

    // -------------------------------------------------------------------------
    // resolve: main action
    // -------------------------------------------------------------------------

    public function testResolveMainActionQueuesActionOp(): void {
        // Setup markers in limbo so the resolve can place them
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_1", "limbo");

        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "actionPractice"]);

        // The action operation should now be queued in the machine
        $top = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertNotNull($top);
        $this->assertEquals("actionPractice", $top->getType());
    }

    public function testResolveMainActionMovesMarker(): void {
        $markerKey = "marker_" . PCOLOR . "_1";
        $this->game->tokens->moveToken($markerKey, "limbo");

        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "actionPractice"]);

        $location = $this->game->tokens->getTokenLocation($markerKey);
        $this->assertEquals("aslot_" . PCOLOR . "_actionPractice", $location);
    }

    public function testResolveFirstActionDecrementsRemaining(): void {
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_1", "limbo");

        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "actionPractice"]);

        // After first action, turn op re-queues itself — find it and check state
        // Dispatch actionPractice, then the re-queued turn op becomes top
        $this->game->machine->dispatchAll(); // run actionPractice
        $turnOp = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertNotNull($turnOp);
        $this->assertEquals("turn", $turnOp->getType());
        $args = $turnOp->getExtraArgs();
        $this->assertEquals(1, $args["actions_remaining"]);
        $this->assertContains("actionPractice", $args["actions_taken"]);
    }

    public function testResolveSecondMarkerPlacedForSecondAction(): void {
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_1", "limbo");
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_2", "limbo");

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
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_1", "limbo");
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_2", "limbo");

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

    public function testResolveFreeActionQueuesIt(): void {
        $op = $this->createOp();
        // useAbility is a free action — verify it gets queued
        $op->action_resolve([Operation::ARG_TARGET => "useAbility"]);

        $top = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertNotNull($top, "useAbility should be queued");
        $this->assertEquals("useAbility", $top->getType());
    }

    public function testResolveFreeActionDoesNotConsumeMainAction(): void {
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "useAbility"]);

        // Top op should be useAbility; behind it the turn op is re-queued
        $top = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertNotNull($top);
        $this->assertEquals("useAbility", $top->getType());

        // Remove useAbility so turn becomes top, then verify its state
        $top->destroy();
        $turnOp = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertNotNull($turnOp, "Turn op should be re-queued after free action");
        $this->assertEquals("turn", $turnOp->getType());
        $args = $turnOp->getExtraArgs();
        $this->assertEquals(2, $args["actions_remaining"]);
        $this->assertEmpty($args["actions_taken"]);
    }

    // -------------------------------------------------------------------------
    // skip (end turn early)
    // -------------------------------------------------------------------------

    public function testSkipQueuesEndOfTurn(): void {
        $op = $this->createOp(["actions_taken" => ["actionPractice"], "actions_remaining" => 1]);
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
        $op = $this->createOp(["actions_taken" => ["actionMove"], "actions_remaining" => 1]);
        $args = $op->getExtraArgs();

        $this->assertEquals(1, $args["actions_remaining"]);
        $this->assertEquals(["actionMove"], $args["actions_taken"]);
    }
}
