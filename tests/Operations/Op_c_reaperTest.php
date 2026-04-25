<?php

declare(strict_types=1);

final class Op_c_reaperTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        // Hero starts at hex_8_9 (Grimheim edge). Most tests reposition or just need
        // marker_attack on a hex; hero placement only matters for damage-routing tests.
    }

    /** Place marker_attack on a hex to simulate "attack in progress". */
    private function setAttackMarker(string $hex): void {
        $this->game->tokens->moveToken("marker_attack", $hex, 0);
    }

    /** Seed a pending resolveHits at top rank — what Op_roll would have queued. */
    private function queuePendingResolveHits(string $primaryHex): void {
        $this->game->machine->queue("resolveHits", $this->owner, [
            "attacker" => "hero_1",
            "target" => $primaryHex,
        ]);
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves
    // -------------------------------------------------------------------------

    public function testNoAttackInProgressReturnsError(): void {
        // marker_attack in limbo — getAttackHex() returns null
        $this->assertNoValidTargets();
    }

    public function testNoAdjacentMonsterReturnsError(): void {
        // Primary hex set, but no monsters around it
        $this->setAttackMarker("hex_7_9");
        $this->createOp(); // re-create after state change
        $this->assertNoValidTargets();
    }

    public function testListsMonsterAdjacentToPrimary(): void {
        // Primary at hex_7_9, secondary candidate at hex_6_9 (adjacent to primary)
        $this->game->tokens->moveToken("monster_goblin_1", "hex_7_9"); // primary defender
        $this->game->tokens->moveToken("monster_goblin_2", "hex_6_9"); // secondary candidate
        $this->setAttackMarker("hex_7_9");
        $this->createOp();

        $this->assertValidTarget("hex_6_9");
    }

    public function testExcludesPrimaryHexFromTargets(): void {
        // The primary hex itself should not be selectable as the secondary
        $this->game->tokens->moveToken("monster_goblin_1", "hex_7_9");
        $this->setAttackMarker("hex_7_9");
        $this->createOp();

        $this->assertNotValidTarget("hex_7_9");
    }

    public function testIgnoresHeroAdjacentToPrimary(): void {
        // Adjacent hex contains a hero, not a monster — should not be a valid target
        $this->game->tokens->moveToken("monster_goblin_1", "hex_7_9"); // primary
        $this->game->tokens->moveToken("hero_1", "hex_6_9"); // hero adjacent to primary
        $this->setAttackMarker("hex_7_9");
        $this->createOp();

        $this->assertNotValidTarget("hex_6_9");
    }

    // -------------------------------------------------------------------------
    // resolve — writes `secondary` onto the pending resolveHits frame
    // -------------------------------------------------------------------------

    public function testResolveSetsSecondaryOnPendingResolveHits(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_7_9");
        $this->game->tokens->moveToken("monster_goblin_2", "hex_6_9");
        $this->setAttackMarker("hex_7_9");
        $this->queuePendingResolveHits("hex_7_9");
        $this->createOp();

        $this->call_resolve("hex_6_9");

        // Pending resolveHits should now carry secondary=hex_6_9
        $row = $this->game->machine->findOperation($this->owner, "resolveHits");
        $this->assertNotNull($row, "resolveHits should still be pending after c_reaper resolve");

        $op = $this->game->machine->instantiateOperationFromDbRow($row);
        $this->assertEquals("hex_6_9", $op->getDataField("secondary"));
        $this->assertEquals("hex_7_9", $op->getDataField("target"), "primary target preserved");
    }

    public function testResolveAssertsWhenNoPendingResolveHits(): void {
        // Set up valid getPossibleMoves but skip queuing resolveHits — resolve should systemAssert
        $this->game->tokens->moveToken("monster_goblin_1", "hex_7_9");
        $this->game->tokens->moveToken("monster_goblin_2", "hex_6_9");
        $this->setAttackMarker("hex_7_9");
        $this->createOp();

        $this->expectExceptionMessage("ERR:c_reaper:noPendingResolveHits");
        $this->call_resolve("hex_6_9");
    }
}
