<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_c_prey;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_c_preyTest extends AbstractOpTestCase {
    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        $this->game->setPlayersNumber(1);
        $this->game->tokens->moveToken("card_hero_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_8_9");
        $this->game->tokens->moveToken("hero_2", "hex_1_1");
        $this->game->tokens->moveToken("hero_3", "hex_1_2");
        $this->game->tokens->moveToken("hero_4", "hex_2_1");
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());
        $this->op = $this->createOp();
    }

    private function yellowOn(string $loc): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", $loc));
    }

    private function redOn(string $loc): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $loc));
    }

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
        $this->assertEquals(2, $this->yellowOn("monster_troll_1"));
    }

    // ---- bonus XP on kill ----

    public function testBonusXpAwardedOnKill(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_5");
        // Mark via Prey
        $op = $this->op;
        $this->call_resolve("hex_12_5");
        $this->assertEquals(2, $this->yellowOn("monster_troll_1"));

        // Kill the troll directly via the model
        $troll = $this->game->getMonster("monster_troll_1");
        $baseXp = $troll->getXpReward();
        // Pre-load damage equal to health so applyDamageEffects(0) kills it
        $health = $troll->getHealth();
        for ($i = 0; $i < $health; $i++) {
            $this->game->tokens->moveToken("crystal_red_" . ($i + 1), "monster_troll_1");
        }
        $troll->applyDamageEffects(0, "hero_1");

        // Troll removed
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_troll_1"));
        // Yellow crystals returned to supply, not stuck on the monster
        $this->assertEquals(0, $this->yellowOn("monster_troll_1"));
        // Hero gained base + 2 XP
        $heroXp = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $this->assertEquals($baseXp + 2, $heroXp);
    }
}
