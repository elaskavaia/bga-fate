<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/** BGA #252119: Tiara gives no gold on the turn Alva gains it, only from her next turn start. */
class Campaign_Bug252119Test extends CampaignBaseTest {
    private string $color;
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([2]); // Solo Alva
        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->clearMonstersFromMap();
    }

    public function testTiaraNoGoldOnTurnGained(): void {
        $tiara = "card_equip_2_16";
        $this->seedDeck("deck_equip_" . $this->color, [$tiara, "card_equip_2_22"]);
        $this->seedDeck("deck_monster_yellow", ["card_monster_7", "card_monster_8"]);
        $this->seedDeck("deck_event_" . $this->color, ["card_event_2_31_1", "card_event_2_31_2"]);

        $this->game->tokens->moveToken($this->heroId, "hex_6_5");
        $this->game->hexMap->invalidateOccupancy();
        $xpBefore = $this->countXp();
        $this->game->machine->push("move", $this->color, ["target" => "hex_6_4"]);
        $this->game->machine->dispatchAll();
        $this->confirmCardEffect();
        $this->assertEquals("tableau_" . $this->color, $this->tokenLocation($tiara), "sanity: Tiara gained this turn");

        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skip();
        $this->assertOperation("drawEvent");

        // pins buggy behavior for BGA #252119; flip to 5 / +2 when the fix lands
        $this->assertEquals(6, $this->countTokens("crystal_yellow", $tiara), "no gold taken from Tiara on the turn it was gained");
        $this->assertEquals($xpBefore + 1, $this->countXp(), "only Practice +1, no Tiara gold");
    }
}
