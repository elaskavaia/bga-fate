<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\Tests\GameUT;
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
    // applyDamageEffects
    // -------------------------------------------------------------------------

    public function testDamageKillsMonsterWhenEnough(): void {
        // Goblin: health=2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        // Pre-place damage crystals
        $this->game->effect_moveCrystals("monster_goblin_1", "red", 2, "monster_goblin_1", ["message" => ""]);

        $monster = $this->game->getMonster("monster_goblin_1");
        $killed = $monster->applyDamageEffects(2, "hero_1");
        $this->assertTrue($killed);
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));

        // No red crystals should remain on monster
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_goblin_1");
        $this->assertCount(0, $crystals);
    }

    public function testDamageDoesNotKillWhenInsufficient(): void {
        // Goblin: health=2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        // Pre-place 1 damage crystal
        $this->game->effect_moveCrystals("monster_goblin_1", "red", 1, "monster_goblin_1", ["message" => ""]);

        $killed = $this->game->getMonster("monster_goblin_1")->applyDamageEffects(1, "hero_1");
        $this->assertFalse($killed);
        $this->assertEquals("hex_12_8", $this->game->tokens->getTokenLocation("monster_goblin_1"));

        // 1 red crystal should be on monster
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_goblin_1");
        $this->assertCount(1, $crystals);
    }

    public function testDamageAccumulates(): void {
        // Troll: health=7
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        // First attack: 3 damage
        $this->game->effect_moveCrystals("monster_troll_1", "red", 3, "monster_troll_1", ["message" => ""]);
        $killed = $this->game->getMonster("monster_troll_1")->applyDamageEffects(3, "hero_1");
        $this->assertFalse($killed);
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_troll_1");
        $this->assertCount(3, $crystals);

        // Second attack: 4 more damage — total 7, enough to kill
        $this->game->effect_moveCrystals("monster_troll_1", "red", 4, "monster_troll_1", ["message" => ""]);
        $killed = $this->game->getMonster("monster_troll_1")->applyDamageEffects(4, "hero_1");
        $this->assertTrue($killed);
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_troll_1"));

        // All red crystals returned to supply
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_troll_1");
        $this->assertCount(0, $crystals);
    }

    public function testZeroDamageDoesNothing(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        $killed = $this->game->getMonster("monster_goblin_1")->applyDamageEffects(0, "hero_1");
        $this->assertFalse($killed);
        $this->assertEquals("hex_12_8", $this->game->tokens->getTokenLocation("monster_goblin_1"));

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_goblin_1");
        $this->assertCount(0, $crystals);
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
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        $monster = $this->game->getMonster("monster_goblin_1");
        $this->assertEquals(1, $monster->countHit("hit", "hero_1"));
    }

    public function testCountHitMiss(): void {
        $monster = $this->game->getMonster("monster_goblin_1");
        $this->assertEquals(0, $monster->countHit("miss", "hero_1"));
    }

    public function testCountHitRuneNotHitForNonDead(): void {
        // Goblin is trollkin — rune should miss
        $monster = $this->game->getMonster("monster_goblin_1");
        $this->assertEquals(0, $monster->countHit("rune", "hero_1"));
    }

    public function testCountHitRuneCountsAsHitForDeadAttacker(): void {
        // Imp is dead faction — rune should count as hit
        $hero = $this->game->getHeroById("hero_1");
        $this->assertEquals(1, $hero->countHit("rune", "monster_imp_1"));
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
        $this->game->tokens->moveToken("monster_draugr_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        $monster = $this->game->getMonster("monster_draugr_1");

        $this->assertEquals(1, $monster->countHit("hit", "hero_1"));
        $this->assertEquals(0, $monster->countHit("miss", "hero_1"));
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
