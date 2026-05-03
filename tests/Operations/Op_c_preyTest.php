<?php

declare(strict_types=1);

final class Op_c_preyTest extends AbstractOpTestCase {
    // ---- target selection ----

    public function testNoMonstersAutoSkips(): void {
        $this->assertNoValidTargets();
    }

    public function testRank3MonsterIsTarget(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_5");
        $this->assertValidTarget("hex_12_5");
    }

    public function testLegendMonsterIsTarget(): void {
        // Any legend works regardless of rank stat
        $this->game->tokens->moveToken("monster_legend_2_1", "hex_5_5");
        $this->assertValidTarget("hex_5_5");
    }

    public function testRank1MonsterNotTarget(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_5");
        $op = $this->op;
        $info = $op->getArgsInfo();
        $this->assertArrayNotHasKey("hex_12_5", $info);
    }

    public function testRank2MonsterNotTarget(): void {
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_5");
        $op = $this->op;
        $info = $op->getArgsInfo();
        $this->assertArrayNotHasKey("hex_12_5", $info);
    }

    public function testDamagedRank3NotTarget(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_5");
        $this->game->tokens->moveToken("crystal_red_1", "monster_troll_1");
        $op = $this->op;
        $info = $op->getArgsInfo();
        $this->assertArrayNotHasKey("hex_12_5", $info);
    }

    public function testTargetsAnyDistance(): void {
        // Hero at hex_8_9, pick a far-away hex — no range restriction
        $this->game->tokens->moveToken("monster_jotunn_1", "hex_2_10");
        $this->assertValidTarget("hex_2_10");
    }

    // ---- resolve ----

    public function testResolveMarksWithTwoYellow(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_5");
        $op = $this->op;
        $this->call_resolve("hex_12_5");
        $this->assertEquals(2, $this->countYellowCrystals("monster_troll_1"));
    }

    // ---- bonus XP on kill ----

    public function testBonusXpAwardedOnKill(): void {
        // Bjorn's deck is shuffled in setUp; if Quiver (TMonsterKilled quest) lands on top
        // it claims the troll kill and suppresses XP. This test is about Prey's bonus XP, not
        // quest interaction — clear the deck so kill cleanup runs the default path.
        $this->game->tokens->moveToken("card_equip_1_18", "limbo");

        $this->game->tokens->moveToken("monster_troll_1", "hex_12_5");
        // Mark via Prey
        $op = $this->op;
        $this->call_resolve("hex_12_5");
        $this->assertEquals(2, $this->countYellowCrystals("monster_troll_1"));

        $xpBefore = $this->countYellowCrystals($this->getPlayersTableau());

        // Kill the troll via Op_applyDamage directly so TMonsterKilled fires + Op_finishKill runs.
        // (Skipping Op_dealDamage because its range defaults to "adj" and the troll is far from the hero.)
        $troll = $this->game->getMonster("monster_troll_1");
        $baseXp = $troll->getXpReward();
        $health = $troll->getHealth();

        $applyOp = $this->createOp("applyDamage", [
            "target" => "monster_troll_1",
            "attacker" => "hero_1",
            "amount" => $health,
        ]);
        $applyOp->action_resolve([\Bga\Games\Fate\OpCommon\Operation::ARG_TARGET => "monster_troll_1"]);
        $this->dispatchAll();

        // Troll removed
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_troll_1"));
        // Yellow crystals returned to supply, not stuck on the monster
        $this->assertEquals(0, $this->countYellowCrystals("monster_troll_1"));
        // Hero gained base + 2 XP
        $heroXp = $this->countYellowCrystals($this->getPlayersTableau());
        $this->assertEquals($xpBefore + $baseXp + 2, $heroXp);
    }
}
