<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Operations\Op_actionPrepare;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_actionPrepareTest extends AbstractOpTestCase {
    private function getHandCards(): array {
        return $this->game->tokens->getTokensOfTypeInLocation("card", "hand_" . PCOLOR);
    }

    public function testResolveQueuesDrawEvent(): void {
        $op = $this->op;
        $op->resolve();
        $handBefore = count($this->getHandCards());
        // drawEvent now requires player confirmation — resolve it
        $drawOp = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertEquals("drawEvent", $drawOp->getType());
        $drawOp->action_resolve([Operation::ARG_TARGET => "confirm"]);
        $this->assertCount($handBefore + 1, $this->getHandCards());
    }

    public function testResolveWithEmptyDeckNoError(): void {
        $handBefore = count($this->getHandCards());
        $op = $this->op;
        $op->resolve();
        $this->game->machine->dispatchAll();
        $this->assertCount($handBefore, $this->getHandCards());
    }
}
