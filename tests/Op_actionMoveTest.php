<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_actionMove;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_actionMoveTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        // Assign hero 1 to PCOLOR player
        $this->game->tokens->moveToken("card_hero_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
    }

    private function createOp(): Op_actionMove {
        /** @var Op_actionMove */
        $op = $this->game->machine->instanciateOperation("actionMove", PCOLOR);
        return $op;
    }

    private function getQueuedOp(): ?array {
        $ops = $this->game->machine->getTopOperations(PCOLOR);
        return $ops ? reset($ops) : null;
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves (delegated to moveHero)
    // -------------------------------------------------------------------------

    public function testReachableHexesFromGrimheim(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        // Grimheim hexes (other than starting hex) are reachable at distance 0
        $this->assertArrayHasKey("hex_9_8", $moves);
        $this->assertArrayHasKey("hex_10_8", $moves);
        $this->assertArrayHasKey("hex_8_10", $moves);

        // Adjacent to Grimheim (distance 1)
        $this->assertArrayHasKey("hex_11_8", $moves);
        $this->assertArrayHasKey("hex_7_8", $moves);
    }

    public function testCurrentHexNotReachable(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        // Current hex should not be in the list
        $this->assertArrayNotHasKey("hex_9_9", $moves);
    }

    public function testTooFarHexesNotReachable(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        // hex_9_1 is far from Grimheim (> 3 steps)
        $this->assertArrayNotHasKey("hex_9_1", $moves);
    }

    public function testMountainHexesNotReachable(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        // hex_9_11 is mountain, should not be reachable
        $this->assertArrayNotHasKey("hex_9_11", $moves);
    }

    public function testOccupiedHexBlocks(): void {
        // Place another hero on an adjacent hex
        $this->game->tokens->moveToken("hero_2", "hex_11_8");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        // Occupied hex should not be reachable
        $this->assertArrayNotHasKey("hex_11_8", $moves);
    }

    public function testNoNonMapLocationsOffered(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        $this->assertArrayNotHasKey("limbo", $moves);
        $this->assertArrayNotHasKey("supply_crystal_yellow", $moves);
        $this->assertArrayNotHasKey("timetrack_1", $moves);
    }

    // -------------------------------------------------------------------------
    // resolve (delegates to [1,3]moveHero)
    // -------------------------------------------------------------------------

    public function testResolveQueuesMoveHero(): void {
        $op = $this->createOp();
        $op->resolve();
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("[1,3]moveHero", $queued["type"]);
    }

    public function testGetNumberOfMovesDefault3(): void {
        $op = $this->createOp();
        $this->assertEquals(3, $op->getNumberOfMoves());
    }
}
