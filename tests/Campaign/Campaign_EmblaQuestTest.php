<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * End-to-end integration tests for Embla-specific quests.
 *
 * Each test scripts a scenario that exercises one of Embla's quest cards
 * (defined in misc/card_equip_material.csv with quest_on / quest_r) end-to-end:
 * player invokes completeQuest (or trigger fires) → quest_r runs → progress
 * accumulates / equip lands on tableau.
 */
class Campaign_EmblaQuestTest extends CampaignBaseTest {
    /**
     * Leg Guards (card_equip_3_23): quest_on= (empty — player-initiated),
     * quest_r=spendAction(actionFocus):gainEquip.
     *
     * Player invokes the top-bar `completeQuest` free action while Leg Guards is
     * on top of deck_equip_<color>. The chain spends a Focus action then runs
     * gainEquip, which moves the card to tableau and reveals the next deck-top.
     *
     * Bypasses the Op_turn flow and pushes `completeQuest` directly onto the
     * machine — matches the Belt of Youth pattern in Campaign_AlvaQuestTest.
     */
    public function testLegGuardsLandsOnTableauAfterCompleteQuest(): void {
        $this->setupGame([3]); // Solo Embla — Leg Guards lives in Embla's deck
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $legGuards = "card_equip_3_23";
        $nextCard = "card_equip_3_16"; // Healing Potion — surfaces after Leg Guards is claimed
        $this->seedDeck("deck_equip_$color", [$legGuards, $nextCard]);
        $this->assertEquals($legGuards, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        $this->game->machine->push("completeQuest", $color);
        $this->game->machine->dispatchAll();

        // completeQuest prompts for the deck-top card; pick Leg Guards.
        $this->assertOperation("completeQuest");
        $this->assertValidTarget($legGuards);
        $this->respond($legGuards);

        // Chained spendAction(actionFocus) is a single-confirm op — accept it.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($legGuards),
            "Leg Guards should land on tableau after completeQuest"
        );

        $hero = $this->game->getHero($color);
        $this->assertContains(
            "actionFocus",
            $hero->getActionsTaken(),
            "Focus action should be marked as taken after spendAction(actionFocus)"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Healing Potion should surface as the new deck-top");
    }
}
