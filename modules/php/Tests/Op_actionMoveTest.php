<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_actionMove;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Tests\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_actionMoveTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createTokens();
        // Assign hero 1 to PCOLOR player
        $this->game->tokens->db->moveToken("card_hero_1", "tableau_" . PCOLOR);
        $this->game->tokens->db->moveToken("hero_1", "hex_9_9");
    }

    private function createOp(): Op_actionMove {
        /** @var Op_actionMove */
        $op = $this->game->machine->instanciateOperation("actionMove", PCOLOR);
        return $op;
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves
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
        $this->game->tokens->db->moveToken("hero_2", "hex_11_8");
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
    // resolve
    // -------------------------------------------------------------------------

    public function testResolveMovesHeroToTargetHex(): void {
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_11_8"]);

        $location = $this->game->tokens->db->getTokenLocation("hero_1");
        $this->assertEquals("hex_11_8", $location);
    }

    public function testResolveToCurrentHexThrows(): void {
        $op = $this->createOp();
        $this->expectException(\Bga\GameFramework\UserException::class);
        $op->action_resolve([Operation::ARG_TARGET => "hex_9_9"]);
    }

    public function testResolveToUnreachableHexThrows(): void {
        $op = $this->createOp();
        $this->expectException(\Bga\GameFramework\UserException::class);
        $op->action_resolve([Operation::ARG_TARGET => "hex_9_1"]);
    }
}
