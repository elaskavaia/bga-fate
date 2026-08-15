<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * BGA #237686: Eagle Eye grants only attack range.
 *
 * The printed cards carry a strength badge in the corner (+1 on level I, +2 on
 * level II) on top of the range text, and Eagle Eye II's text adds "Always
 * ignore the armor". The original CSV had the strength; f7b70ae removed it
 * while adding range, and the armor sentence was never implemented.
 */
class Campaign_EagleEyeBug237686Test extends CampaignBaseTest {
    private string $color;

    private function boot(): void {
        $this->setupGame([1]); // solo Bjorn
        $this->color = $this->getActivePlayerColor();
        $this->clearMonstersFromMap();
        $this->clearHand($this->color);
        $this->clearEquipDecks();
    }

    private function addAbility(string $cardId): void {
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->color);
        $this->game->getHero($this->color)->recalcTrackers();
    }

    public function testEagleEyeIAddsOneStrength(): void {
        $this->boot();
        $hero = $this->game->getHero($this->color);
        $this->assertEquals(3, $hero->getAttackStrength(), "baseline: 2 hero + 1 starting bow");

        $this->addAbility("card_ability_1_9"); // Eagle Eye I

        $this->assertEquals(4, $hero->getAttackStrength(), "corner badge grants +1 strength");
        $this->assertEquals(3, $hero->getAttackRange(), "range +1 unchanged: base 1 + bow 1 + Eagle Eye 1");
    }

    public function testEagleEyeIIAddsTwoStrength(): void {
        $this->boot();
        $this->addAbility("card_ability_1_10"); // Eagle Eye II

        $hero = $this->game->getHero($this->color);
        $this->assertEquals(5, $hero->getAttackStrength(), "corner badge grants +2 strength");
        $this->assertEquals(4, $hero->getAttackRange(), "range +2 unchanged: base 1 + bow 1 + Eagle Eye 2");
    }

    /** Control: without Eagle Eye II, draugr armor still absorbs 1 hit. */
    public function testDraugrArmorReducesHitsWithoutEagleEyeII(): void {
        $this->boot();
        $this->game->getMonster("monster_draugr_1")->moveTo("hex_8_8", "");
        $this->game->tokens->moveToken($this->game->getHeroTokenId($this->color), "hex_7_9");

        $this->seedRand([5, 5, 1]); // strength 3: 2 hits, 1 miss
        // Single monster on the map: the attack target auto-resolves and the dice roll at once.
        $this->respond("actionAttack");
        $this->skipIfOp("useCard"); // decline Bjorn Hero I's +1 damage

        $this->assertEquals(1, $this->countDamage("monster_draugr_1"), "2 hits - 1 armor");
    }

    public function testEagleEyeIIIgnoresDraugrArmor(): void {
        $this->boot();
        $this->addAbility("card_ability_1_10"); // Eagle Eye II
        $this->game->getMonster("monster_draugr_1")->moveTo("hex_8_8", "");
        $this->game->tokens->moveToken($this->game->getHeroTokenId($this->color), "hex_7_9");

        $this->seedRand([1, 1, 1, 5, 5]); // strength 5: the 2 hits are on dice 4-5, so they only exist if all 5 roll
        $this->respond("actionAttack");
        $this->skipIfOp("useCard"); // decline Bjorn Hero I's +1 damage

        $this->assertEquals(2, $this->countDamage("monster_draugr_1"), "both hits land - armor ignored");
    }
}
