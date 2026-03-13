<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\Tests\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class HeroTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        // Assign hero 1 (Bjorn) to PCOLOR: strength 2, starting ability (Sure Shot), starting equip (+1)
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_ability_1_3", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_equip_1_15", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    // -------------------------------------------------------------------------
    // getAttackStrength
    // -------------------------------------------------------------------------

    public function testAttackStrengthBjornStarting(): void {
        // Bjorn hero card: strength=2, starting equip (Bjorn's First Bow): +1, ability (Sure Shot I): no strength
        $hero = $this->game->getHeroById("hero_1");
        $this->assertEquals(3, $hero->getAttackStrength()); // 2 + 1
    }

    public function testAttackStrengthHeroCardOnly(): void {
        // Remove equipment and ability
        $this->game->tokens->moveToken("card_equip_1_15", "limbo");
        $this->game->tokens->moveToken("card_ability_1_3", "limbo");
        $hero = $this->game->getHeroById("hero_1");
        $this->assertEquals(2, $hero->getAttackStrength()); // hero card only
    }

    // -------------------------------------------------------------------------
    // gainXp
    // -------------------------------------------------------------------------

    public function testXpAwardedOnKill(): void {
        // Goblin: xp=1, health=2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        // Pre-place enough damage crystals to kill
        $this->game->effect_moveCrystals("monster_goblin_1", "red", 2, "monster_goblin_1", ["message" => ""]);

        $xpBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));

        $monster = $this->game->getMonster("monster_goblin_1");
        $killed = $monster->applyDamageEffects(2, "hero_1");
        $this->assertTrue($killed);
        $hero = $this->game->getHero(PCOLOR);
        $hero->gainXp($monster->getXpReward());

        $xpAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $this->assertEquals($xpBefore + 1, $xpAfter);
    }

    public function testXpNotAwardedOnSurvive(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        // Pre-place 1 damage crystal (not enough to kill goblin with health=2)
        $this->game->effect_moveCrystals("monster_goblin_1", "red", 1, "monster_goblin_1", ["message" => ""]);

        $xpBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));

        $monster = $this->game->getMonster("monster_goblin_1");
        $killed = $monster->applyDamageEffects(1, "hero_1");
        $this->assertFalse($killed);
        // No XP awarded since monster survived

        $xpAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $this->assertEquals($xpBefore, $xpAfter);
    }

    public function testBruteGivesMoreXp(): void {
        // Brute: health=3, xp=2
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        // Pre-place enough damage crystals to kill
        $this->game->effect_moveCrystals("monster_brute_1", "red", 3, "monster_brute_1", ["message" => ""]);

        $xpBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));

        $monster = $this->game->getMonster("monster_brute_1");
        $killed = $monster->applyDamageEffects(3, "hero_1");
        $this->assertTrue($killed);
        $hero = $this->game->getHero(PCOLOR);
        $hero->gainXp($monster->getXpReward());

        $xpAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $this->assertEquals($xpBefore + 2, $xpAfter);
    }

    // -------------------------------------------------------------------------
    // applyDamageEffects / knockout
    // -------------------------------------------------------------------------

    public function testHeroKnockedOutWhenDamageReachesHealth(): void {
        // Bjorn health=9. Pre-place 8 damage, then 1 more → total 9 = knocked out
        $this->game->effect_moveCrystals("hero_1", "red", 9, "hero_1", ["message" => ""]);

        $hero = $this->game->getHeroById("hero_1");
        $knocked = $hero->applyDamageEffects(1);
        $this->assertTrue($knocked);

        $heroLoc = $this->game->tokens->getTokenLocation("hero_1");
        $this->assertTrue($this->game->hexMap->isInGrimheim($heroLoc), "Hero should be in Grimheim, got $heroLoc");

        // Damage adjusted to 5
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(5, $crystals);

        // 2 houses destroyed
        $houses = $this->game->tokens->getTokensOfTypeInLocation("house", "hex%");
        $this->assertCount(8, $houses); // 10 - 2
    }

    public function testHeroNotKnockedOutBelowHealth(): void {
        // Bjorn health=9. Pre-place 7 damage, then 1 more → total 8 < 9
        $this->game->effect_moveCrystals("hero_1", "red", 8, "hero_1", ["message" => ""]);

        $hero = $this->game->getHeroById("hero_1");
        $knocked = $hero->applyDamageEffects(1);

        $this->assertFalse($knocked);
        $this->assertEquals("hex_11_8", $this->game->tokens->getTokenLocation("hero_1"));

        // 8 damage total
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(8, $crystals);
    }

    public function testHeroKnockedOutExcessDamageCapsAt5(): void {
        // Boldur health=6. Pre-place 5 damage, then 3 more → total 8 >= 6, knocked out, capped at 5
        $this->game->tokens->moveToken("card_hero_4_1", "tableau_" . BCOLOR);
        $this->game->tokens->moveToken("hero_4", "hex_11_7");
        $this->game->effect_moveCrystals("hero_4", "red", 8, "hero_4", ["message" => ""]);

        $hero = $this->game->getHeroById("hero_4");
        $knocked = $hero->applyDamageEffects(3);
        $this->assertTrue($knocked);

        $heroLoc = $this->game->tokens->getTokenLocation("hero_4");
        $this->assertTrue($this->game->hexMap->isInGrimheim($heroLoc), "Hero should be in Grimheim, got $heroLoc");

        // Damage set to exactly 5
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_4");
        $this->assertCount(5, $crystals);
    }

    // -------------------------------------------------------------------------
    // getAttackRange
    // -------------------------------------------------------------------------

    public function testBjornWithBowHasRange2(): void {
        $hero = $this->game->getHeroById("hero_1");
        $this->assertEquals(2, $hero->getAttackRange());
    }

    public function testHeroWithoutBowHasRange1(): void {
        // Remove bow
        $this->game->tokens->moveToken("card_equip_1_15", "limbo");
        $hero = $this->game->getHeroById("hero_1");
        $this->assertEquals(1, $hero->getAttackRange());
    }
}
