<?php

declare(strict_types=1);

final class Op_killMonsterTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->clearMachine(); // drop leftover reinforcement/turnStart so dispatchAll() only runs the queued applyDamage
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function getDamage(string $monsterId): int {
        return $this->countRedCrystals($monsterId);
    }

    // -------------------------------------------------------------------------
    // Testing possible moves — same as dealDamage, range + filter
    // -------------------------------------------------------------------------

    public function testAdjacentMonsterIsTarget(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->assertValidTarget("hex_12_8");
    }

    public function testNoMonstersReturnsEmpty(): void {
        $op = $this->op;
        $this->assertNoValidTargets();
    }

    public function testCanSkip(): void {
        $op = $this->op;
        $this->assertTrue($op->canSkip());
    }

    public function testInRangeFilter(): void {
        // Bjorn has attack_range=2; hex_13_7 is 2 hexes from hex_11_8
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $this->createOp("killMonster(inRange,'rank<=2')");
        $this->assertValidTarget("hex_13_7");
    }

    public function testRankFilterExcludesHighRank(): void {
        // Troll is rank 3 — should be excluded by rank<=2
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $this->createOp("killMonster(adj,'rank<=2')");
        $this->assertNoValidTargets();
    }

    public function testRankFilterIncludesLowRank(): void {
        // Goblin is rank 1 — should be included by rank<=2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->createOp("killMonster(adj,'rank<=2')");
        $this->assertValidTarget("hex_12_8");
    }

    // -------------------------------------------------------------------------
    // healthRem filter (Short Temper)
    // -------------------------------------------------------------------------

    public function testHealthRemFilterFullHealthExcluded(): void {
        // Brute health=3, no damage → healthRem=3, filter healthRem<=2 excludes it
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $this->createOp("killMonster(adj,'healthRem<=2')");
        $this->assertNoValidTargets();
    }

    public function testHealthRemFilterDamagedIncluded(): void {
        // Brute health=3, 1 damage → healthRem=2, filter healthRem<=2 includes it
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $this->game->tokens->moveToken("crystal_red_1", "monster_brute_1");
        $this->createOp("killMonster(adj,'healthRem<=2')");
        $this->assertValidTarget("hex_12_8");
    }

    public function testHealthRemGoblinFullHealthIncluded(): void {
        // Goblin health=2 → healthRem=2, filter healthRem<=2 includes it
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->createOp("killMonster(adj,'healthRem<=2')");
        $this->assertValidTarget("hex_12_8");
    }

    // -------------------------------------------------------------------------
    // resolve — kills monster, awards XP
    // -------------------------------------------------------------------------

    public function testKillsGoblin(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->op;
        $this->call_resolve("hex_12_8");
        $this->dispatchAll();
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));
        $this->assertEquals(0, $this->getDamage("monster_goblin_1"));
    }

    public function testKillsBrute(): void {
        // Brute health=3 — killMonster should deal 3 damage, killing it
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $op = $this->op;
        $this->call_resolve("hex_12_8");
        $this->dispatchAll();
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_brute_1"));
    }

    public function testKillGrantsXp(): void {
        // Goblin xp=1
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $xpBefore = $this->countYellowCrystals($this->getPlayersTableau());
        $this->call_resolve("hex_12_8");
        $this->dispatchAll();
        $xpAfter = $this->countYellowCrystals($this->getPlayersTableau());
        $this->assertEquals($xpBefore + 1, $xpAfter);
    }

    public function testKillBruteGrantsMoreXp(): void {
        // Brute xp=2
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $xpBefore = $this->countYellowCrystals($this->getPlayersTableau());
        $this->call_resolve("hex_12_8");
        $this->dispatchAll();
        $xpAfter = $this->countYellowCrystals($this->getPlayersTableau());
        $this->assertEquals($xpBefore + 2, $xpAfter);
    }

    public function testKillAlreadyDamagedMonster(): void {
        // Brute health=3, already has 1 damage — kill should still work
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $this->game->tokens->moveToken("crystal_red_1", "monster_brute_1");
        $this->call_resolve("hex_12_8");
        $this->dispatchAll();
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_brute_1"));
        $this->assertEquals(0, $this->getDamage("monster_brute_1"));
    }

    public function testNoDiceRolled(): void {
        // killMonster is direct kill — no dice
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->call_resolve("hex_12_8");
        $diceOnDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        $this->assertCount(0, $diceOnDisplay);
    }
}
