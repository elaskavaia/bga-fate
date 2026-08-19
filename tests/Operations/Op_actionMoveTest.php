<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_actionMove;

final class Op_actionMoveTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
    }

    private function getQueuedOp(): ?array {
        $ops = $this->game->machine->getTopOperations(PCOLOR);
        return $ops ? reset($ops) : null;
    }

    // -------------------------------------------------------------------------
    // Testing possible moves (delegated to move)
    // -------------------------------------------------------------------------

    public function testReachableHexesFromGrimheim(): void {
        // Grimheim is one area, so no hex inside it is a destination (BGA #235653)
        $this->assertNotValidTarget("hex_9_8");
        $this->assertNotValidTarget("hex_10_8");
        $this->assertNotValidTarget("hex_8_10");

        // Adjacent to Grimheim (distance 1)
        $this->assertValidTarget("hex_11_8");
        $this->assertValidTarget("hex_7_8");
    }

    public function testCurrentHexNotReachable(): void {
        // Current hex should not be in the list
        $this->assertNotValidTarget("hex_9_9");
    }

    public function testTooFarHexesNotReachable(): void {
        // hex_9_1 is far from Grimheim (> 3 steps)
        $this->assertNotValidTarget("hex_9_1");
    }

    public function testMountainHexesNotReachable(): void {
        // hex_9_11 is mountain, should not be reachable
        $this->assertNotValidTarget("hex_9_11");
    }

    public function testOccupiedHexBlocks(): void {
        // Place another hero on an adjacent hex
        $this->game->tokens->moveToken("hero_2", "hex_11_8");
        // Occupied hex should not be reachable
        $this->assertNotValidTarget("hex_11_8");
    }

    public function testNoNonMapLocationsOffered(): void {
        $this->assertNotValidTarget("limbo");
        $this->assertNotValidTarget("supply_crystal_yellow");
        $this->assertNotValidTarget("timetrack_1");
    }

    // -------------------------------------------------------------------------
    // resolve (delegates to [1,3]move)
    // -------------------------------------------------------------------------

    public function testResolveQueuesMoveHero(): void {
        $op = $this->op;
        $op->resolve();
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("[1,3]move", $queued["type"]);
    }

    public function testGetNumberOfMovesDefault3(): void {
        /** @var Op_actionMove */
        $op = $this->op;
        $this->assertEquals(3, $op->getNumberOfMoves());
    }

    // -------------------------------------------------------------------------
    // step mode selection (delegate to moveStep when routing matters)
    // -------------------------------------------------------------------------

    public function testNormalMoveWithoutStepIncentive(): void {
        /** @var Op_actionMove */
        $op = $this->op;
        [$type] = $op->getDelegateInfo();
        $this->assertEquals("[1,3]move", $type, "no per-step incentive -> normal one-click move");
    }

    public function testStepModeWhenActiveQuestListensOnStep(): void {
        // Raven's Claw (card_equip_3_22) has quest_on=TStep; put it on top of the equipment deck.
        $this->game->tokens->moveToken("card_equip_3_22", "deck_equip_" . PCOLOR, 9999);
        /** @var Op_actionMove */
        $op = $this->op;
        [$type, $data] = $op->getDelegateInfo();
        $this->assertEquals("moveStep", $type);
        $this->assertSame(3, $data["budget"]);
        $this->assertSame(0, $data["moved"]);
    }

    public function testStepModeWhenTableauCardListensOnStep(): void {
        // Treetreader II (card_ability_2_6) has an onStep hook.
        $this->game->tokens->moveToken("card_ability_2_6", "tableau_" . PCOLOR);
        /** @var Op_actionMove */
        $op = $this->op;
        [$type] = $op->getDelegateInfo();
        $this->assertEquals("moveStep", $type);
    }

    public function testHardcodedCustomStepQuestEnablesStepMode(): void {
        // Shield (card_equip_4_16) is a quest_on=custom quest whose triggerQuest reacts to Step;
        // it's caught via the hardcoded allowlist (no declarative quest_on=TStep to read).
        $this->game->tokens->moveToken("card_equip_4_16", "deck_equip_" . PCOLOR, 9999);
        /** @var Op_actionMove */
        $op = $this->op;
        [$type] = $op->getDelegateInfo();
        $this->assertEquals("moveStep", $type);
    }

    public function testCustomCardDoesNotEnableStepMode(): void {
        // Bloodline Crystal (card_equip_2_25) has on=custom but no onStep hook: it must NOT be
        // mistaken for a per-step listener (regression for the canTriggerEffectOn false-positive).
        $this->game->tokens->moveToken("card_equip_2_25", "tableau_" . PCOLOR);
        /** @var Op_actionMove */
        $op = $this->op;
        [$type] = $op->getDelegateInfo();
        $this->assertEquals("[1,3]move", $type, "an on=custom card is not a step incentive");
    }

    private function enableConfirmMovePreference(): void {
        $this->game->userPreferences->_set(PCOLOR_ID, Material::MA_PREF_CONFIRM_MOVE, 1);
    }

    public function testConfirmMovePreferenceEnablesStepMode(): void {
        $this->enableConfirmMovePreference();
        /** @var Op_actionMove */
        $op = $this->op;
        [$type, $data] = $op->getDelegateInfo();
        $this->assertEquals("moveStep", $type, "preference forces step mode without any card incentive");
        $this->assertSame(3, $data["budget"]);
    }

    public function testWreckingBallAloneEnablesStepMode(): void {
        // Moving into occupied hexes is offered per step, so the card itself is a step incentive.
        $this->game->tokens->moveToken("card_ability_4_8", "tableau_" . PCOLOR); // Wrecking Ball II
        /** @var Op_actionMove */
        $op = $this->op;
        [$type] = $op->getDelegateInfo();
        $this->assertEquals("moveStep", $type, "Wrecking Ball forces step mode without any other incentive");
    }

    public function testWreckingBallKeepsConfirmMovePreference(): void {
        $this->enableConfirmMovePreference();
        $this->game->tokens->moveToken("card_ability_4_8", "tableau_" . PCOLOR); // Wrecking Ball II
        /** @var Op_actionMove */
        $op = $this->op;
        [$type] = $op->getDelegateInfo();
        $this->assertEquals("moveStep", $type, "Wrecking Ball no longer vetoes step mode -- moveStep offers it too");
    }

    public function testWreckingBallDoesNotDisableStepMode(): void {
        $this->game->tokens->moveToken("card_ability_2_6", "tableau_" . PCOLOR); // step incentive
        $this->game->tokens->moveToken("card_ability_4_8", "tableau_" . PCOLOR); // Wrecking Ball II
        /** @var Op_actionMove */
        $op = $this->op;
        [$type] = $op->getDelegateInfo();
        $this->assertEquals("moveStep", $type, "a step incentive still wins with Wrecking Ball on the tableau");
    }
}
