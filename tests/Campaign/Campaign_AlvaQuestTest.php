<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * End-to-end integration tests for Alva-specific quests.
 *
 * Each test scripts a scenario that exercises one of Alva's quest cards
 * (defined in misc/card_equip_material.csv with quest_on / quest_r) end-to-end:
 * trigger fires → quest_r runs → progress accumulates / equip lands on tableau.
 */
class Campaign_AlvaQuestTest extends CampaignBaseTest {
    /**
     * Belt of Youth (card_equip_2_22): quest_on=TStep,
     * quest_r=in(forest):gainTracker:check('countTracker>=8'):gainEquip.
     *
     * After 8 TStep firings on forest hexes, the card leaves deck_equip_<color>
     * and lands on tableau_<color>, with all progress crystals swept to supply.
     *
     * Bypasses the actionMove turn flow and pushes single-step `move` ops directly.
     * Hero oscillates between two adjacent forest hexes — simpler than scripting a
     * multi-hex forest path, and equally valid for the quest engine.
     */
    public function testBeltOfYouthLandsOnTableauAfter8ForestSteps(): void {
        $this->setupGame([2]); // Solo Alva — Belt of Youth lives in Alva's deck
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $belt = "card_equip_2_22";
        $nextCard = "card_equip_2_25"; // Bloodline Crystal — surfaces after Belt is claimed
        $this->seedDeck("deck_equip_$color", [$belt, $nextCard]);
        $this->assertEquals($belt, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Two adjacent forest hexes (both in the DarkForest cluster).
        $hexA = "hex_10_2";
        $hexB = "hex_11_2";
        $this->game->tokens->moveToken($heroId, $hexA);

        // 8 single-step moves, oscillating A → B → A → B …
        // Each move emits TStep at the destination (forest), so quest_r runs:
        // in(forest) passes → gainTracker → check(countTracker>=8) → gainEquip.
        // push() interrupts on top of the active turn op. LIFO order means we
        // push in reverse so the dispatch sequence is i=0, 1, 2, … 7
        // (alternating B, A, B, A, …; hero starts at A so first move is real).
        for ($i = 7; $i >= 0; $i--) {
            $target = $i % 2 === 0 ? $hexB : $hexA;
            $this->game->machine->push("move", $color, ["target" => $target, "reason" => "Op_actionMove"]);
        }

        $this->game->machine->dispatchAll();

        $this->assertEquals("tableau_$color", $this->tokenLocation($belt), "Belt of Youth should land on tableau after 8 forest TSteps");
        $this->assertEquals(0, $this->countTokens("crystal_red", $belt), "Op_gainEquip should sweep tracker crystals back to supply");

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Bloodline Crystal should surface as the new deck-top");
    }
}
