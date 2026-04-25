<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Embla's equipment cards.
 * Scripts full game turns using the harness GameDriver in-process.
 */
class Campaign_EmblaEquipTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([3]); // Solo Embla
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());

        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    // --- Blade Decorations (card_equip_3_19) ---
    // Passive +1 strength, no r, no on. Pure stat boost.
    // Base Embla I = 3 + Flimsy Blade(1) = 4 → with Blade Decorations = 5 dice.

    public function testBladeDecorationsAddsOneStrengthDie(): void {
        $cardId = "card_equip_3_19";
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($cardId, "tableau_$color");
        // Recompute strength tracker — calcBaseStrength only runs on setup/turnEnd/upgrade.
        $this->game->getHero($color)->recalcTrackers();

        $this->assertEquals(5, $this->game->getHero($color)->getAttackStrength());

        // Place Embla outside Grimheim, troll (health=6) adjacent — survives 5 hits.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_7_8", "");

        // Seed exactly 5 dice — if strength were 4, only 4 would be consumed and the
        // 5th would leak; if strength were 6, the queue would underflow. All hits.
        $this->seedRand([5, 5, 5, 5, 5]);
        // actionAttack is inline — the turn op exposes attack-target hexes directly.
        $this->respond("hex_7_8");

        // Troll took 5 damage (survives, health=6).
        $this->assertEquals(5, $this->countDamage($troll));
        $this->assertEquals("hex_7_8", $this->tokenLocation($troll));
        // Rand queue exhausted — confirms exactly 5 dice were rolled.
        $this->assertEmpty($this->game->randQueue, "Strength should consume exactly 5 dice");
    }

    // --- Raven's Claw (card_equip_3_22) ---
    // r=2addDamage, on=TActionAttack, strength=1.
    // Main weapon: passive +1 strength; on attack, useCard adds 2 damage.

    public function testRavensClawAddsTwoDamageOnAttack(): void {
        $cardId = "card_equip_3_22";
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($cardId, "tableau_$color");
        $this->game->getHero($color)->recalcTrackers();

        // Embla outside Grimheim, troll (health=6) adjacent — survives 2 damage.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_7_8", "");

        // 5 dice (Embla I=3 + Flimsy Blade=1 + Raven's Claw=1), all misses → base 0 damage.
        $this->seedRand([1, 1, 1, 1, 1]);
        $this->respond("hex_7_8");

        // TActionAttack trigger queues useCard with Raven's Claw as a target.
        $this->assertOperation("useCard");
        $this->assertValidTarget($cardId);
        $this->respond($cardId);
        $this->confirmCardEffect();

        // 2 damage added by Raven's Claw, no other source.
        $this->assertEquals(2, $this->countDamage($troll));
        $this->assertEquals("hex_7_8", $this->tokenLocation($troll));
    }

    // --- Wildfire Blade (card_equip_3_21) ---
    // r=dealDamage(adj), on=TAfterActionAttack, strength=2.
    // Main weapon: after attack action, deal 1 damage to a hero-adjacent monster.

    public function testWildfireBladeDealsOneDamageToAdjacentMonsterAfterAttack(): void {
        $cardId = "card_equip_3_21";
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($cardId, "tableau_$color");
        $this->game->getHero($color)->recalcTrackers();

        // Embla at hex_7_9 with two adjacent monsters: primary (attack target) and secondary.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $primary = "monster_brute_1"; // hex_7_8 — attack target, survives 0 damage
        $secondary = "monster_brute_2"; // hex_6_9 — adjacent to hero, untouched by attack
        $this->game->getMonster($primary)->moveTo("hex_7_8", "");
        $this->game->getMonster($secondary)->moveTo("hex_6_9", "");

        // 6 dice (Embla I=3 + Flimsy Blade=1 + Wildfire Blade=2), all misses → primary 0 damage.
        $this->seedRand([1, 1, 1, 1, 1, 1]);
        $this->respond("hex_7_8");

        // TAfterActionAttack trigger queues useCard with Wildfire Blade.
        $this->assertOperation("useCard");
        $this->assertValidTarget($cardId);
        $this->respond($cardId);

        // dealDamage(adj) prompts for the adjacent monster (target pick doubles as confirm).
        $this->respond("hex_6_9");

        $this->assertEquals(0, $this->countDamage($primary), "Primary takes no damage (all misses)");
        $this->assertEquals(1, $this->countDamage($secondary), "Wildfire Blade deals 1 damage to secondary");
    }
}
