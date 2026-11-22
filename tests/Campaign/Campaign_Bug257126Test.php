<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * BGA #257126: damage stored on Smiterbiter (red crystals) is offered and removed by the Grimheim mend.
 */
class Campaign_Bug257126Test extends CampaignBaseTest {
    private const SMITER = "card_equip_4_21";

    public function testGrimheimMendStripsSmiterbiterStoredDamage(): void {
        $this->setupGame([4]);
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);
        $this->clearMonstersFromMap();
        $this->clearHand($color);
        $this->game->tokens->moveToken(self::SMITER, "tableau_$color");
        $this->game->effect_moveCrystals($heroId, "red", 3, self::SMITER, ["message" => ""]);
        $this->assertEquals("hex_10_9", $this->tokenLocation($heroId));
        $this->assertEquals(3, $this->countDamage(self::SMITER));

        $this->skipIfOp("drawEvent");
        // Smiterbiter is the only damaged target, so the mend auto-resolves onto it
        $this->respond("actionMend");
        // documents buggy behavior for BGA #257126; flip the assertion when the fix lands
        $this->assertEquals(0, $this->countDamage(self::SMITER));
    }
}
