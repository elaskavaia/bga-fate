<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

class Campaign_BoldurSweepArmorTest extends CampaignBaseTest {
    private string $color;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]); // Solo Boldur
        $this->color = $this->getActivePlayerColor();
        $this->clearMonstersFromMap();
        $this->clearHand($this->color);
    }

    public function testSweepIgnoresDraugrArmor(): void {
        $cardId = "card_ability_4_5";
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->game->getHeroTokenId($this->color), "hex_5_9");

        $skeleton = "monster_skeleton_1";
        $draugr = "monster_draugr_1";
        $this->game->getMonster($skeleton)->moveTo("hex_4_9", "");
        $this->game->getMonster($draugr)->moveTo("hex_5_8", "");
        $this->assertEquals(1, $this->game->getMonster($draugr)->getArmor(), "sanity: draugr has armor 1");

        $this->seedRand([5, 5, 5, 1]); // 3 hits + 1 passive = 4 vs skeleton health 3 -> 1 overkill

        $this->respond("actionAttack");
        $this->respond("hex_4_9");
        $this->respond($cardId);
        $this->confirmCardEffect();

        $this->assertEquals("supply_monster", $this->tokenLocation($skeleton), "skeleton should be dead");
        // Pins buggy behavior for BGA #251884 - flip the assertion when the fix lands (expected 0: armor prevents 1)
        $this->assertEquals(1, $this->countDamage($draugr), "sweep damage currently bypasses draugr armor");
    }
}
