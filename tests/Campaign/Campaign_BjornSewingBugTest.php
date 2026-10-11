<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

class Campaign_BjornSewingBugTest extends CampaignBaseTest {
    public function testSewingDoesNotHealHero(): void {
        $this->setupGame([1]);
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);
        $this->clearMonstersFromMap();
        foreach (array_keys($this->game->tokens->getTokensOfTypeInLocation("card_event", "hand_$color")) as $card) {
            $this->game->tokens->moveToken($card, "limbo");
        }

        $sewing = "card_event_1_30_1";
        $this->seedHand($sewing, $color);
        $this->game->tokens->moveToken("card_equip_1_21", "tableau_$color");
        $this->game->effect_moveCrystals("card_equip_1_21", "red", 1, "card_equip_1_21", ["message" => ""]);
        $this->game->effect_moveCrystals($heroId, "red", 2, $heroId, ["message" => ""]);

        $this->respond($sewing);
        $this->confirmCardEffect();

        $this->assertEquals(0, $this->countDamage("card_equip_1_21"));
        // Documents buggy behavior for BGA #242206; flip the assertion when fixed (expect 1)
        $this->assertEquals(2, $this->countDamage($heroId));
    }
}
