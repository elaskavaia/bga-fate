<?php

declare(strict_types=1);

final class Op_spendActionTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("card_hero_1_1", $this->getPlayersTableau());
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
        // Fresh turn: both markers in limbo
        $this->game->tokens->moveToken("marker_" . $this->owner . "_1", "limbo");
        $this->game->tokens->moveToken("marker_" . $this->owner . "_2", "limbo");
    }

    public function testAvailableWithFreshTurn(): void {
        $this->createOp("spendAction(actionPrepare)");
        $this->assertEquals(0, $this->op->getErrorCode());
    }

    public function testFailsIfActionAlreadyTaken(): void {
        $this->game->tokens->moveToken("marker_" . $this->owner . "_1", "aslot_" . $this->owner . "_actionPrepare");
        $this->createOp("spendAction(actionPrepare)");
        $this->assertNoValidTargets();
    }

    public function testFailsIfNoActionsRemaining(): void {
        $this->game->tokens->moveToken("marker_" . $this->owner . "_1", "aslot_" . $this->owner . "_actionMove");
        $this->game->tokens->moveToken("marker_" . $this->owner . "_2", "aslot_" . $this->owner . "_actionAttack");
        $this->createOp("spendAction(actionPrepare)");
        $this->assertNoValidTargets();
    }

    public function testResolveMovesMarkerToAslot(): void {
        $this->createOp("spendAction(actionPrepare)");
        $this->call_resolve();

        $loc = $this->game->tokens->getTokenLocation("marker_" . $this->owner . "_1");
        $this->assertEquals("aslot_" . $this->owner . "_actionPrepare", $loc);
    }

    public function testResolveUsesSecondMarkerIfFirstAlreadyPlaced(): void {
        $this->game->tokens->moveToken("marker_" . $this->owner . "_1", "aslot_" . $this->owner . "_actionMove");
        $this->createOp("spendAction(actionPrepare)");
        $this->call_resolve();

        $loc = $this->game->tokens->getTokenLocation("marker_" . $this->owner . "_2");
        $this->assertEquals("aslot_" . $this->owner . "_actionPrepare", $loc);
    }
}
