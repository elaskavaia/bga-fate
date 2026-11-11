<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/** BGA #255758: Stitching II grants Bjorn +2 max health (printed blood-drop icon). */
class Campaign_Bug255758Test extends CampaignBaseTest {
    public function testStitchingIIHealthBonus(): void {
        $this->setupGame([1]); // Solo Bjorn
        $color = $this->getActivePlayerColor();
        $hero = $this->game->getHero($color);
        $hero->recalcTrackers();
        $before = $hero->getMaxHealth();

        $this->game->tokens->moveToken("card_ability_1_8", "tableau_" . $color);
        $hero->recalcTrackers();

        $this->assertEquals($before + 2, $hero->getMaxHealth(), "Stitching II grants +2 health");
    }
}
