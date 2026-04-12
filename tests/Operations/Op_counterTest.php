<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;

final class Op_counterTest extends AbstractOpTestCase {
    public function testCounterZeroIsVoid(): void {
        $this->createOp("counter(0)");
        $this->assertTrue($this->op->isVoid());
    }

    public function testCounterNonZeroIsNotVoid(): void {
        $this->createOp("counter(2)");
        $this->assertFalse($this->op->isVoid());
    }

    public function testCounterZeroReturnsPrereqError(): void {
        $this->createOp("counter(0)");
        $this->assertNoValidTargetsAndError(Material::ERR_PREREQ);
    }

    public function testResolveSetsCountOnNextOp(): void {
        $this->game->machine->push("counter(3),gainXp", $this->owner);
        $this->game->machine->dispatchOne();

        $nextOp = $this->game->machine->createTopOperationFromDbForOwner();
        $this->assertEquals("counter", $nextOp->getType());
        $nextOp->resolve();

        $nextOp = $this->game->machine->createTopOperationFromDbForOwner();
        $this->assertEquals("gainXp", $nextOp->getType());
        $this->assertEquals(3, $nextOp->getCount());
    }

    public function testResolveWithMaxCapsCount(): void {
        $this->game->machine->push("counter(5,null,3),gainXp", $this->owner);

        $this->game->machine->dispatchOne();
        $this->game->machine->dispatchOne();

        $nextOp = $this->game->machine->createTopOperationFromDbForOwner();
        $this->assertEquals(3, $nextOp->getCount());
    }
}
