<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/** BGA #252904: Precision Axes "unpreventable" damage is still stopped by Draugr armor. */
class Campaign_Bug252904Test extends CampaignBaseTest {
    private string $color;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]); // Solo Boldur
        $this->color = $this->getActivePlayerColor();
        $this->clearMonstersFromMap();
        $this->clearHand($this->color);
    }

    public function testPrecisionAxesUnpreventableVsDraugrArmor(): void {
        $cardId = "card_equip_4_26";
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->game->getHeroTokenId($this->color), "hex_7_9");

        $draugr = "monster_draugr_1";
        $this->game->getMonster($draugr)->moveTo("hex_7_8", "");
        $this->assertEquals(1, $this->game->getMonster($draugr)->getArmor(), "sanity: draugr has armor 1");

        $this->respond($cardId);
        $this->confirmCardEffect();

        $this->assertEquals(1, $this->countTokens("crystal_red", $cardId), "durability spent");
        // documents buggy behavior for BGA #252904; flip assertion when fixed (expected 1: unpreventable ignores armor)
        $this->assertEquals(0, $this->countDamage($draugr), "draugr armor currently absorbs unpreventable damage");
    }
}
