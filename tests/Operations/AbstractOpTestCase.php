<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

abstract class AbstractOpTestCase extends TestCase {
    protected GameUT $game;
    protected string $owner;
    protected Operation $op;

    /**
     * Instantiate op and cache $this->op;
     */
    function createOp(?string $type = null, mixed $data = null): Operation {
        // Derive op type from the test class name: "Op_c_preyTest" → "c_prey"
        if ($type == null) {
            $className = (new \ReflectionClass($this))->getShortName();
            $type = preg_replace(["/^Op_/", '/Test$/'], "", $className);
        }
        $this->op = $this->game->machine->instanciateOperation($type, $this->owner, $data);
        return $this->op;
    }

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
        $this->game->clearHand();
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());
        $this->createOp();
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves tested via noValidTargets(), getArgsInfo() and getArgsTarget()
    // -------------------------------------------------------------------------

    /**
     * Assert that $target appears among the current op's possible moves.
     */
    protected function assertValidTarget(string $target, string $message = ""): void {
        $op = $this->op;
        $candidates = $op->getArgsTarget();
        $this->assertContains($target, $candidates, $message ?: "$target should be a valid target");
    }

    /** Assert that $target is NOT a valid target of the current op. */
    protected function assertNotValidTarget(string $target, string $message = ""): void {
        $op = $this->op;
        $candidates = $op->getArgsTarget();
        $this->assertNotContains($target, $candidates, $message ?: "$target should not be a valid target");
    }

    /** Assert that the op has no valid targets at all (will auto-skip). */
    protected function assertNoValidTargets(string $message = ""): void {
        $op = $this->op;
        $this->assertTrue($op->noValidTargets(), $message ?: "op should have no valid targets");
    }

    /** Assert that the op has exactly $expected valid targets. */
    protected function assertValidTargetCount(int $expected, string $message = ""): void {
        $count = count($this->op->getArgsTarget());
        $this->assertEquals($expected, $count, $message ?: "op should have exactly $expected valid targets");
    }

    /** Fetch the info record for $target from getArgsInfo, asserting it exists. Record may be valid or carry an error code. */
    protected function getTargetInfo(string $target): array {
        $info = $this->op->getArgsInfo();
        $this->assertArrayHasKey($target, $info, "$target should be in possible moves");
        return $info[$target];
    }

    /** Assert that the op has no valid targets AND reports the given error code. */
    protected function assertNoValidTargetsAndError(int $expectedErrorCode, string $message = ""): void {
        $op = $this->op;
        $this->assertTrue($op->noValidTargets(), $message ?: "op should have no valid targets");
        $this->assertEquals($expectedErrorCode, $op->getErrorCode(), $message ?: "op should have error code $expectedErrorCode");
    }

    /** Assert that $target is listed in getArgsInfo but has the given error code. */
    protected function assertTargetError(string $target, int $expectedErrorCode, string $message = ""): void {
        $info = $this->op->getArgsInfo();
        $this->assertArrayHasKey($target, $info, $message ?: "$target should be listed in args info");
        $this->assertEquals($expectedErrorCode, (int) $info[$target]["q"], $message ?: "$target should have error code $expectedErrorCode");
    }
    // -------------------------------------------------------------------------
    //  Helper methods
    // -------------------------------------------------------------------------

    function call_resolve(mixed $target = []) {
        return $this->op->action_resolve([Operation::ARG_TARGET => $target]);
    }

    protected function getPlayersTableau(): string {
        return "tableau_" . $this->owner;
    }

    protected function countYellowCrystals(string $loc): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", $loc));
    }

    protected function countRedCrystals(string $loc): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $loc));
    }

    protected function countGreenCrystals(string $loc): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", $loc));
    }
}
