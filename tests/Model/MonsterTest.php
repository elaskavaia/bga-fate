<?php

declare(strict_types=1);

use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class MonsterTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        // Assign hero 1 (Bjorn) to PCOLOR
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    // -------------------------------------------------------------------------
    // evaluateDamage
    // -------------------------------------------------------------------------

    public function testDamageKillsMonsterWhenEnough(): void {
        // Goblin: health=2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->effect_moveCrystals("monster_goblin_1", "red", 2, "monster_goblin_1", ["message" => ""]);

        $monster = $this->game->getMonster("monster_goblin_1");
        // Pure detection — evaluateDamage no longer mutates state.
        $result = $monster->evaluateDamage(2, "hero_1");
        $this->assertTrue($result["killed"]);
        $this->assertLessThanOrEqual(0, $result["remaining"]);

        // Cleanup is the cleanup-step's job (Op_finishKill in production); call it here.
        $monster->finalizeDamage(2, "hero_1");
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));
        $this->assertCount(0, $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_goblin_1"));
    }

    public function testDamageDoesNotKillWhenInsufficient(): void {
        // Goblin: health=2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->effect_moveCrystals("monster_goblin_1", "red", 1, "monster_goblin_1", ["message" => ""]);

        $result = $this->game->getMonster("monster_goblin_1")->evaluateDamage(1, "hero_1");
        $this->assertFalse($result["killed"]);
        $this->assertGreaterThan(0, $result["remaining"]);

        // No cleanup runs; monster stays on hex with its damage.
        $this->assertEquals("hex_12_8", $this->game->tokens->getTokenLocation("monster_goblin_1"));
        $this->assertCount(1, $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_goblin_1"));
    }

    public function testDamageAccumulates(): void {
        // Troll: health=7
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $monster = $this->game->getMonster("monster_troll_1");

        // First attack: 3 damage — survives
        $this->game->effect_moveCrystals("monster_troll_1", "red", 3, "monster_troll_1", ["message" => ""]);
        $result = $monster->evaluateDamage(3, "hero_1");
        $this->assertFalse($result["killed"]);
        $this->assertCount(3, $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_troll_1"));

        // Second attack: 4 more damage — total 7, enough to kill
        $this->game->effect_moveCrystals("monster_troll_1", "red", 4, "monster_troll_1", ["message" => ""]);
        $result = $monster->evaluateDamage(4, "hero_1");
        $this->assertTrue($result["killed"]);
        $this->assertLessThanOrEqual(0, $result["remaining"]);

        $monster->finalizeDamage(4, "hero_1");
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_troll_1"));
        $this->assertCount(0, $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_troll_1"));
    }

    public function testZeroDamageDoesNothing(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        $result = $this->game->getMonster("monster_goblin_1")->evaluateDamage(0, "hero_1");
        $this->assertFalse($result["killed"]);
        $this->assertGreaterThan(0, $result["remaining"]);
        $this->assertEquals("hex_12_8", $this->game->tokens->getTokenLocation("monster_goblin_1"));
        $this->assertCount(0, $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_goblin_1"));
    }

    // -------------------------------------------------------------------------
    // getAttackRange
    // -------------------------------------------------------------------------

    public function testFireHordeHasRange2(): void {
        $monster = $this->game->getMonster("monster_sprite_1");
        $this->assertEquals(2, $monster->getAttackRange());
    }

    public function testTrollkinHasRange1(): void {
        $monster = $this->game->getMonster("monster_goblin_1");
        $this->assertEquals(1, $monster->getAttackRange());
    }

    public function testSeerOfOdinIsDeadFaction(): void {
        // Seer of Odin is a Dead legend at both levels (not Fire Horde): range 1, gets dead rune-as-hit.
        foreach (["monster_legend_2_1", "monster_legend_2_2"] as $seer) {
            $monster = $this->game->getMonster($seer);
            $this->assertEquals("dead", $monster->getFaction(), "$seer faction");
            $this->assertEquals(1, $monster->getAttackRange(), "$seer attack range");
        }
    }

    public function testSurtIIHasRange3(): void {
        // Surt II (red) has attack range 3, himself only; Surt I stays firehorde range 2.
        $this->assertEquals(3, $this->game->getMonster("monster_legend_4_2")->getAttackRange());
        $this->assertEquals(2, $this->game->getMonster("monster_legend_4_1")->getAttackRange());
    }

    public function testGrendelIIRuneCountsAsTwoHits(): void {
        $grendel = $this->game->getMonster("monster_legend_3_2");
        $this->assertEquals(2, $grendel->countHit("rune"), "Grendel II: each rune = 2 hits");
        $this->assertEquals(1, $grendel->countHit("hit"), "a normal hit is still 1");
        // Ordinary trollkin runes remain misses.
        $this->assertEquals(0, $this->game->getMonster("monster_goblin_1")->countHit("rune"));
    }

    public function testQueenIIGivesOtherDeadPlusOneHealth(): void {
        $skeleton = $this->game->getMonster("monster_skeleton_1"); // dead
        $goblin = $this->game->getMonster("monster_goblin_1"); // trollkin
        $base = $skeleton->getHealth();

        // Queen II not on the board: no bonus.
        $this->assertEquals($base, $skeleton->getEffectiveHealth());

        // Queen II on the board: other Dead get +1, non-dead unaffected, Queen herself unaffected.
        $this->game->tokens->moveToken("monster_legend_1_2", "hex_5_5");
        $this->assertEquals($base + 1, $skeleton->getEffectiveHealth(), "skeleton +1 with Queen II in play");
        $this->assertEquals($goblin->getHealth(), $goblin->getEffectiveHealth(), "non-dead unaffected");
        $queen = $this->game->getMonster("monster_legend_1_2");
        $this->assertEquals($queen->getHealth(), $queen->getEffectiveHealth(), "Queen does not buff herself");
    }

    // -------------------------------------------------------------------------
    // getHealth / getXpReward
    // -------------------------------------------------------------------------

    public function testGoblinStats(): void {
        $monster = $this->game->getMonster("monster_goblin_1");
        $this->assertEquals(2, $monster->getHealth());
        $this->assertEquals(1, $monster->getXpReward());
        $this->assertEquals("trollkin", $monster->getFaction());
    }

    public function testBruteStats(): void {
        $monster = $this->game->getMonster("monster_brute_1");
        $this->assertEquals(3, $monster->getHealth());
        $this->assertEquals(2, $monster->getXpReward());
    }

    public function testTrollStats(): void {
        $monster = $this->game->getMonster("monster_troll_1");
        $this->assertEquals(7, $monster->getHealth());
    }

    // -------------------------------------------------------------------------
    // countHit (die result → hit count, no crystals)
    // -------------------------------------------------------------------------

    public function testCountHitHit(): void {
        $hero = $this->game->getHeroById("hero_1");
        $this->assertEquals(1, $hero->countHit("hit", "hex_12_8"));
    }

    public function testCountHitMiss(): void {
        $hero = $this->game->getHeroById("hero_1");
        $this->assertEquals(0, $hero->countHit("miss"));
    }

    public function testCountHitRuneNotHitForNonDead(): void {
        // Hero is not dead-faction — rune does not count as a hit
        $hero = $this->game->getHeroById("hero_1");
        $this->assertEquals(0, $hero->countHit("rune"));
    }

    public function testCountHitRuneCountsAsHitForDeadAttacker(): void {
        // Imp is dead faction — its rune counts as a hit
        $imp = $this->game->getMonster("monster_imp_1");
        $this->assertEquals(1, $imp->countHit("rune"));
    }

    // Designer ruling (BGG 3426870): Nidhuggr is faction "wyrm", not "dead" —
    // so the Dead faction's rune-die-as-hit rule does NOT apply.
    public function testNidhuggrFactionIsWyrm(): void {
        $this->assertEquals("wyrm", $this->game->getMonster("monster_legend_6_1")->getFaction());
        $this->assertEquals("wyrm", $this->game->getMonster("monster_legend_6_2")->getFaction());
    }

    public function testNidhuggrRuneIsNotHit(): void {
        $nidhuggr = $this->game->getMonster("monster_legend_6_1");
        $this->assertEquals(0, $nidhuggr->countHit("rune"));
    }

    public function testNidhuggrRangeIsOne(): void {
        // Wyrm has no faction-wide range bonus; default range = 1.
        $this->assertEquals(1, $this->game->getMonster("monster_legend_6_1")->getAttackRange());
    }

    public function testQueenOfDeadStillGetsRuneAsHit(): void {
        // Regression: Queen (legend 1) remains in the Dead faction.
        $queen = $this->game->getMonster("monster_legend_1_1");
        $this->assertEquals("dead", $queen->getFaction());
        $this->assertEquals(1, $queen->countHit("rune"));
    }

    // -------------------------------------------------------------------------
    // Armor (Draugr absorbs 1 hit per attack)
    // -------------------------------------------------------------------------

    public function testDraugrHasArmor(): void {
        $monster = $this->game->getMonster("monster_draugr_1");
        $this->assertEquals(1, $monster->getArmor());
    }

    public function testGoblinHasNoArmor(): void {
        $monster = $this->game->getMonster("monster_goblin_1");
        $this->assertEquals(0, $monster->getArmor());
    }

    public function testCountHitReturnsHits(): void {
        $hero = $this->game->getHeroById("hero_1");
        $this->assertEquals(1, $hero->countHit("hit", "hex_12_8"));
        $this->assertEquals(0, $hero->countHit("miss", "hex_12_8"));
    }

    public function testApplyArmorReducesHits(): void {
        $monster = $this->game->getMonster("monster_draugr_1");

        // Draugr has armor=1, so 2 hits → 1 effective damage
        $this->assertEquals(1, $monster->applyArmor(2));
        // 1 hit → 0 effective damage
        $this->assertEquals(0, $monster->applyArmor(1));
        // 0 hits → 0
        $this->assertEquals(0, $monster->applyArmor(0));
    }
}
