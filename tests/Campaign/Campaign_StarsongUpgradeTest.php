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

    /** BGA #251068: Starsong gained in the upgrade draws its extra card that same turn. */
    public function testStarsongGainedInUpgradeDrawsExtraSameTurn(): void {
        $heroId = $this->game->getHeroTokenId($this->color);
        $this->game->effect_moveCrystals($heroId, "yellow", 5, "tableau_" . $this->color, ["message" => ""]);
        $this->seedDeck("deck_ability_" . $this->color, ["card_ability_2_7"]);

        $this->respond("hex_7_9");
        $this->respond("actionFocus");
        $this->skip();
        $this->assertOperation("upgrade");
        $this->respond("card_ability_2_7");
        $this->assertEquals("tableau_" . $this->color, $this->tokenLocation("card_ability_2_7"));
        $this->drawEndOfTurnCards(2);
    }

    /** BGA #251630: Starsong already in play draws at step 4, after the upgrade, not before it. */
    public function testStarsongDrawsAfterUpgradeOnLaterTurns(): void {
        $heroId = $this->game->getHeroTokenId($this->color);
        $this->game->tokens->moveToken("card_ability_2_7", "tableau_" . $this->color);
        $this->game->effect_moveCrystals($heroId, "yellow", 5, "tableau_" . $this->color, ["message" => ""]);

        $this->respond("hex_7_9");
        $this->respond("actionFocus");
        $this->skip();
        $this->assertOperation("upgrade");
        $this->skip();
        $this->drawEndOfTurnCards(2);
    }

    /** BGA #251068: improving to Starsong II in the upgrade draws 2 extra with the 5-card limit that same turn. */
    public function testStarsongImprovedInUpgradeDrawsTwoExtraWithFiveCardLimit(): void {
        $heroId = $this->game->getHeroTokenId($this->color);
        $this->game->tokens->moveToken("card_ability_2_7", "tableau_" . $this->color);
        $this->game->effect_moveCrystals($heroId, "yellow", 5, "tableau_" . $this->color, ["message" => ""]);
        $this->game->tokens->moveToken("card_event_2_31_1", "hand_" . $this->color);
        $this->game->tokens->moveToken("card_event_2_31_2", "hand_" . $this->color);

        $this->respond("hex_7_9");
        $this->respond("actionFocus");
        $this->skip();
        $this->assertOperation("upgrade");
        $this->respond("card_ability_2_7");
        $this->assertEquals("tableau_" . $this->color, $this->tokenLocation("card_ability_2_8"));
        $this->drawEndOfTurnCards(5);
    }

    private function drawEndOfTurnCards(int $handCount): void {
        $this->assertOperation("useCard");
        $this->confirmCardEffect();
        $this->assertOperation("drawEvent");
        $this->respond("confirm");
        $this->assertOperation("drawEvent");
        $this->respond("confirm");
        $this->skipIfOp("demote");
        $this->assertCount($handCount, $this->game->tokens->getTokensOfTypeInLocation("card", "hand_" . $this->color));
    }
}
