<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Boldur's ability and hero cards.
 * Scripts full game turns using the harness GameDriver in-process.
 */
class Campaign_BoldurAbilityTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]); // Solo Boldur
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());

        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    // --- Beefy Berserker I (card_ability_4_9) ---
    // No r/on — passive: runes [RUNE] always count as hits when this hero attacks.
    // Hooked in Character::countHit: if attacker is a hero whose tableau holds 4_9 or 4_10,
    // a "rune" die result is treated as a hit.
    // Card also grants +1 strength (one extra die).

    public function testBeefyBerserkerICountsRunesAsHits(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_4_9";
        $this->game->tokens->moveToken($cardId, "tableau_$color");
        // strength tracker only recomputes on setup/turnEnd/upgrade — recalc to pick up the +1 die
        $this->game->getHero($color)->recalcTrackers();

        // Boldur I (3) + Boldur's First Pick (1) + Beefy Berserker I (1) = 5 dice.
        $this->assertEquals(5, $this->game->getHero($color)->getAttackStrength());

        // Boldur on plains outside Grimheim, troll (health=6) adjacent — survives the attack.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_7_8", "");

        // 2 runes (3) + 3 misses (1). Without the card, runes wouldn't count → 0 damage.
        // With Beefy Berserker I, both runes count as hits → 2 damage.
        $this->seedRand([3, 3, 1, 1, 1]);
        // Op_turn inlines attack-target hexes — pick the troll's hex directly.
        $this->respond("hex_7_8");

        $this->assertEquals(2, $this->countDamage($troll));
        $this->assertEquals("hex_7_8", $this->tokenLocation($troll));
        $this->assertEmpty($this->game->randQueue, "Strength should consume exactly 5 dice");
    }
}
