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

        $this->assertEquals("tableau_$color", $this->tokenLocation($legGuards), "Leg Guards should land on tableau after completeQuest");

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

    /**
     * Healing Potion (card_equip_3_16): quest_on= (empty — player-initiated),
     * quest_r=in(WitchCabin):spendAction(actionMend):gainEquip.
     *
     * Player invokes the top-bar `completeQuest` free action while Healing Potion
     * is on top of deck_equip_<color> AND the hero stands on the Witch Cabin hex
     * (hex_11_11, named location, forest terrain). The chain spends a Mend action
     * then runs gainEquip, landing the card on the tableau and revealing the next
     * deck-top.
     */
    public function testHealingPotionLandsOnTableauAfterCompleteQuestOnWitchCabin(): void {
        $this->setupGame([3]); // Solo Embla — Healing Potion lives in Embla's deck
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $potion = "card_equip_3_16";
        $nextCard = "card_equip_3_17"; // Heels — surfaces after Healing Potion is claimed
        $this->seedDeck("deck_equip_$color", [$potion, $nextCard]);
        $this->assertEquals($potion, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Place Embla on the Witch Cabin hex so the in(WitchCabin) gate passes.
        $witchCabinHex = "hex_11_11";
        $this->game->tokens->moveToken($heroId, $witchCabinHex);

        $this->game->machine->push("completeQuest", $color);
        $this->game->machine->dispatchAll();

        $this->assertOperation("completeQuest");
        $this->assertValidTarget($potion);
        $this->respond($potion);

        // Chained spendAction(actionMend) is a single-confirm op — accept it.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($potion),
            "Healing Potion should land on tableau after completeQuest on Witch Cabin"
        );

        $hero = $this->game->getHero($color);
        $this->assertContains(
            "actionMend",
            $hero->getActionsTaken(),
            "Mend action should be marked as taken after spendAction(actionMend)"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Heels should surface as the new deck-top");
    }

    /**
     * Warrior Shield (card_equip_3_24): quest_on= (empty — player-initiated),
     * quest_r=spendAction(actionAttack):gainEquip.
     *
     * Mirrors the Leg Guards flow but spends an attack action instead of focus.
     * Embla must be parked outside Grimheim — heroes can't attack from inside it,
     * which would make spendAction(actionAttack) ungated-fail mid-quest.
     */
    public function testWarriorShieldLandsOnTableauAfterCompleteQuest(): void {
        $this->setupGame([3]); // Solo Embla — Warrior Shield lives in Embla's deck
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        // Park Embla on a plains hex outside Grimheim so attack actions are legal.
        $this->game->tokens->moveToken($heroId, "hex_7_9");

        $warriorShield = "card_equip_3_24";
        $nextCard = "card_equip_3_16"; // Healing Potion — surfaces after Warrior Shield is claimed
        $this->seedDeck("deck_equip_$color", [$warriorShield, $nextCard]);
        $this->assertEquals($warriorShield, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        $this->game->machine->push("completeQuest", $color);
        $this->game->machine->dispatchAll();

        // completeQuest prompts for the deck-top card; pick Warrior Shield.
        $this->assertOperation("completeQuest");
        $this->assertValidTarget($warriorShield);
        $this->respond($warriorShield);

        // Chained spendAction(actionAttack) is a single-confirm op — accept it.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($warriorShield),
            "Warrior Shield should land on tableau after completeQuest"
        );

        $hero = $this->game->getHero($color);
        $this->assertContains(
            "actionAttack",
            $hero->getActionsTaken(),
            "Attack action should be marked as taken after spendAction(actionAttack)"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Healing Potion should surface as the new deck-top");
    }

    /**
     * Wildfire Blade (card_equip_3_21): quest_on= (empty — player-initiated),
     * quest_r=in(SpewingMountain):spendAction(actionMend):gainEquip.
     *
     * Player invokes the top-bar `completeQuest` free action while Wildfire Blade
     * is on top of deck_equip_<color> AND the hero stands on a Spewing Mountain
     * hex (hex_14_2 — plains terrain inside the Spewing Mountain named area; the
     * hex itself must be standable, so we pick a plains tile rather than a
     * mountain one). The chain spends a Mend action then runs gainEquip, landing
     * the card on the tableau and revealing the next deck-top.
     */
    public function testWildfireBladeLandsOnTableauAfterCompleteQuestOnSpewingMountain(): void {
        $this->setupGame([3]); // Solo Embla — Wildfire Blade lives in Embla's deck
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $wildfireBlade = "card_equip_3_21";
        $nextCard = "card_equip_3_22"; // Raven's Claw — surfaces after Wildfire Blade is claimed
        $this->seedDeck("deck_equip_$color", [$wildfireBlade, $nextCard]);
        $this->assertEquals($wildfireBlade, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Place Embla on a Spewing Mountain plains hex so the in(SpewingMountain) gate passes.
        // (hex id is hex_{x}_{y}; the named area Spewing Mountain has plains tiles
        // hex_2_14, hex_1_15, hex_4_16 — picking the first.)
        $spewingMountainHex = "hex_2_14";
        $this->game->tokens->moveToken($heroId, $spewingMountainHex);

        $this->game->machine->push("completeQuest", $color);
        $this->game->machine->dispatchAll();

        $this->assertOperation("completeQuest");
        $this->assertValidTarget($wildfireBlade);
        $this->respond($wildfireBlade);

        // Chained spendAction(actionMend) is a single-confirm op — accept it.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($wildfireBlade),
            "Wildfire Blade should land on tableau after completeQuest on Spewing Mountain"
        );

        $hero = $this->game->getHero($color);
        $this->assertContains(
            "actionMend",
            $hero->getActionsTaken(),
            "Mend action should be marked as taken after spendAction(actionMend)"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Raven's Claw should surface as the new deck-top");
    }

    /**
     * Heels (card_equip_3_17): quest_on= (empty — player-initiated),
     * quest_r=in(WitchCabin):spendAction(actionMend):2discardEvent:gainEquip.
     *
     * Resolved as a single paid claim per QUESTS.md §6 Q7. Player invokes the
     * top-bar `completeQuest` free action while Heels is on top of
     * deck_equip_<color> AND the hero stands on the Witch Cabin hex AND the
     * hand has at least 2 event cards. The chain spends a Mend action,
     * discards 2 event cards (one prompt per discardEvent invocation; the
     * second auto-resolves when only one card remains), then runs gainEquip.
     */
    public function testHeelsLandsOnTableauAfterMendAndDiscarding2InWitchCabin(): void {
        $this->setupGame([3]); // Solo Embla — Heels lives in Embla's deck
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $heels = "card_equip_3_17";
        $nextCard = "card_equip_3_20"; // Helmet — surfaces after Heels is claimed
        $this->seedDeck("deck_equip_$color", [$heels, $nextCard]);
        $this->assertEquals($heels, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Two distinct Embla event cards (Maneuver + Rest) — discardEvent will
        // prompt for the first, then auto-resolve the second since only one
        // card remains.
        $this->clearHand($color);
        $cardA = "card_event_3_27_1"; // Maneuver
        $cardB = "card_event_3_35_1"; // Rest
        $this->seedHand($cardA, $color);
        $this->seedHand($cardB, $color);

        // Place Embla on the Witch Cabin hex so the in(WitchCabin) gate passes.
        $witchCabinHex = "hex_11_11";
        $this->game->tokens->moveToken($heroId, $witchCabinHex);

        $this->game->machine->push("completeQuest", $color);
        $this->game->machine->dispatchAll();

        $this->assertOperation("completeQuest");
        $this->assertValidTarget($heels);
        $this->respond($heels);

        // Chained spendAction(actionMend) is a single-confirm op — accept it.
        $this->confirmCardEffect();

        // First discardEvent: 2 cards in hand, player picks which to discard.
        // After resolve, Op_discardEvent re-queues `1discardEvent` for the next
        // pick; with only $cardB left in hand the single-target prompt
        // auto-resolves, so no second user response is needed.
        $this->assertOperation("discardEvent");
        $this->respond($cardA);

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($heels),
            "Heels should land on tableau after mend + discarding 2 cards in Witch Cabin"
        );

        $hero = $this->game->getHero($color);
        $this->assertContains(
            "actionMend",
            $hero->getActionsTaken(),
            "Mend action should be marked as taken after spendAction(actionMend)"
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
        $this->assertEquals($nextCard, $newTop["key"], "Helmet should surface as the new deck-top");
    }

    /**
     * Raven's Claw (card_equip_3_22): quest_on=TStep,
     * quest_r=in(forest):gainTracker,check('countTracker>=10'):gainEquip.
     *
     * Counter quest mirroring Belt of Youth (Alva's Q1 canary), only with target=10
     * instead of 8. After 10 TStep firings on forest hexes, the card leaves
     * deck_equip_<color> and lands on tableau_<color>, with all progress crystals
     * swept to supply.
     *
     * Bonus: a single non-forest step (hex_11_2 → hex_12_2 plains) is performed
     * first to verify the in(forest) gate suppresses the tracker tick when the
     * destination terrain isn't forest.
     *
     * Bypasses the actionMove turn flow and pushes single-step `move` ops directly.
     * Hero oscillates between two adjacent forest hexes — simpler than scripting a
     * multi-hex forest path, and equally valid for the quest engine.
     */
    public function testRavensClawLandsOnTableauAfter10ForestSteps(): void {
        $this->setupGame([3]); // Solo Embla — Raven's Claw lives in Embla's deck
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $ravensClaw = "card_equip_3_22";
        $nextCard = "card_equip_3_16"; // Healing Potion — surfaces after Raven's Claw is claimed
        $this->seedDeck("deck_equip_$color", [$ravensClaw, $nextCard]);
        $this->assertEquals($ravensClaw, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Two adjacent forest hexes (both in the DarkForest cluster per Material.php).
        $hexA = "hex_10_2"; // forest
        $hexB = "hex_11_2"; // forest
        $hexPlains = "hex_12_2"; // plains, adjacent to hex_11_2 — the negative-case hex
        $this->game->tokens->moveToken($heroId, $hexB);

        // Bonus: one non-forest step first. From hex_11_2 (forest) to hex_12_2 (plains).
        // TStep fires at the destination — in(forest) gate fails — no crystal added.
        $this->game->machine->push("move", $color, ["target" => $hexPlains, "reason" => "Op_actionMove"]);
        $this->game->machine->dispatchAll();
        $this->assertEquals(
            0,
            $this->countTokens("crystal_red", $ravensClaw),
            "A non-forest step must NOT add a tracker crystal (in(forest) gate suppresses the tick)"
        );
        $this->assertEquals(
            "deck_equip_$color",
            $this->tokenLocation($ravensClaw),
            "Raven's Claw should still be on the deck after a non-forest step"
        );

        // Move hero back onto a forest hex so the first scripted forest move starts cleanly.
        $this->game->tokens->moveToken($heroId, $hexA);

        // 10 single-step moves, oscillating A → B → A → B …
        // Each move emits TStep at the destination (forest), so quest_r runs:
        // in(forest) passes → gainTracker → check(countTracker>=10) → gainEquip.
        // push() interrupts on top of the active turn op. LIFO order means we
        // push in reverse so the dispatch sequence is i=0, 1, 2, … 9
        // (alternating B, A, B, A, …; hero starts at A so first move is real).
        for ($i = 9; $i >= 0; $i--) {
            $target = $i % 2 === 0 ? $hexB : $hexA;
            $this->game->machine->push("move", $color, ["target" => $target, "reason" => "Op_actionMove"]);
        }

        $this->game->machine->dispatchAll();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($ravensClaw),
            "Raven's Claw should land on tableau after 10 forest TSteps"
        );
        $this->assertEquals(0, $this->countTokens("crystal_red", $ravensClaw), "Op_gainEquip should sweep tracker crystals back to supply");

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Healing Potion should surface as the new deck-top");
    }

    /**
     * Tailored Boots (card_equip_3_18): quest_on= (empty — player-initiated),
     * quest_r=in(Grimheim):2spendXp:gainEquip.
     *
     * Player invokes the top-bar `completeQuest` free action while Tailored Boots
     * is on top of deck_equip_<color> AND the hero stands on a Grimheim hex
     * (hex_9_9). The chain pays 2 XP (yellow crystals from tableau) then runs
     * gainEquip, which moves the card to tableau and reveals the next deck-top.
     *
     * Identical shape to Boldur's Dwarf Helm (in(TempleRuins):2spendXp:gainEquip).
     */
    public function testTailoredBootsLandsOnTableauAfterCompleteQuestPays2XpInGrimheim(): void {
        $this->setupGame([3]); // Solo Embla — Tailored Boots lives in Embla's deck
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $tailoredBoots = "card_equip_3_18";
        $nextCard = "card_equip_3_16"; // Healing Potion — surfaces after Tailored Boots is claimed
        $this->seedDeck("deck_equip_$color", [$tailoredBoots, $nextCard]);
        $this->assertEquals($tailoredBoots, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Place Embla on a Grimheim hex so the in(Grimheim) gate passes.
        $this->game->tokens->moveToken($heroId, "hex_9_9");

        // Seed Embla with 2 yellow crystals (XP) on tableau so the 2spendXp cost can be paid.
        $this->game->effect_moveCrystals($heroId, "yellow", 2, "tableau_$color", ["message" => ""]);
        $xpBefore = $this->countTokens("crystal_yellow", "tableau_$color");
        $this->assertGreaterThanOrEqual(2, $xpBefore, "Need at least 2 XP on tableau to pay the quest cost");

        $this->game->machine->push("completeQuest", $color);
        $this->game->machine->dispatchAll();

        // completeQuest prompts for the deck-top card; pick Tailored Boots.
        $this->assertOperation("completeQuest");
        $this->assertValidTarget($tailoredBoots);
        $this->respond($tailoredBoots);

        // Chained 2spendXp is a single-confirm op (count-prefixed) — accept it.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($tailoredBoots),
            "Tailored Boots should land on tableau after completeQuest pays 2 XP in Grimheim"
        );

        $this->assertEquals(
            $xpBefore - 2,
            $this->countTokens("crystal_yellow", "tableau_$color"),
            "2 yellow crystals should have been spent paying the quest cost"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Healing Potion should surface as the new deck-top");
    }

    /**
     * Custom Armor (card_equip_3_25): quest_on= (empty — player-initiated),
     * quest_r=4spendXp:gainEquip.
     *
     * Player invokes the top-bar `completeQuest` free action while Custom Armor
     * is on top of deck_equip_<color>. The chain pays 4 XP (yellow crystals from
     * tableau) then runs gainEquip, which moves the card to tableau and reveals
     * the next deck-top.
     *
     * Identical shape to Boldur's Mining Equipment (3spendXp:gainEquip), only the
     * cost is 4 instead of 3 and Embla owns the deck. No location gate — pay-anywhere.
     */
    public function testCustomArmorLandsOnTableauAfterCompleteQuestPays4Xp(): void {
        $this->setupGame([3]); // Solo Embla — Custom Armor lives in Embla's deck
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $customArmor = "card_equip_3_25";
        $nextCard = "card_equip_3_16"; // Healing Potion — surfaces after Custom Armor is claimed
        $this->seedDeck("deck_equip_$color", [$customArmor, $nextCard]);
        $this->assertEquals($customArmor, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Seed Embla with 4 yellow crystals (XP) on tableau so the 4spendXp cost can be paid.
        $this->game->effect_moveCrystals($heroId, "yellow", 4, "tableau_$color", ["message" => ""]);
        $xpBefore = $this->countTokens("crystal_yellow", "tableau_$color");
        $this->assertGreaterThanOrEqual(4, $xpBefore, "Need at least 4 XP on tableau to pay the quest cost");

        $this->game->machine->push("completeQuest", $color);
        $this->game->machine->dispatchAll();

        // completeQuest prompts for the deck-top card; pick Custom Armor.
        $this->assertOperation("completeQuest");
        $this->assertValidTarget($customArmor);
        $this->respond($customArmor);

        // Chained 4spendXp is a single-confirm op (count-prefixed) — accept it.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($customArmor),
            "Custom Armor should land on tableau after completeQuest pays 4 XP"
        );

        $this->assertEquals(
            $xpBefore - 4,
            $this->countTokens("crystal_yellow", "tableau_$color"),
            "4 yellow crystals should have been spent paying the quest cost"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Healing Potion should surface as the new deck-top");
    }

    /**
     * Blade Decorations (card_equip_3_19): quest_on= (empty — player-initiated),
     * quest_r=in(Grimheim):2spendXp:gainEquip.
     *
     * Migrated from the legacy `r` field (where it was unreachable — deck-top equip
     * cards never get useCard-prompted) to `quest_r` so it now flows through the
     * standard completeQuest path. Identical shape to Tailored Boots, only the card
     * is different.
     */
    public function testBladeDecorationsLandsOnTableauAfterCompleteQuestPays2XpInGrimheim(): void {
        $this->setupGame([3]); // Solo Embla — Blade Decorations lives in Embla's deck
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $bladeDecorations = "card_equip_3_19";
        $nextCard = "card_equip_3_16"; // Healing Potion — surfaces after Blade Decorations is claimed
        $this->seedDeck("deck_equip_$color", [$bladeDecorations, $nextCard]);
        $this->assertEquals($bladeDecorations, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Place Embla on a Grimheim hex so the in(Grimheim) gate passes.
        $this->game->tokens->moveToken($heroId, "hex_9_9");

        // Seed Embla with 2 yellow crystals (XP) on tableau so the 2spendXp cost can be paid.
        $this->game->effect_moveCrystals($heroId, "yellow", 2, "tableau_$color", ["message" => ""]);
        $xpBefore = $this->countTokens("crystal_yellow", "tableau_$color");
        $this->assertGreaterThanOrEqual(2, $xpBefore, "Need at least 2 XP on tableau to pay the quest cost");

        $this->game->machine->push("completeQuest", $color);
        $this->game->machine->dispatchAll();

        // completeQuest prompts for the deck-top card; pick Blade Decorations.
        $this->assertOperation("completeQuest");
        $this->assertValidTarget($bladeDecorations);
        $this->respond($bladeDecorations);

        // Chained 2spendXp is a single-confirm op (count-prefixed) — accept it.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($bladeDecorations),
            "Blade Decorations should land on tableau after completeQuest pays 2 XP in Grimheim"
        );

        $this->assertEquals(
            $xpBefore - 2,
            $this->countTokens("crystal_yellow", "tableau_$color"),
            "2 yellow crystals should have been spent paying the quest cost"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Healing Potion should surface as the new deck-top");
    }
}
