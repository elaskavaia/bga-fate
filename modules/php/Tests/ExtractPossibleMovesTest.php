<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Tests\GameUT;
use PHPUnit\Framework\TestCase;

/**
 * Stub operation that returns configurable getPossibleMoves() results.
 */
class Op_stub extends Operation {
    public array $moves = [];

    function getPossibleMoves() {
        return $this->moves;
    }

    function resolve(): void {}
}

final class ExtractPossibleMovesTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
    }

    private function createOp(array $moves): Op_stub {
        $op = new Op_stub("stub", PCOLOR);
        $op->moves = $moves;
        return $op;
    }

    // -------------------------------------------------------------------------
    // Valid targets
    // -------------------------------------------------------------------------

    public function testSimpleArrayTargets(): void {
        $op = $this->createOp(["hex_1_1", "hex_2_2"]);
        $args = $op->getArgs();

        $this->assertEquals(["hex_1_1", "hex_2_2"], $args["target"]);
        $this->assertEquals(0, $args["q"]);
        $this->assertNull($args["err"] ?? null);
    }

    public function testAssocTargetsWithQZero(): void {
        $op = $this->createOp([
            "hex_1_1" => ["q" => 0],
            "hex_2_2" => ["q" => 0],
        ]);
        $args = $op->getArgs();

        $this->assertEquals(["hex_1_1", "hex_2_2"], $args["target"]);
        $this->assertEquals(0, $args["q"]);
    }

    public function testNumericShorthandQZero(): void {
        $op = $this->createOp([
            "hex_1_1" => 0,
            "hex_2_2" => 0,
        ]);
        $args = $op->getArgs();

        $this->assertEquals(["hex_1_1", "hex_2_2"], $args["target"]);
        $this->assertEquals(0, $args["q"]);
    }

    // -------------------------------------------------------------------------
    // Error targets (q != 0) — no valid targets
    // -------------------------------------------------------------------------

    public function testAllTargetsHaveErrors(): void {
        $op = $this->createOp([
            "hex_1_1" => ["q" => Material::ERR_OCCUPIED],
            "hex_2_2" => ["q" => Material::ERR_COST],
        ]);
        $args = $op->getArgs();

        $this->assertEmpty($args["target"]);
        // First error code should be captured
        $this->assertEquals(Material::ERR_OCCUPIED, $args["q"]);
        $this->assertNotEmpty($args["err"]);
    }

    public function testNumericShorthandErrors(): void {
        $op = $this->createOp([
            "hex_1_1" => Material::ERR_MAX,
        ]);
        $args = $op->getArgs();

        $this->assertEmpty($args["target"]);
        $this->assertEquals(Material::ERR_MAX, $args["q"]);
    }

    public function testMixedValidAndErrorTargets(): void {
        $op = $this->createOp([
            "hex_1_1" => ["q" => 0],
            "hex_2_2" => ["q" => Material::ERR_OCCUPIED],
        ]);
        $args = $op->getArgs();

        // Only valid target listed
        $this->assertEquals(["hex_1_1"], $args["target"]);
        $this->assertEquals(0, $args["q"]);
    }

    // -------------------------------------------------------------------------
    // Top-level error keys
    // -------------------------------------------------------------------------

    public function testTopLevelErrString(): void {
        $op = $this->createOp([
            "err" => "Something went wrong",
        ]);
        $args = $op->getArgs();

        $this->assertEmpty($args["target"]);
        $this->assertEquals("Something went wrong", $args["err"]);
        $this->assertEquals(Material::ERR_PREREQ, $args["q"]);
    }

    public function testTopLevelQCode(): void {
        $op = $this->createOp([
            "q" => Material::ERR_COST,
        ]);
        $args = $op->getArgs();

        $this->assertEmpty($args["target"]);
        $this->assertEquals(Material::ERR_COST, $args["q"]);
        $this->assertNotEmpty($args["err"]);
    }

    public function testTopLevelErrAndQ(): void {
        // When both "err" and "q" are present, "q" should take priority for the code
        $op = $this->createOp([
            "err" => "Custom message",
            "q" => Material::ERR_NO_PLACE,
        ]);
        $args = $op->getArgs();

        $this->assertEmpty($args["target"]);
        $this->assertEquals(Material::ERR_NO_PLACE, $args["q"]);
    }

    // -------------------------------------------------------------------------
    // Empty moves
    // -------------------------------------------------------------------------

    public function testEmptyMovesArray(): void {
        $op = $this->createOp([]);
        $args = $op->getArgs();

        $this->assertEmpty($args["target"]);
        $this->assertEquals(Material::ERR_PREREQ, $args["q"]);
        $this->assertEquals("No valid targets", $args["err"]);
    }

    // -------------------------------------------------------------------------
    // Secondary targets
    // -------------------------------------------------------------------------

    public function testSecondaryTargetsNotInTargetList(): void {
        $op = $this->createOp([
            "hex_1_1" => ["q" => 0],
            "skip" => ["q" => 0, "sec" => true],
        ]);
        $args = $op->getArgs();

        $this->assertEquals(["hex_1_1"], $args["target"]);
        // "skip" should be in info but not in target list
        $this->assertArrayHasKey("skip", $args["info"]);
    }

    // -------------------------------------------------------------------------
    // Per-entry err string
    // -------------------------------------------------------------------------

    public function testPerEntryErrStringWithCode(): void {
        $op = $this->createOp([
            "hex_1_1" => ["q" => Material::ERR_OCCUPIED, "err" => "Hex is blocked"],
        ]);
        $args = $op->getArgs();

        $this->assertEmpty($args["target"]);
        $this->assertEquals(Material::ERR_OCCUPIED, $args["q"]);
        $this->assertEquals("Hex is blocked", $args["err"]);
    }

    // -------------------------------------------------------------------------
    // Prompt
    // -------------------------------------------------------------------------

    public function testTopLevelPrompt(): void {
        $op = $this->createOp([
            "prompt" => "Choose a hex",
            "hex_1_1" => ["q" => 0],
        ]);
        $args = $op->getArgs();

        $this->assertEquals(["hex_1_1"], $args["target"]);
        $this->assertEquals("Choose a hex", $args["prompt"]);
    }

    public function testPromptOnlyIsError(): void {
        $op = $this->createOp([
            "prompt" => "Choose a hex",
        ]);
        $args = $op->getArgs();

        $this->assertEmpty($args["target"]);
        $this->assertEquals(Material::ERR_PREREQ, $args["q"]);
        $this->assertEquals("No valid targets", $args["err"]);
    }

    // -------------------------------------------------------------------------
    // getError / getErrorCode accessors
    // -------------------------------------------------------------------------

    public function testGetErrorCodeAccessor(): void {
        $op = $this->createOp([
            "hex_1_1" => ["q" => Material::ERR_COST],
        ]);

        $this->assertEquals(Material::ERR_COST, $op->getErrorCode());
        $this->assertNotEmpty($op->getError());
    }

    public function testGetErrorCodeZeroWhenValid(): void {
        $op = $this->createOp(["hex_1_1"]);

        $this->assertEquals(0, $op->getErrorCode());
    }
}
