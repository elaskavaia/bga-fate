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

    // -------------------------------------------------------------------------
    // Attacker-adjacency gate: preventDamage(adj) (Riposte) only vs an adjacent
    // attacker; the unparametrized op (Dodge/Stoneskin/Dreadnought) is not gated.
    // BGA #233845
    // -------------------------------------------------------------------------

    public function testRiposteAdjOfferedWhenAttackerAdjacent(): void {
        $this->queueDealDamage(3); // goblin at hex_12_8 is adjacent to hero at hex_11_8
        $op = $this->createOp("2preventDamage(adj)");
        $this->assertFalse($op->noValidTargets(), "Riposte applies against an adjacent attacker");
    }

    public function testRiposteAdjNotOfferedWhenAttackerNotAdjacent(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_8"); // distance 2, ranged
        $this->queueDealDamage(3);
        $this->createOp("2preventDamage(adj)");
        $this->assertNoValidTargets("Riposte must not apply against a non-adjacent attacker");
    }

    public function testPreventWithoutAdjParamAppliesRegardlessOfDistance(): void {
        // Dodge/Stoneskin/Dreadnought carry no (adj) param and prevent damage at any range.
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_8"); // distance 2
        $this->queueDealDamage(3);
        $op = $this->createOp("2preventDamage");
        $this->assertFalse($op->noValidTargets(), "plain preventDamage is not adjacency-gated");
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
        $args = $op->getExtraArgs();
        $this->assertEquals(5, $args["max"]);
        // Parent's ${count} must survive so the prompt template resolves (BGA #233796).
        $this->assertArrayHasKey("count", $args);
    }

    public function testGetPromptIncludesCountAndMaxPlaceholders(): void {
        $this->queueDealDamage(3);
        $op = $this->createOp("1preventDamage");
        $prompt = $op->getPrompt();
        $this->assertStringContainsString('${count}', $prompt);
        $this->assertStringContainsString('${max}', $prompt);
    }

    // -------------------------------------------------------------------------
    // Armor resolves before prevention (FORUM.md:6519) — a prevention card is
    // only offered on, and only spends itself against, what armor left over.
    // -------------------------------------------------------------------------

    private function initBoldurUnderAttack(int $count): void {
        $this->init(4); // Boldur, armor=1
        $this->game->tokens->moveToken("hero_4", "hex_11_8");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        // Monster-turn ops are automa-owned; using the player color here would make
        // retargetCounterAttackDamage mistake this incoming hit for a counter-attack.
        $this->game->machine->push("dealDamage", $this->game->getAutomaColor(), [
            "target" => "hex_11_8",
            "attacker" => "monster_goblin_1",
            "count" => $count,
        ]);
    }

    public function testNotOfferedWhenArmorAbsorbsEverything(): void {
        $this->initBoldurUnderAttack(1);
        $this->createOp("1preventDamage");
        $this->assertNoValidTargets("armor already ate the only hit - nothing left to prevent");
    }

    public function testMaxExcludesTheDamageArmorAbsorbs(): void {
        $this->initBoldurUnderAttack(3);
        $op = $this->createOp("1preventDamage");
        $this->assertEquals(2, $op->getCurrentDamage());
    }

    public function testPreventionStacksWithArmorWithoutDoubleCounting(): void {
        $this->initBoldurUnderAttack(3);
        $this->createOp("1preventDamage")->resolve();
        $pending = $this->game->machine->findOperation(null, "dealDamage");
        $this->assertEquals(2, (int) $pending->getCount(), "prevention comes off the raw count");
        $this->assertEquals(1, $pending->getEffectiveDamage(), "3 damage - 1 prevented - 1 armor, armor counted once");
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
