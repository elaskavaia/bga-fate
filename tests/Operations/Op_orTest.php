<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\CountableOperation;
use Bga\Games\Fate\Operations\Op_or;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_orTest extends AbstractOpTestCase {
    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves
    // -------------------------------------------------------------------------

    public function testGetPossibleMovesTwoOptions(): void {
        $op = $this->createOp("gainXp/drawEvent");
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("choice_0", $moves);
        $this->assertArrayHasKey("choice_1", $moves);
        $this->assertCount(2, $moves);
    }

    public function testGetPossibleMovesPropagatesParentData(): void {
        /** @var Op_or */
        $op = $this->createOp("gainXp/2drawEvent", ["card" => "test_card"]);

        $moves = $op->getPossibleMoves();

        $this->assertArrayHasKey("choice_0", $moves);
        $this->assertArrayHasKey("choice_1", $moves);

        // Delegate counts should be preserved
        $this->assertEquals(2, $op->delegates[1]->getDataField("count"), "Second delegate count should still be 2");

        // Parent data should have been propagated to delegates
        $this->assertEquals("test_card", $op->delegates[0]->getDataField("card"), "Parent data should propagate to first delegate");
        $this->assertEquals("test_card", $op->delegates[1]->getDataField("card"), "Parent data should propagate to second delegate");
    }

    // -------------------------------------------------------------------------
    // resolve
    // -------------------------------------------------------------------------

    public function testResolveChooseFirst(): void {
        $this->op = $this->createOp("gainXp/drawEvent");
        $this->op->saveToDb(1, true);

        $xpBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));

        $this->call_resolve("choice_0");
        $this->game->machine->dispatchAll();

        $xpAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $this->assertEquals($xpBefore + 1, $xpAfter, "XP should be gained");
    }

    public function testResolveChooseSecond(): void {
        $this->op = $this->createOp("gainXp/drawEvent");
        $this->op->saveToDb(1, true);

        $xpBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));

        $this->call_resolve("choice_1");
        $this->game->machine->dispatchAll();

        $xpAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $this->assertEquals($xpBefore, $xpAfter, "XP should not be gained when choosing second option");
    }

    // -------------------------------------------------------------------------
    // basics
    // -------------------------------------------------------------------------

    public function testGetOperator(): void {
        $op = $this->createOp("gainXp/drawEvent");
        $this->assertInstanceOf(Op_or::class, $op);
        $this->assertEquals("/", $op->getOperator());
    }

    public function testCountIsOne(): void {
        /** @var CountableOperation */
        $op = $this->createOp("gainXp/drawEvent");
        $this->assertEquals(1, $op->getCount());
        $this->assertEquals(1, $op->getMinCount());
    }

    public function testGetTypeFullExpr(): void {
        $op = $this->createOp("gainXp/drawEvent");
        $this->assertEquals("gainXp/drawEvent", $op->getTypeFullExpr());
    }

    public function testComplexInst(): void {
        $op = $this->createOp("2(gainAtt/3gainXp)", ["reason" => "kick"]);
        $copy = $op->copy();
        $copy->queueOp($copy);
        $op = $this->game->machine->createTopOperationFromDbForOwner();
        $this->assertEquals("2(gainAtt/3(gainXp))", $op->getTypeFullExpr());
        $this->assertEquals(["reason" => "kick", "count" => 2, "mcount" => 2], $op->getData());
        $this->game->fakeUserAction($op, "choice_0");
        $this->game->machine->dispatchAll();
        $op = $this->game->machine->createTopOperationFromDbForOwner();
        $this->assertEquals("gainAtt/3(gainXp)", $op->getTypeFullExpr());
    }
}
