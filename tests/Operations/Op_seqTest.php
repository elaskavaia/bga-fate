<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_seq;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

use function Bga\Games\Fate\toJson;

final class Op_seqTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
    }

    // -------------------------------------------------------------------------
    // basics
    // -------------------------------------------------------------------------

    public function testGetOperator(): void {
        $op = $this->game->machine->instanciateOperation("gainXp,drawEvent", PCOLOR);
        $this->assertInstanceOf(Op_seq::class, $op);
        $this->assertEquals(",", $op->getOperator());
    }

    public function testGetTypeFullExpr(): void {
        $op = $this->game->machine->instanciateOperation("gainXp,drawEvent", PCOLOR);
        $this->assertEquals("gainXp,drawEvent", $op->getTypeFullExpr());
    }

    public function testCopyRoundtrip(): void {
        $op = $this->game->machine->instanciateOperation("gainXp,drawEvent", PCOLOR, ["reason" => "test"]);
        $copy = $op->copy();
        $this->assertEquals("gainXp,drawEvent", $copy->getTypeFullExpr());
        $this->assertEquals("test", $copy->getDataField("reason"));
    }

    // -------------------------------------------------------------------------
    // expandOperation — auto-expands into queued sub-ops
    // -------------------------------------------------------------------------

    public function testExpandQueuesSubOps(): void {
        $op = $this->game->machine->instanciateOperation("gainXp,drawEvent", PCOLOR);
        $op->saveToDb(1, true);

        $xpBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $this->game->machine->dispatchAll();
        $xpAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));

        $this->assertEquals($xpBefore + 1, $xpAfter, "XP should be gained from first sub-op");
    }

    public function testExpandWithCount(): void {
        // 2(gainXp,drawEvent) — each sub-op should execute with count*2
        $op = $this->game->machine->instanciateOperation("2(gainXp,drawEvent)", PCOLOR);
        $op->saveToDb(1, true);

        $xpBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $this->game->machine->dispatchAll();
        $xpAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));

        $this->assertEquals($xpBefore + 2, $xpAfter, "XP should be gained twice");
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves — delegates to first sub-op
    // -------------------------------------------------------------------------

    public function testGetPossibleMovesFromFirstSub(): void {
        // First sub is gainXp which auto-resolves, so getPossibleMoves delegates to it
        $op = $this->game->machine->instanciateOperation("gainXp,drawEvent", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertNotEmpty($moves);
        $this->assertEquals(0, $op->getErrorCode());
    }

    public function testEmptyDelegatesReturnsEmpty(): void {
        /** @var Op_seq */
        $op = $this->game->machine->instanciateOperation("seq", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    // -------------------------------------------------------------------------
    // data propagation
    // -------------------------------------------------------------------------

    public function testParentDataPropagatedToSubOps(): void {
        $op = $this->game->machine->instanciateOperation("gainXp,drawEvent", PCOLOR, ["card" => "test_card"]);
        $this->assertEquals("test_card", $op->delegates[0]->getDataField("card"));
        $this->assertEquals("test_card", $op->delegates[1]->getDataField("card"));
    }

    public function testComplexInst(): void {
        $op = $this->game->machine->instanciateOperation("[0,2](gainAtt,3gainXp)", PCOLOR, ["reason" => "kick"]);
        $copy = $op->copy();
        $copy->queueOp($copy);
        $op = $this->game->machine->createTopOperationFromDbForOwner();
        $this->assertEquals("[0,2](gainAtt,3(gainXp))", $op->getTypeFullExpr());
        $this->assertEquals(["reason" => "kick", "count" => 2, "mcount" => 0], $op->getData());
        $this->game->fakeUserAction($op, "1");
        $this->game->machine->dispatchAll();
        $op = $this->game->machine->createTopOperationFromDbForOwner();
        $this->assertEquals("turn", $op->getTypeFullExpr());
    }
}
