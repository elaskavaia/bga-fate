<?php

declare(strict_types=1);

final class Op_performActionTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->clearMachine();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function getQueuedOp(): ?array {
        $ops = $this->game->machine->getTopOperations($this->owner);
        return $ops ? reset($ops) : null;
    }

    public function testQueuesActionAttack(): void {
        $this->createOp("performAction(actionAttack)");
        $this->call_resolve();
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("actionAttack", $queued["type"]);
    }

    public function testQueuesActionMend(): void {
        $this->createOp("performAction(actionMend)");
        $this->call_resolve();
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("actionMend", $queued["type"]);
    }

    public function testQueuesActionPractice(): void {
        $this->createOp("performAction(actionPractice)");
        $this->call_resolve();
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("actionPractice", $queued["type"]);
    }
}
