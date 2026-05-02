<?php

declare(strict_types=1);

/**
 * Tests for Op_turnStart — fires trigger(turnStart) then queues the main turn op.
 */
class Op_turnStartTest extends AbstractOpTestCase {
    public function testResolveQueuesTriggerAndTurn(): void {
        $this->createOp("turnStart");
        $this->call_resolve();
        $opTypes = array_map(fn($o) => $o["type"], $this->game->machine->getAllOperations($this->owner));
        $this->assertContains("trigger(TTurnStart)", $opTypes, "Should queue trigger(turnStart)");
        $this->assertContains("turn", $opTypes, "Should queue turn");
    }

    public function testTriggerFiresBeforeTurn(): void {
        $this->createOp("turnStart");
        $this->call_resolve();
        $ops = $this->game->machine->getAllOperations($this->owner);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $triggerIdx = array_search("trigger(TTurnStart)", $opTypes);
        $turnIdx = array_search("turn", $opTypes);
        $this->assertLessThan($turnIdx, $triggerIdx, "trigger(turnStart) should be queued before turn");
    }
}
