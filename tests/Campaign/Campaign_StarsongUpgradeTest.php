<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Starsong gained in the end-of-turn upgrade (BGA #251068).
 */
class Campaign_StarsongUpgradeTest extends CampaignBaseTest {
    private string $color;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([2]);
        $this->color = $this->getActivePlayerColor();
        $this->seedDeck("deck_monster_yellow", ["card_monster_7", "card_monster_8", "card_monster_9", "card_monster_10"]);
        $this->clearHand($this->color);
        $this->clearMonstersFromMap();
    }

    // Pins current behavior for BGA #251068; flip if the ruling says Starsong should fire the turn it is gained.
    public function testStarsongGainedInUpgradeDoesNotDrawExtraSameTurn(): void {
        $heroId = $this->game->getHeroTokenId($this->color);
        $this->game->effect_moveCrystals($heroId, "yellow", 5, "tableau_" . $this->color, ["message" => ""]);
        $this->seedDeck("deck_ability_" . $this->color, ["card_ability_2_7"]);

        $this->respond("hex_7_9");
        $this->respond("actionFocus");
        $this->skip();
        $this->skipIfOp("trigger");
        $this->assertOperation("upgrade");
        $this->respond("card_ability_2_7");
        $this->assertEquals("tableau_" . $this->color, $this->tokenLocation("card_ability_2_7"));
        $this->assertOperation("drawEvent");
        $this->assertEquals(1, $this->getOpArgs()["count"]);
        $this->respond("confirm");
        $this->skipIfOp("demote");
        $this->assertCount(1, $this->game->tokens->getTokensOfTypeInLocation("card", "hand_" . $this->color));
    }
}
