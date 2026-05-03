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
     * quest_r=in(forest):gainTracker,check('countTracker>=8'):gainEquip.
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

    /**
     * Throwing Darts (card_equip_2_17): quest_on= (empty — player-initiated),
     * quest_r=in(forest):spendAction(actionPractice):gainEquip.
     *
     * Player invokes the top-bar `completeQuest` free action while Throwing Darts
     * is on top of deck_equip_<color> AND the hero stands on a forest hex. The
     * chain spends a Practice action then runs gainEquip, which moves the card
     * to tableau and reveals the next deck-top.
     *
     * This test exercises the rule shape shared by all four "spend Practice in
     * forest" quest cards: Throwing Axes (Bjorn), Throwing Darts (Alva),
     * Throwing Knives (Embla), and Precision Axes (Boldur). One canonical test
     * is sufficient — the cards differ only in hero owner and active-effect text.
     */
    public function testThrowingDartsLandsOnTableauAfterCompleteQuestInForest(): void {
        $this->setupGame([2]); // Solo Alva — Throwing Darts lives in Alva's deck
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $darts = "card_equip_2_17";
        $nextCard = "card_equip_2_25"; // Bloodline Crystal — surfaces after Darts is claimed
        $this->seedDeck("deck_equip_$color", [$darts, $nextCard]);
        $this->assertEquals($darts, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Place hero on a forest hex so the in(forest) gate passes.
        $forestHex = "hex_2_8";
        $this->game->tokens->moveToken($heroId, $forestHex);

        $this->game->machine->push("completeQuest", $color);
        $this->game->machine->dispatchAll();

        $this->assertOperation("completeQuest");
        $this->assertValidTarget($darts);
        $this->respond($darts);

        // Chained spendAction(actionPractice) is a single-confirm op — accept it.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($darts),
            "Throwing Darts should land on tableau after completeQuest in forest"
        );

        $hero = $this->game->getHero($color);
        $this->assertContains(
            "actionPractice",
            $hero->getActionsTaken(),
            "Practice action should be marked as taken after spendAction(actionPractice)"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Bloodline Crystal should surface as the new deck-top");
    }

    /**
     * Bloodline Crystal (card_equip_2_25): quest_on= (empty — player-initiated),
     * quest_r=in(TempleRuins):2discardEvent:gainEquip.
     *
     * Player invokes the top-bar `completeQuest` free action while Bloodline Crystal
     * is on top of deck_equip_<color> AND the hero stands on the Temple Ruins hex
     * AND the hand has at least 2 event cards. The chain pays the cost by
     * discarding 2 event cards (one prompt per discardEvent invocation), then runs
     * gainEquip, which moves the card to tableau and reveals the next deck-top.
     */
    public function testBloodlineCrystalLandsOnTableauAfterDiscarding2InTempleRuins(): void {
        $this->setupGame([2]); // Solo Alva — Bloodline Crystal lives in Alva's deck
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $crystal = "card_equip_2_25";
        $nextCard = "card_equip_2_22"; // Belt of Youth — surfaces after Crystal is claimed
        $this->seedDeck("deck_equip_$color", [$crystal, $nextCard]);
        $this->assertEquals($crystal, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Two distinct Alva event cards (Mastery + Rest) — discardEvent will prompt
        // for the first, then auto-resolve the second since only one card remains.
        $this->clearHand($color);
        $cardA = "card_event_2_27_1"; // Mastery
        $cardB = "card_event_2_31_1"; // Rest
        $this->seedHand($cardA, $color);
        $this->seedHand($cardB, $color);

        // Place hero on Temple Ruins hex so the in(TempleRuins) gate passes.
        $this->game->tokens->moveToken($heroId, "hex_12_4");

        $this->game->machine->push("completeQuest", $color);
        $this->game->machine->dispatchAll();

        $this->assertOperation("completeQuest");
        $this->assertValidTarget($crystal);
        $this->respond($crystal);

        // First discardEvent: 2 cards in hand, player picks which to discard.
        // After resolve, Op_discardEvent re-queues `1discardEvent` for the next pick;
        // with only $cardB left in hand the single-target prompt auto-resolves, so
        // no second user response is needed.
        $this->assertOperation("discardEvent");
        $this->respond($cardA);

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($crystal),
            "Bloodline Crystal should land on tableau after discarding 2 cards in Temple Ruins"
        );
        $this->assertEquals("discard_$color", $this->tokenLocation($cardA), "First discarded card should be in discard pile");
        $this->assertEquals("discard_$color", $this->tokenLocation($cardB), "Second discarded card should be in discard pile");
        $this->assertEquals(
            0,
            count($this->game->tokens->getTokensOfTypeInLocation("card_event", "hand_$color")),
            "Hand should have 0 event cards left"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Belt of Youth should surface as the new deck-top");
    }

    /**
     * Alva's Bracers (card_equip_2_23): quest_on= (empty — player-initiated),
     * quest_r=in(road):5spendXp:gainEquip.
     *
     * Player invokes the top-bar `completeQuest` free action while Alva's Bracers
     * is on top of deck_equip_<color> AND the hero stands on a road hex. The chain
     * pays 5 XP (yellow crystals from tableau) then runs gainEquip, which moves
     * the card to tableau and reveals the next deck-top.
     *
     * `in(road)` predicate matches hexes with the road=1 flag in map_material.csv
     * (see Op_in.php — checks $game->getRulesFor($hex, "road", 0) == 1).
     */
    public function testAlvaBracersLandsOnTableauAfterCompleteQuestPays5XpOnRoad(): void {
        $this->setupGame([2]); // Solo Alva — Alva's Bracers lives in Alva's deck
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $bracers = "card_equip_2_23";
        $nextCard = "card_equip_2_22"; // Belt of Youth — surfaces after Bracers is claimed
        $this->seedDeck("deck_equip_$color", [$bracers, $nextCard]);
        $this->assertEquals($bracers, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Place hero on a road hex so the in(road) gate passes.
        // hex_7_8 is plains with road=1 in map_material.csv.
        $this->game->tokens->moveToken($heroId, "hex_7_8");

        // Seed Alva with 5 yellow crystals (XP) on tableau so the 5spendXp cost can be paid.
        $this->game->effect_moveCrystals($heroId, "yellow", 5, "tableau_$color", ["message" => ""]);
        $xpBefore = $this->countTokens("crystal_yellow", "tableau_$color");
        $this->assertGreaterThanOrEqual(5, $xpBefore, "Need at least 5 XP on tableau to pay the quest cost");

        $this->game->machine->push("completeQuest", $color);
        $this->game->machine->dispatchAll();

        // completeQuest prompts for the deck-top card; pick Alva's Bracers.
        $this->assertOperation("completeQuest");
        $this->assertValidTarget($bracers);
        $this->respond($bracers);

        // Chained 5spendXp is a single-confirm op (count-prefixed) — accept it.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($bracers),
            "Alva's Bracers should land on tableau after completeQuest pays 5 XP on a road"
        );

        $this->assertEquals(
            $xpBefore - 5,
            $this->countTokens("crystal_yellow", "tableau_$color"),
            "5 yellow crystals should have been spent paying the quest cost"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Belt of Youth should surface as the new deck-top");
    }

    /**
     * Quiver (card_equip_2_18): quest_on=TMonsterKilled,
     * quest_r=killed('rank>=3'):?(blockXp:gainEquip).
     *
     * Same shape as Bjorn's Quiver: rank-3 kill pops a yes/no prompt to claim.
     */
    public function testQuiverClaimsItselfOnRank3KillWhenAccepted(): void {
        $this->setupGame([2]); // Solo Alva
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $quiver = "card_equip_2_18";
        $nextCard = "card_equip_2_17"; // Throwing Darts
        $this->seedDeck("deck_equip_$color", [$quiver, $nextCard]);

        $heroHex = "hex_11_8";
        $trollHex = "hex_12_8";
        $this->game->tokens->moveToken($heroId, $heroHex);
        $this->game->getMonster("monster_troll_1")->moveTo($trollHex, "");

        $xpBefore = $this->countXp();

        $this->game->machine->push("dealDamage", $color, ["target" => $trollHex, "count" => 7]);
        $this->game->machine->dispatchAll();

        $this->confirmCardEffect();
        $this->game->machine->dispatchAll();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_troll_1"));
        $this->assertEquals("tableau_$color", $this->tokenLocation($quiver), "Quiver should land on tableau on rank-3 kill");
        $this->assertEquals($xpBefore, $this->countXp(), "blockXp should suppress the troll's XP reward");

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Throwing Darts should surface as the new deck-top");
    }

    public function testQuiverDeclinedKeepsXp(): void {
        $this->setupGame([2]);
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $quiver = "card_equip_2_18";
        $nextCard = "card_equip_2_17";
        $this->seedDeck("deck_equip_$color", [$quiver, $nextCard]);

        $this->game->tokens->moveToken($heroId, "hex_11_8");
        $this->game->getMonster("monster_troll_1")->moveTo("hex_12_8", "");

        $baseXp = $this->game->getMonster("monster_troll_1")->getXpReward();
        $xpBefore = $this->countXp();

        $this->game->machine->push("dealDamage", $color, ["target" => "hex_12_8", "count" => 7]);
        $this->game->machine->dispatchAll();

        $this->skip();
        $this->game->machine->dispatchAll();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_troll_1"));
        $this->assertEquals("deck_equip_$color", $this->tokenLocation($quiver), "Quiver stays in deck on decline");
        $this->assertEquals($xpBefore + $baseXp, $this->countXp(), "XP awarded when player declines the claim");
    }

    public function testQuiverStaysInDeckWhenKillIsRank1(): void {
        $this->setupGame([2]);
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $quiver = "card_equip_2_18";
        $nextCard = "card_equip_2_17";
        $this->seedDeck("deck_equip_$color", [$quiver, $nextCard]);

        $this->game->tokens->moveToken($heroId, "hex_11_8");
        $this->game->getMonster("monster_goblin_1")->moveTo("hex_12_8", "");

        $xpBefore = $this->countXp();

        $this->game->machine->push("dealDamage", $color, ["target" => "hex_12_8", "count" => 2]);
        $this->game->machine->dispatchAll();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_goblin_1"));
        $this->assertEquals("deck_equip_$color", $this->tokenLocation($quiver), "Quiver should stay in deck — goblin is rank 1");
        $this->assertEquals($xpBefore + 1, $this->countXp(), "Goblin's 1 XP awarded normally when chain voids");
    }
}
