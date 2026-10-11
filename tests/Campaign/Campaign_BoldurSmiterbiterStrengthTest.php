<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

class Campaign_BoldurSmiterbiterStrengthTest extends CampaignBaseTest {
    public function testSmiterbiterStrengthBgaBug246556(): void {
        $this->setupGame([4]);
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_equip_4_15", "limbo");
        $this->game->tokens->moveToken("card_equip_4_21", "tableau_$color");
        $this->game->tokens->moveToken("card_equip_4_25", "tableau_$color");
        $hero = $this->game->getHero($color);
        $hero->recalcTrackers();

        // Documents buggy behavior for BGA #246556; flip the assertion when fixed
        // (printed Smiterbiter is +3: Hero I 3 + Smiterbiter 3 + Dwarf Pick 1 = 7)
        $this->assertEquals(1, (int) $this->game->material->getRulesFor("card_equip_4_21", "strength", ""));
        $this->assertEquals(5, $hero->calcBaseStrength());
        $this->assertEquals(5, $hero->getAttackStrength());
    }
}
