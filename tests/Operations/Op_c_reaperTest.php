<?php

declare(strict_types=1);

final class Op_c_reaperTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        // Adjacent to both hex_7_9 and hex_6_9, the primary/secondary pair used below
        $this->game->tokens->moveToken("hero_1", "hex_6_10");
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

    public function testListsMonsterAdjacentToHero(): void {
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

    public function testIgnoresOtherHeroAdjacentToHero(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_7_9"); // primary
        $this->game->tokens->moveToken("hero_2", "hex_6_9");
        $this->setAttackMarker("hex_7_9");
        $this->createOp();

        $this->assertEquals("hero_2", $this->game->hexMap->getCharacterOnHex("hex_6_9"));
        $this->assertNotValidTarget("hex_6_9");
    }

    /** BGA #241160: designer ruling, "both targets need to be adjacent to Embla" (FORUM.md). */
    public function testSecondaryAdjacentToPrimaryButNotToHeroIsNotOffered(): void {
        $this->game->tokens->moveToken("hero_1", "hex_7_9");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_7_8"); // primary
        $this->game->tokens->moveToken("monster_goblin_2", "hex_6_8"); // adjacent to primary only
        $this->game->tokens->moveToken("monster_goblin_3", "hex_7_10"); // adjacent to hero only
        $this->setAttackMarker("hex_7_8");
        $this->createOp();

        $this->assertNotValidTarget("hex_6_8");
        $this->assertValidTarget("hex_7_10");
    }

    /** BGA #241160: primary out of the hero's adjacency (e.g. a ranged attack) - no swing. */
    public function testNotApplicableWhenPrimaryNotAdjacentToHero(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_8_8"); // primary, 2 away from hex_6_10
        $this->game->tokens->moveToken("monster_goblin_2", "hex_7_9"); // adjacent to both
        $this->setAttackMarker("hex_8_8");
        $this->createOp();

        $this->assertNoValidTargets();
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
        $op = $this->game->machine->findOperation($this->owner, "resolveHits");
        $this->assertNotNull($op, "resolveHits should still be pending after c_reaper resolve");
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
        ob_start();
        try {
            $this->call_resolve("hex_6_9");
        } finally {
            ob_end_clean();
        }
    }
}
