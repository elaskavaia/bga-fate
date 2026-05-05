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
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([2]); // Solo Alva
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());
        $this->clearMonstersFromMap();
    }

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
        $color = $this->getActivePlayerColor();

        $belt = "card_equip_2_22";
        $nextCard = "card_equip_2_25"; // Bloodline Crystal — surfaces after Belt is claimed
        $this->seedDeck("deck_equip_$color", [$belt, $nextCard]);
        $this->assertEquals($belt, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Two adjacent forest hexes (both in the DarkForest cluster).
        $hexA = "hex_10_2";
        $hexB = "hex_11_2";
        $this->game->tokens->moveToken($this->heroId, $hexA);

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
        $color = $this->getActivePlayerColor();

        $darts = "card_equip_2_17";
        $nextCard = "card_equip_2_25"; // Bloodline Crystal — surfaces after Darts is claimed
        $this->seedDeck("deck_equip_$color", [$darts, $nextCard]);
        $this->assertEquals($darts, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Place hero on a forest hex so the in(forest) gate passes.
        $forestHex = "hex_2_8";
        $this->game->tokens->moveToken($this->heroId, $forestHex);

        $this->respond("completeQuest");

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
        $color = $this->getActivePlayerColor();

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
        $this->game->tokens->moveToken($this->heroId, "hex_12_4");

        $this->respond("completeQuest");

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
        $color = $this->getActivePlayerColor();

        $bracers = "card_equip_2_23";
        $nextCard = "card_equip_2_22"; // Belt of Youth — surfaces after Bracers is claimed
        $this->seedDeck("deck_equip_$color", [$bracers, $nextCard]);
        $this->assertEquals($bracers, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Place hero on a road hex so the in(road) gate passes.
        // hex_7_8 is plains with road=1 in map_material.csv.
        $this->game->tokens->moveToken($this->heroId, "hex_7_8");

        // Seed Alva with 5 yellow crystals (XP) on tableau so the 5spendXp cost can be paid.
        $this->game->effect_moveCrystals($this->heroId, "yellow", 5, "tableau_$color", ["message" => ""]);
        $xpBefore = $this->countTokens("crystal_yellow", "tableau_$color");
        $this->assertGreaterThanOrEqual(5, $xpBefore, "Need at least 5 XP on tableau to pay the quest cost");

        $this->respond("completeQuest");

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
     * Tiara (card_equip_2_16): quest_on= (empty — player-initiated),
     * quest_r=in(DarkForest):gainEquip.
     *
     * Player invokes the top-bar `completeQuest` free action while Tiara is on top
     * of deck_equip_<color> AND the hero stands on a Dark Forest hex. No cost — the
     * gate alone gates the claim. On success, Tiara's onCardEnter seeds 6 yellow
     * crystals on the card.
     */
    public function testTiaraLandsOnTableauAfterCompleteQuestInDarkForest(): void {
        $color = $this->getActivePlayerColor();

        $tiara = "card_equip_2_16";
        $nextCard = "card_equip_2_22"; // Belt of Youth — surfaces after Tiara is claimed
        $this->seedDeck("deck_equip_$color", [$tiara, $nextCard]);
        $this->assertEquals($tiara, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Place hero on a Dark Forest hex so the in(DarkForest) gate passes.
        $this->game->tokens->moveToken($this->heroId, "hex_9_1");

        $this->respond("completeQuest");

        $this->assertOperation("completeQuest");
        $this->assertValidTarget($tiara);
        $this->respond($tiara);

        // gainEquip may auto-resolve or surface a single-confirm; either way this is a no-op or a confirm.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($tiara),
            "Tiara should land on tableau after completeQuest in Dark Forest"
        );

        // onCardEnter seeds 6 yellow crystals on the card.
        $this->assertEquals(6, $this->countTokens("crystal_yellow", $tiara), "Tiara should seed 6 [XP] on enter");

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Belt of Youth should surface as the new deck-top");
    }

    /**
     * Elven Arrows (card_equip_2_24): quest_on= (empty — player-initiated),
     * quest_r=in(TrollCaves):spawn(troll):gainEquip.
     *
     * Player invokes the top-bar `completeQuest` free action while Elven Arrows is on
     * top of deck_equip_<color> AND the hero stands in the Troll Caves. The "cost" is
     * a spawn — Op_spawn pulls a troll from supply_monster onto the first free
     * adjacent hex (clockwise) — and then gainEquip moves the card to tableau.
     */
    public function testElvenArrowsLandsOnTableauAndSpawnsTrollOnCompleteQuestInTrollCaves(): void {
        $color = $this->getActivePlayerColor();

        $arrows = "card_equip_2_24";
        $nextCard = "card_equip_2_22"; // Belt of Youth — surfaces after Arrows is claimed
        $this->seedDeck("deck_equip_$color", [$arrows, $nextCard]);
        $this->assertEquals($arrows, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // hex_6_6 is mountain/TrollCaves — moveToken bypasses heroes-can't-enter-mountain.
        $heroHex = "hex_6_6";
        $this->game->tokens->moveToken($this->heroId, $heroHex);
        $this->game->hexMap->invalidateOccupancy();

        $trollsBefore = count($this->game->tokens->getTokensOfTypeInLocation("monster_troll", "supply_monster"));
        $this->assertGreaterThan(0, $trollsBefore, "Need at least one troll in supply for spawn to work");

        $this->respond("completeQuest");

        $this->assertOperation("completeQuest");
        $this->assertValidTarget($arrows);
        $this->respond($arrows);

        // spawn(troll) auto-resolves; gainEquip may surface a single-confirm or auto-resolve.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($arrows),
            "Elven Arrows should land on tableau after completeQuest in Troll Caves"
        );

        $trollsAfter = count($this->game->tokens->getTokensOfTypeInLocation("monster_troll", "supply_monster"));
        $this->assertEquals($trollsBefore - 1, $trollsAfter, "One troll should leave supply (spawned next to hero)");

        // The spawned troll should be on a hex adjacent to the hero.
        $adjHexes = $this->game->hexMap->getAdjacentHexes($heroHex);
        $spawnedTroll = null;
        foreach ($adjHexes as $hex) {
            $charId = $this->game->hexMap->getCharacterOnHex($hex);
            if ($charId !== null && str_starts_with($charId, "monster_troll")) {
                $spawnedTroll = $charId;
                break;
            }
        }
        $this->assertNotNull($spawnedTroll, "A troll should have spawned on a hex adjacent to the hero");

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
        $color = $this->getActivePlayerColor();

        $quiver = "card_equip_2_18";
        $nextCard = "card_equip_2_17"; // Throwing Darts
        $this->seedDeck("deck_equip_$color", [$quiver, $nextCard]);

        $heroHex = "hex_11_8";
        $trollHex = "hex_12_8";
        $this->game->tokens->moveToken($this->heroId, $heroHex);
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
        $color = $this->getActivePlayerColor();

        $quiver = "card_equip_2_18";
        $nextCard = "card_equip_2_17";
        $this->seedDeck("deck_equip_$color", [$quiver, $nextCard]);

        $this->game->tokens->moveToken($this->heroId, "hex_11_8");
        $this->game->getMonster("monster_troll_1")->moveTo("hex_12_8", "");

        $baseXp = $this->game->getMonster("monster_troll_1")->getXpReward();
        $xpBefore = $this->countXp();

        $this->game->machine->push("dealDamage", $color, ["target" => "hex_12_8", "count" => 7]);
        $this->game->machine->dispatchAll();

        $this->skip();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_troll_1"));
        $this->assertEquals("deck_equip_$color", $this->tokenLocation($quiver), "Quiver stays in deck on decline");
        $this->assertEquals($xpBefore + $baseXp, $this->countXp(), "XP awarded when player declines the claim");
    }

    public function testQuiverStaysInDeckWhenKillIsRank1(): void {
        $color = $this->getActivePlayerColor();

        $quiver = "card_equip_2_18";
        $nextCard = "card_equip_2_17";
        $this->seedDeck("deck_equip_$color", [$quiver, $nextCard]);

        $this->game->tokens->moveToken($this->heroId, "hex_11_8");
        $this->game->getMonster("monster_goblin_1")->moveTo("hex_12_8", "");

        $xpBefore = $this->countXp();

        $this->game->machine->push("dealDamage", $color, ["target" => "hex_12_8", "count" => 2]);
        $this->game->machine->dispatchAll();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_goblin_1"));
        $this->assertEquals("deck_equip_$color", $this->tokenLocation($quiver), "Quiver should stay in deck — goblin is rank 1");
        $this->assertEquals($xpBefore + 1, $this->countXp(), "Goblin's 1 XP awarded normally when chain voids");
    }

    /**
     * Elven Blade (card_equip_2_21): quest_on=TMonsterKilled,
     * quest_r=killed(adj):gainTracker,check('countTracker>=3'):gainEquip.
     *
     * Trigger-driven counter quest. Each kill of a monster adjacent to Alva
     * bumps the tracker; the third kill flips check() and runs gainEquip.
     * Uses the existing `adj` Math term (Game::evaluateTerm), no new code.
     */
    public function testElvenBladeLandsOnTableauAfter3AdjacentKills(): void {
        $color = $this->getActivePlayerColor();

        $blade = "card_equip_2_21";
        $nextCard = "card_equip_2_22"; // Belt of Youth — surfaces after Blade is claimed
        $this->seedDeck("deck_equip_$color", [$blade, $nextCard]);

        $heroHex = "hex_11_8";
        $this->game->tokens->moveToken($this->heroId, $heroHex);

        // Place 3 goblins on the first 3 free adjacent hexes.
        $goblins = ["monster_goblin_1", "monster_goblin_2", "monster_goblin_3"];
        $placedHexes = [];
        $i = 0;
        foreach ($this->game->hexMap->getAdjacentHexes($heroHex) as $hex) {
            if ($this->game->hexMap->getCharacterOnHex($hex) !== null) {
                continue;
            }
            $this->game->getMonster($goblins[$i])->moveTo($hex, "");
            $placedHexes[] = $hex;
            if (++$i >= 3) {
                break;
            }
        }
        $this->assertCount(3, $placedHexes, "Need 3 free adjacent hexes for the test");

        // Kill each goblin in sequence — each TMonsterKilled fires the chain:
        // killed(adj) passes (monster on adjacent hex) → gainTracker → check.
        foreach ($placedHexes as $hex) {
            $this->game->machine->push("dealDamage", $color, ["target" => $hex, "count" => 2]);
            $this->game->machine->dispatchAll();
        }

        $this->assertEquals("tableau_$color", $this->tokenLocation($blade), "Elven Blade should land on tableau after 3 adjacent kills");
        $this->assertEquals(0, $this->countTokens("crystal_red", $blade), "gainEquip should sweep tracker crystals back to supply");

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Belt of Youth should surface as the new deck-top");
    }

    /**
     * Negative path: a non-adjacent kill (range-2 attack) should NOT bump the
     * tracker — killed(adj) fails when the monster wasn't next to the hero.
     */
    public function testElvenBladeIgnoresNonAdjacentKills(): void {
        $color = $this->getActivePlayerColor();

        $blade = "card_equip_2_21";
        $nextCard = "card_equip_2_22";
        $this->seedDeck("deck_equip_$color", [$blade, $nextCard]);

        // Hero at hex_11_8; goblin two hexes away (non-adjacent).
        $this->game->tokens->moveToken($this->heroId, "hex_11_8");
        $farHex = "hex_13_8"; // distance 2 from hex_11_8
        $this->assertEquals(2, $this->game->hexMap->getMoveDistance("hex_11_8", $farHex), "Sanity: hex_13_8 is 2 away from hex_11_8");
        $this->game->getMonster("monster_goblin_1")->moveTo($farHex, "");

        $this->game->machine->push("dealDamage", $color, ["target" => $farHex, "count" => 2]);
        $this->game->machine->dispatchAll();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_goblin_1"));
        $this->assertEquals("deck_equip_$color", $this->tokenLocation($blade), "Elven Blade stays in deck — goblin wasn't adjacent");
        $this->assertEquals(0, $this->countTokens("crystal_red", $blade), "No tracker crystal added on non-adjacent kill");
    }

    /**
     * Windbite (card_equip_2_19): quest_on=TMonsterKilled,
     * quest_r=killed('range>=2'):gainTracker,check('countTracker>=4'):gainEquip.
     *
     * Trigger-driven counter quest. Each kill of a monster at distance 2+ from
     * Alva bumps the tracker; the fourth such kill flips check() and runs
     * gainEquip. Uses the new `range` Math term (Game::evaluateTerm).
     */
    public function testWindbiteLandsOnTableauAfter4RangedKills(): void {
        $color = $this->getActivePlayerColor();

        $windbite = "card_equip_2_19";
        $nextCard = "card_equip_2_22"; // Belt of Youth — surfaces after Windbite is claimed
        $this->seedDeck("deck_equip_$color", [$windbite, $nextCard]);

        $this->game->tokens->moveToken($this->heroId, "hex_11_8");
        $farHex = "hex_13_8";
        $this->assertEquals(2, $this->game->hexMap->getMoveDistance("hex_11_8", $farHex), "Sanity: hex_13_8 is range 2");

        // Kill 4 goblins in sequence, each one placed on the same range-2 hex.
        $goblins = ["monster_goblin_1", "monster_goblin_2", "monster_goblin_3", "monster_goblin_4"];
        foreach ($goblins as $g) {
            $this->game->getMonster($g)->moveTo($farHex, "");
            $this->game->machine->push("dealDamage", $color, ["target" => $farHex, "count" => 2]);
            $this->game->machine->dispatchAll();
        }

        $this->assertEquals("tableau_$color", $this->tokenLocation($windbite), "Windbite should land on tableau after 4 range-2+ kills");
        $this->assertEquals(0, $this->countTokens("crystal_red", $windbite), "gainEquip should sweep tracker crystals back to supply");

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Belt of Youth should surface as the new deck-top");
    }

    /**
     * Negative path: an adjacent kill (range 1) should NOT bump the tracker —
     * killed('range>=2') fails when the monster was next to the hero.
     */
    public function testWindbiteIgnoresAdjacentKills(): void {
        $color = $this->getActivePlayerColor();

        $windbite = "card_equip_2_19";
        $nextCard = "card_equip_2_22";
        $this->seedDeck("deck_equip_$color", [$windbite, $nextCard]);

        $this->game->tokens->moveToken($this->heroId, "hex_11_8");
        $this->game->getMonster("monster_goblin_1")->moveTo("hex_12_8", "");

        $this->game->machine->push("dealDamage", $color, ["target" => "hex_12_8", "count" => 2]);
        $this->game->machine->dispatchAll();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_goblin_1"));
        $this->assertEquals("deck_equip_$color", $this->tokenLocation($windbite), "Windbite stays in deck — kill was at range 1");
        $this->assertEquals(0, $this->countTokens("crystal_red", $windbite), "No tracker crystal added on adjacent kill");
    }

    /**
     * Singing Bow (card_equip_2_20): quest_on=TRoll,
     * quest_r=in(forest):counter(countDice):gainTracker,check('countTracker>=10'):gainEquip.
     *
     * Counter quest. Each TRoll while standing in a forest hex bumps the
     * tracker by the number of dice rolled (read from display_battle via the
     * new `countDice` Math term). Hits 10 cumulative dice → claim.
     */
    public function testSingingBowLandsOnTableauAfterRolling10DiceInForest(): void {
        $color = $this->getActivePlayerColor();

        $bow = "card_equip_2_20";
        $nextCard = "card_equip_2_22"; // Belt of Youth — surfaces after Bow is claimed
        $this->seedDeck("deck_equip_$color", [$bow, $nextCard]);

        // Hero on a forest hex so in(forest) gate passes.
        $this->game->tokens->moveToken($this->heroId, "hex_2_8");

        // Three simulated rolls: 4 + 4 + 2 = 10 dice cumulative → claim fires.
        $rolls = [4, 4, 2];
        foreach ($rolls as $diceCount) {
            $this->seedDiceOnDisplay($diceCount);
            $this->game->machine->push("trigger(TRoll)", $color);
            $this->game->machine->dispatchAll();
        }

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($bow),
            "Singing Bow should land on tableau after rolling 10 dice in forest"
        );
        $this->assertEquals(0, $this->countTokens("crystal_red", $bow), "gainEquip should sweep tracker crystals back to supply");

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Belt of Youth should surface as the new deck-top");
    }

    /**
     * Negative path: rolls outside a forest hex don't bump the tracker —
     * in(forest) voids the chain.
     */
    public function testSingingBowIgnoresRollsOutsideForest(): void {
        $color = $this->getActivePlayerColor();

        $bow = "card_equip_2_20";
        $nextCard = "card_equip_2_22";
        $this->seedDeck("deck_equip_$color", [$bow, $nextCard]);

        // hex_11_8 is plains — not forest.
        $this->game->tokens->moveToken($this->heroId, "hex_11_8");

        $this->seedDiceOnDisplay(10);
        $this->game->machine->push("trigger(TRoll)", $color);
        $this->game->machine->dispatchAll();

        $this->assertEquals("deck_equip_$color", $this->tokenLocation($bow), "Singing Bow stays in deck — hero wasn't in forest");
        $this->assertEquals(0, $this->countTokens("crystal_red", $bow), "No tracker crystal added when in(forest) voids");
    }

    /** Sweep display_battle and seed N fresh dice (face=1; doesn't matter for countDice). */
    private function seedDiceOnDisplay(int $count): void {
        foreach (array_keys($this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle")) as $id) {
            $this->game->tokens->moveToken($id, "limbo");
        }
        for ($i = 1; $i <= $count; $i++) {
            $this->game->tokens->moveToken("die_attack_$i", "display_battle", 1);
        }
    }
}
