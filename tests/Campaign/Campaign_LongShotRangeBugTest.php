<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Investigation for BGA #234927 - Bjorn "Long Shot I" (card_ability_1_11,
 * r=2addDamage(2), on=TActionAttack) reportedly not adding +2 damage when
 * attacking a target at range 2.
 *
 * Result: NOT reproducible. When the player selects Long Shot I during the
 * attack and confirms it, the +2 lands at distance exactly 2 (the min-range
 * predicate in Op_addDamage is `dist < minRange` -> at dist 2 with minRange 2
 * that is false, so the bonus applies). Long Shot I is an OPTIONAL, interactive
 * trigger (a useCard prompt plus a confirm) - it is NOT automatic. If the player
 * does not actively select and confirm it, no +2 is added, which matches the
 * report. This test documents the correct behavior as a regression guard.
 */
class Campaign_LongShotRangeBugTest extends CampaignBaseTest {
    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1]); // Solo Bjorn
        $this->clearEquipDecks();
        $this->seedDeck("deck_monster_yellow", [
            "card_monster_7",
            "card_monster_8",
            "card_monster_9",
            "card_monster_10",
        ]);
        $this->seedDeck("deck_event_" . $this->getActivePlayerColor(), [
            "card_event_1_27_1",
            "card_event_1_27_2",
        ]);
        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    public function testLongShotIAddsPlus2AtRangeExactly2(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_ability_1_11", "tableau_$color");
        $this->moveHeroOutOfGrimheim(); // hero -> hex_7_9

        $brute = "monster_brute_1"; // health 3, survives the +2
        $this->game->getMonster($brute)->moveTo("hex_5_9", ""); // distance exactly 2 from hex_7_9

        // Rig every base attack die to a miss so base damage is 0 - the only damage
        // source is Long Shot I's +2. Bjorn strength (Bjorn I 2 + First Bow 1) = 3 dice.
        $this->seedRand([1, 1, 1]);
        $this->respond("actionAttack"); // single target auto-selected -> roll -> useCard prompt

        $this->assertOperation("useCard");
        $this->assertValidTarget("card_ability_1_11", "Long Shot I should be offered at range 2");
        $this->respond("card_ability_1_11"); // select Long Shot -> queues 2addDamage(2)
        $this->respond("confirm"); // confirm the +2 addDamage
        $this->skipUseCard("card_hero_1_1"); // dismiss Bjorn Hero I (on=Roll)

        $this->assertEquals(2, $this->countDamage($brute), "Long Shot I applies +2 at distance exactly 2");
    }
}
