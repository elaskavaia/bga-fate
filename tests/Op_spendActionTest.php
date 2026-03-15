<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_spendAction;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_spendActionTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
        // Fresh turn: both markers in limbo
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_1", "limbo");
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_2", "limbo");
    }

    private function createOp(string $expr = "spendAction(actionPrepare)"): Op_spendAction {
        /** @var Op_spendAction */
        return $this->game->machine->instanciateOperation($expr, PCOLOR);
    }

    public function testAvailableWithFreshTurn(): void {
        $op = $this->createOp();
        $this->assertEquals(0, $op->getErrorCode());
    }

    public function testFailsIfActionAlreadyTaken(): void {
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_1", "aslot_" . PCOLOR . "_actionPrepare");
        $op = $this->createOp();
        $this->assertNotEquals(0, $op->getErrorCode());
    }

    public function testFailsIfNoActionsRemaining(): void {
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_1", "aslot_" . PCOLOR . "_actionMove");
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_2", "aslot_" . PCOLOR . "_actionAttack");
        $op = $this->createOp();
        $this->assertNotEquals(0, $op->getErrorCode());
    }

    public function testResolveMovesMarkerToAslot(): void {
        $op = $this->createOp();
        $op->action_resolve([]);

        $loc = $this->game->tokens->getTokenLocation("marker_" . PCOLOR . "_1");
        $this->assertEquals("aslot_" . PCOLOR . "_actionPrepare", $loc);
    }

    public function testResolveUsesSecondMarkerIfFirstAlreadyPlaced(): void {
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_1", "aslot_" . PCOLOR . "_actionMove");
        $op = $this->createOp();
        $op->action_resolve([]);

        $loc = $this->game->tokens->getTokenLocation("marker_" . PCOLOR . "_2");
        $this->assertEquals("aslot_" . PCOLOR . "_actionPrepare", $loc);
    }
}
