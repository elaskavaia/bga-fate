<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_order;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_orderTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves
    // -------------------------------------------------------------------------

    public function testGetPossibleMovesTwoOptions(): void {
        $op = $this->game->machine->instanciateOperation("gainXp+drawEvent", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("choice_0", $moves);
        $this->assertArrayHasKey("choice_1", $moves);
        $this->assertCount(2, $moves);
    }

    public function testGetPossibleMovesPropagatesParentData(): void {
        /** @var Op_order */
        $op = $this->game->machine->instanciateOperation("gainXp+drawEvent", PCOLOR, ["card" => "test_card"]);

        $moves = $op->getPossibleMoves();

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
        /** @var Op_order */
        $op = $this->game->machine->instanciateOperation("gainXp+drawEvent", PCOLOR);
        $op->saveToDb(1, true);

        $xpBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));

        $op->action_resolve(["target" => "choice_0"]);

        // After resolving first choice, gainXp should be queued
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("gainXp", $opTypes);
    }

    public function testResolveQueuesRemainingAsOrder(): void {
        /** @var Op_order */
        $op = $this->game->machine->instanciateOperation("gainXp+drawEvent+moveHero", PCOLOR);
        $op->saveToDb(1, true);

        $op->action_resolve(["target" => "choice_0"]);

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
        $op = $this->game->machine->instanciateOperation("gainXp+drawEvent", PCOLOR);
        $this->assertInstanceOf(Op_order::class, $op);
        $this->assertEquals("+", $op->getOperator());
    }

    public function testCountIsOne(): void {
        $op = $this->game->machine->instanciateOperation("gainXp+drawEvent", PCOLOR);
        $this->assertEquals(1, $op->getCount());
        $this->assertEquals(1, $op->getMinCount());
    }

    public function testGetTypeFullExpr(): void {
        $op = $this->game->machine->instanciateOperation("gainXp+drawEvent", PCOLOR);
        $this->assertEquals("gainXp+drawEvent", $op->getTypeFullExpr());
    }
}
