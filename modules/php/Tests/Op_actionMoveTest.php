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

    public function testAllMapHexesOffered(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        // Should contain hex entries from map_material
        $this->assertArrayHasKey("hex_9_1", $moves);
        $this->assertArrayHasKey("hex_9_8", $moves);
        $this->assertArrayHasKey("hex_9_9", $moves);
    }

    public function testCurrentHexNotApplicable(): void {
        // hero_1 starts at hex_9_9 (Grimheim)
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        $this->assertEquals(Material::ERR_NOT_APPLICABLE, $moves["hex_9_9"]["q"]);
    }

    public function testOtherHexesAreValid(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        $this->assertEquals(Material::RET_OK, $moves["hex_9_1"]["q"]);
        $this->assertEquals(Material::RET_OK, $moves["hex_9_8"]["q"]);
    }

    public function testNoNonMapLocationsOffered(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();

        // These are not map hexes
        $this->assertArrayNotHasKey("limbo", $moves);
        $this->assertArrayNotHasKey("supply_crystal_yellow", $moves);
        $this->assertArrayNotHasKey("timetrack_1", $moves);
    }

    // -------------------------------------------------------------------------
    // resolve
    // -------------------------------------------------------------------------

    public function testResolveMovesHeroToTargetHex(): void {
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_9_1"]);

        $location = $this->game->tokens->db->getTokenLocation("hero_1");
        $this->assertEquals("hex_9_1", $location);
    }

    public function testResolveToCurrentHexThrows(): void {
        // hero_1 is at hex_9_9 — resolving to current hex should fail
        $op = $this->createOp();
        $this->expectException(\Bga\GameFramework\UserException::class);
        $op->action_resolve([Operation::ARG_TARGET => "hex_9_9"]);
    }

    public function testResolveUpdatesTokenLocation(): void {
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_10_1"]);

        $location = $this->game->tokens->db->getTokenLocation("hero_1");
        $this->assertEquals("hex_10_1", $location);
    }
}
