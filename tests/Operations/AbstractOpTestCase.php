<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;
abstract class AbstractOpTestCase extends TestCase {
    protected GameUT $game;
    protected string $owner;
    protected Operation $op;

    function createOp(?string $type = null, mixed $data = null): Operation {
        // Derive op type from the test class name: "Op_c_preyTest" → "c_prey"
        if ($type == null) {
            $className = (new \ReflectionClass($this))->getShortName();
            $type = preg_replace(["/^Op_/", '/Test$/'], "", $className);
        }
        return $this->game->machine->instanciateOperation($type, $this->owner, $data);
    }

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
        $this->game->clearHand();
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());
        $this->op = $this->createOp();
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

    function call_resolve(mixed $target = []) {
        return $this->op->action_resolve([Operation::ARG_TARGET => $target]);
    }
}
