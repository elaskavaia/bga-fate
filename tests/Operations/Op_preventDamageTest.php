<?php

declare(strict_types=1);

final class Op_preventDamageTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
    }

    private function queueDealDamage(int $count = 3): void {
        $this->game->machine->push("dealDamage", PCOLOR, [
            "target" => "hex_11_8",
            "attacker" => "monster_goblin_1",
            "count" => $count,
        ]);
    }

    private function getDealDamageCount(): ?int {
        $ops = $this->game->machine->db->getOperations(null, "dealDamage");
        if (empty($ops)) {
            return null;
        }
        $row = reset($ops);
        $op = $this->game->machine->instantiateOperationFromDbRow($row);
        return (int) $op->getCount();
    }

    // -------------------------------------------------------------------------
    // resolve — reduces dealDamage count
    // -------------------------------------------------------------------------

    public function testPrevent1ReducesDealDamageBy1(): void {
        $this->queueDealDamage(3);
        $op = $this->createOp("1preventDamage");
        $op->resolve();
        $this->assertEquals(2, $this->getDealDamageCount());
    }

    public function testPrevent2ReducesDealDamageBy2(): void {
        $this->queueDealDamage(3);
        $op = $this->createOp("2preventDamage");
        $op->resolve();
        $this->assertEquals(1, $this->getDealDamageCount());
    }

    public function testPreventAllDamageHidesDealDamage(): void {
        $this->queueDealDamage(2);
        $op = $this->createOp("2preventDamage");
        $op->resolve();
        // dealDamage should be hidden (removed from active queue)
        $this->assertNull($this->getDealDamageCount());
    }

    public function testPreventMoreThanDamageClamps(): void {
        $this->queueDealDamage(1);
        $op = $this->createOp("3preventDamage");
        $op->resolve();
        // Only 1 damage existed, all prevented
        $this->assertNull($this->getDealDamageCount());
    }

    public function testNoDealDamageOnStackReturnsError(): void {
        // No dealDamage queued — getPossibleMoves returns error
        $this->createOp("1preventDamage");
        $this->assertNoValidTargets();
    }

    public function testPreventDoesNotAffectOtherOperations(): void {
        $this->game->machine->push("roll", PCOLOR, ["count" => 3]);
        $this->queueDealDamage(3);
        $op = $this->createOp("1preventDamage");
        $op->resolve();
        // dealDamage reduced, roll untouched
        $this->assertEquals(2, $this->getDealDamageCount());
        $ops = $this->game->machine->db->getOperations(PCOLOR, "roll");
        $this->assertNotEmpty($ops);
    }

    // -------------------------------------------------------------------------
    // Prompt path — getPrompt / getExtraArgs / getCurrentDamage
    // -------------------------------------------------------------------------

    public function testGetCurrentDamageReadsLiveDealDamageCount(): void {
        $this->queueDealDamage(4);
        $op = $this->createOp("1preventDamage");
        $this->assertEquals(4, $op->getCurrentDamage());
    }

    public function testGetCurrentDamageZeroWhenNoDealDamageOnStack(): void {
        $op = $this->createOp("1preventDamage");
        $this->assertEquals(0, $op->getCurrentDamage());
    }

    public function testGetExtraArgsExposesMaxToClient(): void {
        $this->queueDealDamage(5);
        $op = $this->createOp("2preventDamage");
        $this->assertEquals(["max" => 5], $op->getExtraArgs());
    }

    public function testGetPromptIncludesCountAndMaxPlaceholders(): void {
        $this->queueDealDamage(3);
        $op = $this->createOp("1preventDamage");
        $prompt = $op->getPrompt();
        $this->assertStringContainsString('${count}', $prompt);
        $this->assertStringContainsString('${max}', $prompt);
    }

    public function testGetCurrentDamageReflectsPostPreventCount(): void {
        $this->queueDealDamage(5);
        $op = $this->createOp("2preventDamage");
        $op->resolve();
        // After preventing 2 of 5, a fresh preventDamage op should see 3 remaining.
        $next = $this->createOp("1preventDamage");
        $this->assertEquals(3, $next->getCurrentDamage());
    }
}
