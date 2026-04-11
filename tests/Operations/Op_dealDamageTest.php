<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;

final class Op_dealDamageTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function getDamage(string $monsterId): int {
        return $this->countRedCrystals($monsterId);
    }

    // -------------------------------------------------------------------------
    // Testing possible moves
    // -------------------------------------------------------------------------

    public function testNoMonstersAdjacentReturnsEmpty(): void {
        $this->assertNoValidTargets();
    }

    public function testAdjacentMonsterIsTarget(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->assertValidTarget("hex_12_8");
    }

    public function testNonAdjacentMonsterNotTarget(): void {
        // hex_13_7 is 2 hexes away from hex_11_8 — out of range 1
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $op = $this->op;
        $this->assertNoValidTargets();
    }

    public function testMultipleAdjacentMonsters(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_11_7");
        $this->assertValidTargetCount(2);
    }

    public function testInRangeUsesHeroAttackRange(): void {
        // Bjorn has First Bow (attack_range=2), hex_13_7 is 2 hexes away
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $this->createOp("dealDamage(inRange)");
        $this->assertValidTarget("hex_13_7");
    }

    public function testInRange3ReachesDistance3(): void {
        // hex_14_6 is 3 hexes from hex_11_8
        $this->game->tokens->moveToken("monster_goblin_1", "hex_14_6");
        $this->createOp("dealDamage(inRange3)");
        $this->assertValidTarget("hex_14_6");
    }

    public function testAdjDoesNotReachDistance2(): void {
        // hex_13_7 is 2 hexes away — adj should not reach it
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $this->createOp("dealDamage(adj)");
        $this->assertNoValidTargets();
    }

    // -------------------------------------------------------------------------
    // matchesFilter
    // -------------------------------------------------------------------------

    public function testFilterTrueMatchesAll(): void {
        // Default filter is "true" — should match any monster
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->assertValidTarget("hex_12_8");
    }

    public function testFilterNotLegendExcludesLegend(): void {
        $this->game->tokens->moveToken("monster_legend_1_1", "hex_12_8");
        $this->createOp("dealDamage(adj,'not_legend')");
        $this->assertNoValidTargets();
    }

    public function testFilterNotLegendIncludesNonLegend(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->createOp("dealDamage(adj,'not_legend')");
        $this->assertValidTarget("hex_12_8");
    }

    public function testFilterRank3OrLegendMatchesRank3(): void {
        // Troll is rank 3
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $this->createOp("dealDamage(adj,'rank==3 or legend')");
        $this->assertValidTarget("hex_12_8");
    }

    public function testFilterRank3OrLegendMatchesLegend(): void {
        $this->game->tokens->moveToken("monster_legend_1_1", "hex_12_8");
        $this->createOp("dealDamage(adj,'rank==3 or legend')");
        $this->assertValidTarget("hex_12_8");
    }

    public function testFilterRank3OrLegendExcludesRank1(): void {
        // Goblin is rank 1
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->createOp("dealDamage(adj,'rank==3 or legend')");
        $this->assertNoValidTargets();
    }

    public function testAdjacentHeroNotTarget(): void {
        $this->game->tokens->moveToken("hero_2", "hex_12_8");
        $op = $this->op;
        $moves = $op->getArgsInfo();
        $this->assertArrayNotHasKey("hex_12_8", $moves);
    }

    // -------------------------------------------------------------------------
    // resolve
    // -------------------------------------------------------------------------

    public function testDeal1DamageToMonster(): void {
        // Goblin health=2 — 1 damage shouldn't kill it
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->op;
        $this->call_resolve("hex_12_8");
        $this->assertEquals(1, $this->getDamage("monster_goblin_1"));
        $this->assertEquals("hex_12_8", $this->game->tokens->getTokenLocation("monster_goblin_1"));
    }

    public function testDeal2DamageKillsGoblin(): void {
        // Goblin health=2 — 2 damage kills it
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->createOp("2dealDamage");
        $this->call_resolve("hex_12_8");
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));
        // Red crystals should be cleaned up after kill
        $this->assertEquals(0, $this->getDamage("monster_goblin_1"));
    }

    public function testKillGrantsXp(): void {
        // Goblin xp=1 — killing it should add 1 yellow crystal to tableau
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $xpBefore = $this->countYellowCrystals($this->getPlayersTableau());
        $this->createOp("2dealDamage");
        $this->call_resolve("hex_12_8");
        $xpAfter = $this->countYellowCrystals($this->getPlayersTableau());
        $this->assertEquals($xpBefore + 1, $xpAfter);
    }

    public function testNoXpIfNotKilled(): void {
        // Troll health=7 — 1 damage doesn't kill
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $xpBefore = $this->countYellowCrystals($this->getPlayersTableau());
        $op = $this->op;
        $this->call_resolve("hex_12_8");
        $xpAfter = $this->countYellowCrystals($this->getPlayersTableau());
        $this->assertEquals($xpBefore, $xpAfter);
    }

    public function testDamageStacksAcrossMultipleCalls(): void {
        // Brute health=3 — 1+1=2, should survive
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $op = $this->op;
        $this->call_resolve("hex_12_8");
        $op2 = $this->createOp();
        $op2->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $this->assertEquals(2, $this->getDamage("monster_brute_1"));
        $this->assertEquals("hex_12_8", $this->game->tokens->getTokenLocation("monster_brute_1"));
    }

    public function testDamageStacksAndKills(): void {
        // Brute health=3 — 1+2=3, should die
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $op = $this->op;
        $this->call_resolve("hex_12_8");
        $op2 = $this->createOp("2dealDamage");
        $op2->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_brute_1"));
    }

    public function testPresetTargetReturnsOnlyThatTarget(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_11_7");
        $this->createOp("2dealDamage", ["target" => "hex_12_8"]);
        $this->assertValidTargetCount(1);
        $this->assertValidTarget("hex_12_8");
    }

    public function testNoDiceRolled(): void {
        // dealDamage is direct damage — no dice should appear on display_battle
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->op;
        $this->call_resolve("hex_12_8");
        $diceOnDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        $this->assertCount(0, $diceOnDisplay);
    }

    public function testCrystalsTakenFromSupply(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $supplyBefore = $this->countRedCrystals("supply_crystal_red");
        $this->createOp("2dealDamage");
        $this->call_resolve("hex_12_8");
        $supplyAfter = $this->countRedCrystals("supply_crystal_red");
        $this->assertEquals($supplyBefore - 2, $supplyAfter);
    }

    // -------------------------------------------------------------------------
    // Param: adj_attack — candidates adjacent to current attack target hex
    // Used by Bone Bane Bow and Fireball II.
    // -------------------------------------------------------------------------

    public function testAdjAttackFindsNeighborMonster(): void {
        // Hero hex_11_8, attack target hex_12_8, monster on hex_13_8 (neighbor of attack hex)
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_8");
        $this->createOp("dealDamage(adj_attack)");
        $this->assertValidTarget("hex_13_8");
    }

    public function testAdjAttackExcludesAttackHexItself(): void {
        // A monster ON the attack hex should NOT be a candidate (we want "another" monster)
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->createOp("dealDamage(adj_attack)");
        $this->assertNotValidTarget("hex_12_8");
    }

    public function testAdjAttackReturnsEmptyWithoutMarker(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_8");
        $this->createOp("dealDamage(adj_attack)");
        $this->assertNoValidTargets();
    }

    public function testAdjAttackOnlyMonsters(): void {
        // A hero adjacent to the attack hex is NOT a candidate
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        $this->game->tokens->moveToken("hero_2", "hex_13_8");
        $this->createOp("dealDamage(adj_attack)");
        $this->assertNoValidTargets();
    }

    public function testAdjAttackHonoursFilter(): void {
        // Troll (rank 3) adjacent — filter requires rank<=2 → rejected
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        $this->game->tokens->moveToken("monster_troll_1", "hex_13_8");
        $this->createOp("dealDamage(adj_attack,'rank<=2')");
        $this->assertNoValidTargets();
    }

    public function testAdjAttackMultipleNeighbors(): void {
        // Two monsters adjacent to the attack hex — both are candidates
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_9");
        $this->createOp("dealDamage(adj_attack)");
        $this->assertValidTarget("hex_13_8");
        $this->assertValidTarget("hex_12_9");
    }

    public function testAdjAttackResolveDealsDamage(): void {
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_8");
        $this->createOp("dealDamage(adj_attack)");
        $this->call_resolve("hex_13_8");
        $this->assertEquals(1, $this->getDamage("monster_goblin_1"));
    }
}
