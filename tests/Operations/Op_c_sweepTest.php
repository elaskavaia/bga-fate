<?php

declare(strict_types=1);

/**
 * Op_c_sweep — Sweeping Strike cleave.
 *
 * Boldur sits in the centre of a 6-hex "clock". After killing an adjacent monster,
 * overkill damage carries to the next monster encountered walking clockwise around
 * the hero, starting just past the killed hex.
 *
 * Test geometry (Boldur at hex_5_9):
 *
 *   CW ring order: NW hex_4_9 → NE hex_5_8 → E hex_6_8 → SE hex_6_9
 *                  → SW hex_5_10 → W hex_4_10
 */
final class Op_c_sweepTest extends AbstractOpTestCase {
    private string $heroHex = "hex_5_9";

    protected function setUp(): void {
        parent::setUp();
        // Default hero from AbstractOpTestCase is hero_1; that's fine — Op_c_sweep
        // is hero-agnostic, it uses whoever owns the op.
        $this->game->tokens->moveToken("hero_1", $this->heroHex);
    }

    private function setAttackMarker(string $hex, int $overkill): void {
        $this->game->tokens->moveToken("marker_attack", $hex, $overkill);
    }

    public function testNoAttackHexBails(): void {
        // marker_attack in limbo → no current attack
        $this->createOp("c_sweep");
        $this->assertNoValidTargets();
    }

    public function testNoOverkillBails(): void {
        $this->setAttackMarker("hex_4_9", 0);
        $this->createOp("c_sweep");
        $this->assertNoValidTargets();
    }

    public function testKilledHexNotAdjacentBails(): void {
        // Killed hex is 2 away from hero — Sweeping Strike only fires for *adjacent* kills.
        $this->setAttackMarker("hex_3_9", 2);
        $this->game->tokens->moveToken("monster_goblin_1", "hex_4_9");
        $this->createOp("c_sweep");
        $this->assertNoValidTargets();
    }

    public function testNoMonsterOnRingBails(): void {
        // Killed hex is adjacent, overkill=2, but ring is empty.
        $this->setAttackMarker("hex_4_9", 2);
        $this->createOp("c_sweep");
        $this->assertNoValidTargets();
    }

    public function testFindsNextMonsterClockwise(): void {
        // Killed at NW. Next CW = NE (hex_5_8). Place a goblin there.
        $this->game->tokens->moveToken("monster_goblin_1", "hex_5_8");
        $this->setAttackMarker("hex_4_9", 1);
        $this->createOp("c_sweep");
        $this->assertValidTarget("hex_5_8");
        $this->assertValidTargetCount(1);
    }

    public function testSkipsEmptyHexesUntilLiveMonster(): void {
        // Killed at NW (hex_4_9). Next CW = NE (hex_5_8) [empty], E (hex_6_8) [empty],
        // SE (hex_6_9) [goblin] → cleave should land on hex_6_9.
        $this->game->tokens->moveToken("monster_goblin_1", "hex_6_9");
        $this->setAttackMarker("hex_4_9", 1);
        $this->createOp("c_sweep");
        $this->assertValidTarget("hex_6_9");
        $this->assertValidTargetCount(1);
    }

    public function testPicksFirstMonsterNotFurther(): void {
        // Two monsters on the ring; the closer-clockwise one wins.
        // Killed at NW. CW: NE [goblin_1] (winner), E [empty], SE [goblin_2] (ignored).
        $this->game->tokens->moveToken("monster_goblin_1", "hex_5_8");
        $this->game->tokens->moveToken("monster_goblin_2", "hex_6_9");
        $this->setAttackMarker("hex_4_9", 1);
        $this->createOp("c_sweep");
        $this->assertValidTarget("hex_5_8");
        $this->assertNotValidTarget("hex_6_9");
    }

    public function testWalksAcrossWrapAroundRing(): void {
        // Killed at NW. Walk CW to W (hex_4_10) — the last position in CW order
        // before wrapping back to the killed hex itself.
        $this->game->tokens->moveToken("monster_goblin_1", "hex_4_10");
        $this->setAttackMarker("hex_4_9", 1);
        $this->createOp("c_sweep");
        $this->assertValidTarget("hex_4_10");
    }

    public function testResolveDealsOverkillDamage(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_5_8");
        $this->setAttackMarker("hex_4_9", 1);
        $this->createOp("c_sweep");

        $this->call_resolve("hex_5_8");

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_goblin_1");
        $this->assertCount(1, $crystals, "Goblin should take 1 overkill damage");
    }

    public function testResolveCanKillCleaveTarget(): void {
        // Goblin health=2; pre-place 1 damage so overkill=2 finishes it.
        $this->game->tokens->moveToken("monster_goblin_1", "hex_5_8");
        $this->game->effect_moveCrystals("hero_1", "red", 1, "monster_goblin_1", ["message" => ""]);
        $this->setAttackMarker("hex_4_9", 2);
        $this->createOp("c_sweep");

        $this->call_resolve("hex_5_8");

        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));
    }

    public function testNoChainAfterCleaveKill(): void {
        // 2-enemy cap is structural: even if cleave kills the second monster with
        // leftover damage and another monster sits clockwise from it, no further
        // cleave is queued.
        $this->game->tokens->moveToken("monster_goblin_1", "hex_5_8"); // cleave target
        $this->game->tokens->moveToken("monster_goblin_2", "hex_6_8"); // sits CW from #1
        $this->game->effect_moveCrystals("hero_1", "red", 1, "monster_goblin_1", ["message" => ""]);
        $this->setAttackMarker("hex_4_9", 5); // 5 overkill — kills #1 with 4 to spare

        $this->createOp("c_sweep");
        $this->call_resolve("hex_5_8");

        // #1 dead, #2 unharmed
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));
        $this->assertCount(0, $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_goblin_2"));

        // No follow-up c_sweep on the stack
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertNotContains("c_sweep", $opTypes);
    }
}
