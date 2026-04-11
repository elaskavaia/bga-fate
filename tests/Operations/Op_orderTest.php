<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_order;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_orderTest extends AbstractOpTestCase {
    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());
    }

    // -------------------------------------------------------------------------
    // Testing possible moves
    // -------------------------------------------------------------------------

    public function testGetPossibleMovesTwoOptions(): void {
        $this->op = $this->createOp("gainXp+drawEvent");
        $this->assertValidTarget("choice_0");
        $this->assertValidTarget("choice_1");
        $this->assertValidTargetCount(2);
    }

    public function testGetPossibleMovesPropagatesParentData(): void {
        /** @var Op_order */
        $op = $this->createOp("gainXp+drawEvent", ["card" => "test_card"]);

        $moves = $op->getArgsInfo();

        $this->assertArrayHasKey("choice_0", $moves);
        $this->assertArrayHasKey("choice_1", $moves);

        // Parent data should have been propagated to delegates
        $this->assertEquals("test_card", $op->delegates[0]->getDataField("card"));
        $this->assertEquals("test_card", $op->delegates[1]->getDataField("card"));
    }

    // -------------------------------------------------------------------------
    // resolve
    // -------------------------------------------------------------------------

    public function testResolveChooseFirst(): void {
        $this->op = $this->createOp("gainXp+drawEvent");
        $this->op->saveToDb(1, true);

        $xpBefore = $this->countYellowCrystals($this->getPlayersTableau());

        $this->call_resolve("choice_0");

        // After resolving first choice, gainXp should be queued
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("gainXp", $opTypes);
    }

    public function testResolveQueuesRemainingAsOrder(): void {
        $this->op = $this->createOp("gainXp+drawEvent+moveHero");
        $this->op->saveToDb(1, true);

        $this->call_resolve("choice_0");

        // After choosing first, remaining 2 should still be queued as order
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("gainXp", $opTypes);
        $this->assertContains("order", $opTypes);
    }

    // -------------------------------------------------------------------------
    // basics
    // -------------------------------------------------------------------------

    public function testGetOperator(): void {
        $op = $this->createOp("gainXp+drawEvent");
        $this->assertInstanceOf(Op_order::class, $op);
        $this->assertEquals("+", $op->getOperator());
    }

    public function testCountIsOne(): void {
        $op = $this->createOp("gainXp+drawEvent");
        $this->assertEquals(1, $op->getCount());
        $this->assertEquals(1, $op->getMinCount());
    }

    public function testGetTypeFullExpr(): void {
        $op = $this->createOp("gainXp+drawEvent");
        $this->assertEquals("gainXp+drawEvent", $op->getTypeFullExpr());
    }
}
