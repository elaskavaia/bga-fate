<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * End-to-end integration tests for Boldur-specific quests.
 *
 * Each test scripts a scenario that exercises one of Boldur's quest cards
 * (defined in misc/card_equip_material.csv with quest_on / quest_r) end-to-end:
 * player invokes completeQuest (or trigger fires) → quest_r runs → progress
 * accumulates / equip lands on tableau.
 */
class Campaign_BoldurQuestTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]); // Solo Boldur
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());
        $this->clearMonstersFromMap();
    }

    /**
     * Mining Equipment (card_equip_4_17): quest_on= (empty — player-initiated),
     * quest_r=3spendXp:gainEquip.
     *
     * Player invokes the top-bar `completeQuest` free action while Mining
     * Equipment is on top of deck_equip_<color>. The chain pays 3 XP (yellow
     * crystals from tableau) then runs gainEquip, which moves the card to
     * tableau and reveals the next deck-top.
     *
     * The "1 gold + 2 gold" wording is a flavor joke — mechanically a single 3-XP payment.
     */
    public function testMiningEquipmentLandsOnTableauAfterCompleteQuestPays3Xp(): void {
        $color = $this->getActivePlayerColor();

        $miningEquipment = "card_equip_4_17";
        $nextCard = "card_equip_4_19"; // Orebiter — surfaces after Mining Equipment is claimed
        $this->seedDeck("deck_equip_$color", [$miningEquipment, $nextCard]);
        $this->assertEquals($miningEquipment, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Seed Boldur with 3 yellow crystals (XP) on tableau so the 3spendXp cost can be paid.
        $this->game->effect_moveCrystals($this->heroId, "yellow", 3, "tableau_$color", ["message" => ""]);
        $xpBefore = $this->countTokens("crystal_yellow", "tableau_$color");
        $this->assertGreaterThanOrEqual(3, $xpBefore, "Need at least 3 XP on tableau to pay the quest cost");

        $this->respond("completeQuest");

        // completeQuest prompts for the deck-top card; pick Mining Equipment.
        $this->assertOperation("completeQuest");
        $this->assertValidTarget($miningEquipment);
        $this->respond($miningEquipment);

        // Chained 3spendXp is a single-confirm op (count-prefixed) — accept it.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($miningEquipment),
            "Mining Equipment should land on tableau after completeQuest pays 3 XP"
        );

        $this->assertEquals(
            $xpBefore - 3,
            $this->countTokens("crystal_yellow", "tableau_$color"),
            "3 yellow crystals should have been spent paying the quest cost"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Orebiter should surface as the new deck-top");
    }

    /**
     * Dwarf Helm (card_equip_4_23): quest_on= (empty — player-initiated),
     * quest_r=in(TempleRuins):2spendXp:gainEquip.
     *
     * Player invokes the top-bar `completeQuest` free action while Dwarf Helm
     * is on top of deck_equip_<color> AND the hero stands on the Temple Ruins
     * hex (hex_12_4). The chain pays 2 XP (yellow crystals from tableau) then
     * runs gainEquip, which moves the card to tableau and reveals the next
     * deck-top.
     */
    public function testDwarfHelmLandsOnTableauAfterCompleteQuestPays2XpInTempleRuins(): void {
        $color = $this->getActivePlayerColor();

        $dwarfHelm = "card_equip_4_23";
        $nextCard = "card_equip_4_24"; // Battle Boots — surfaces after Dwarf Helm is claimed
        $this->seedDeck("deck_equip_$color", [$dwarfHelm, $nextCard]);
        $this->assertEquals($dwarfHelm, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Place hero on Temple Ruins hex so the in(TempleRuins) gate passes.
        $this->game->tokens->moveToken($this->heroId, "hex_12_4");

        // Seed Boldur with 2 yellow crystals (XP) on tableau so the 2spendXp cost can be paid.
        $this->game->effect_moveCrystals($this->heroId, "yellow", 2, "tableau_$color", ["message" => ""]);
        $xpBefore = $this->countTokens("crystal_yellow", "tableau_$color");
        $this->assertGreaterThanOrEqual(2, $xpBefore, "Need at least 2 XP on tableau to pay the quest cost");

        $this->respond("completeQuest");

        // completeQuest prompts for the deck-top card; pick Dwarf Helm.
        $this->assertOperation("completeQuest");
        $this->assertValidTarget($dwarfHelm);
        $this->respond($dwarfHelm);

        // Chained 2spendXp is a single-confirm op (count-prefixed) — accept it.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($dwarfHelm),
            "Dwarf Helm should land on tableau after completeQuest pays 2 XP in Temple Ruins"
        );

        $this->assertEquals(
            $xpBefore - 2,
            $this->countTokens("crystal_yellow", "tableau_$color"),
            "2 yellow crystals should have been spent paying the quest cost"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Battle Boots should surface as the new deck-top");
    }

    /**
     * Battle Boots (card_equip_4_24): quest_on= (empty — player-initiated),
     * quest_r=spendAction(actionFocus):gainEquip.
     *
     * Player invokes the top-bar `completeQuest` free action while Battle Boots
     * is on top of deck_equip_<color>. The chain spends a Focus action then runs
     * gainEquip, which moves the card to tableau and reveals the next deck-top.
     *
     * Mirrors Embla's Leg Guards flow (same shape, different hero deck).
     */
    public function testBattleBootsLandsOnTableauAfterCompleteQuest(): void {
        $color = $this->getActivePlayerColor();

        $battleBoots = "card_equip_4_24";
        $nextCard = "card_equip_4_19"; // Orebiter — surfaces after Battle Boots is claimed
        $this->seedDeck("deck_equip_$color", [$battleBoots, $nextCard]);
        $this->assertEquals($battleBoots, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        $this->respond("completeQuest");

        // completeQuest prompts for the deck-top card; pick Battle Boots.
        $this->assertOperation("completeQuest");
        $this->assertValidTarget($battleBoots);
        $this->respond($battleBoots);

        // Chained spendAction(actionFocus) is a single-confirm op — accept it.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($battleBoots),
            "Battle Boots should land on tableau after completeQuest"
        );

        $hero = $this->game->getHero($color);
        $this->assertContains(
            "actionFocus",
            $hero->getActionsTaken(),
            "Focus action should be marked as taken after spendAction(actionFocus)"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Orebiter should surface as the new deck-top");
    }

    /**
     * Smiterbiter (card_equip_4_21): quest_on=TMove,
     * quest_r=in(MarshOfSorrow):gainEquip.
     *
     * Trigger-driven, end-of-movement positional quest. When Boldur ends a move
     * on a Marsh of Sorrow hex, Trigger::Move (chained from ActionMove) walks the
     * deck-top equip card; the in(MarshOfSorrow) gate passes and gainEquip moves
     * the card to tableau. No counter, no payment — single-shot.
     *
     * Mirrors Belt of Youth's trigger-driven shape (push single `move` ops
     * directly onto the machine), but only one move is needed.
     */
    public function testSmiterbiterLandsOnTableauAfterMoveEndsInMarshOfSorrow(): void {
        $color = $this->getActivePlayerColor();

        $smiterbiter = "card_equip_4_21";
        $nextCard = "card_equip_4_19"; // Orebiter — surfaces after Smiterbiter is claimed
        $this->seedDeck("deck_equip_$color", [$smiterbiter, $nextCard]);
        $this->assertEquals($smiterbiter, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Place Boldur on a non-Marsh plains hex adjacent to a Marsh hex.
        // CSV is y|x so map_material row "7|16|plains|MarshOfSorrow" -> hex_16_7.
        $startHex = "hex_15_8"; // plains, no named loc
        $marshHex = "hex_16_8"; // plains, MarshOfSorrow
        $this->game->tokens->moveToken($this->heroId, $startHex);

        // Single move into the Marsh. Op_step emits Trigger::ActionMove on the
        // final step (because reason="Op_actionMove"), which chains through
        // Trigger::Move. Smiterbiter listens on TMove, so it matches.
        $this->respond($marshHex);

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($smiterbiter),
            "Smiterbiter should land on tableau after a move ending in Marsh of Sorrow"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Orebiter should surface as the new deck-top");
    }

    /**
     * Dwarf Pick (card_equip_4_25): quest_on=TMonsterKilled,
     * quest_r=gainTracker,check('countTracker>=3'):gainEquip.
     *
     * Trigger-driven counter quest. Every monster kill ticks the counter (no
     * predicate filter — any kill counts). After 3 kills the card leaves
     * deck_equip_<color> and lands on tableau_<color>, with all progress
     * crystals swept to supply.
     *
     * Mirrors Belt of Youth's trigger-driven shape, but on TMonsterKilled —
     * three single-shot dealDamage ops with count=2 (goblin health) kill three
     * goblins one at a time, each firing Trigger::MonsterKilled which the
     * deck-top Dwarf Pick listens for.
     *
     * Op_check only hides ONE next op — fine here because the chain past `check`
     * is just `gainEquip` (single op).
     */
    public function testDwarfPickLandsOnTableauAfter3MonsterKills(): void {
        $color = $this->getActivePlayerColor();

        $dwarfPick = "card_equip_4_25";
        $nextCard = "card_equip_4_19"; // Orebiter — surfaces after Dwarf Pick is claimed
        $this->seedDeck("deck_equip_$color", [$dwarfPick, $nextCard]);
        $this->assertEquals($dwarfPick, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Place Boldur and three goblins on adjacent hexes. Goblins have health=2,
        // so dealDamage(count=2) kills outright and queues Trigger::MonsterKilled.
        $heroHex = "hex_11_8";
        $this->game->tokens->moveToken($this->heroId, $heroHex);
        $goblinHexes = ["hex_12_8", "hex_10_8", "hex_11_7"];
        $goblins = ["monster_goblin_1", "monster_goblin_2", "monster_goblin_3"];
        foreach ($goblins as $i => $goblin) {
            $this->game->getMonster($goblin)->moveTo($goblinHexes[$i], "");
        }

        // First kill — counter ticks to 1, check fails, gainEquip hidden.
        $this->game->machine->push("dealDamage", $color, ["target" => $goblinHexes[0], "count" => 2]);
        $this->game->machine->dispatchAll();
        $this->assertEquals("supply_monster", $this->tokenLocation($goblins[0]), "Goblin 1 should be killed");
        $this->assertEquals(1, $this->countTokens("crystal_red", $dwarfPick), "After kill 1, Dwarf Pick should have 1 progress crystal");
        $this->assertEquals(
            "deck_equip_$color",
            $this->tokenLocation($dwarfPick),
            "Dwarf Pick should still be in the deck after only 1 kill"
        );

        // Second kill — counter ticks to 2, check still fails, gainEquip still hidden.
        $this->game->machine->push("dealDamage", $color, ["target" => $goblinHexes[1], "count" => 2]);
        $this->game->machine->dispatchAll();
        $this->assertEquals("supply_monster", $this->tokenLocation($goblins[1]), "Goblin 2 should be killed");
        $this->assertEquals(2, $this->countTokens("crystal_red", $dwarfPick), "After kill 2, Dwarf Pick should have 2 progress crystals");
        $this->assertEquals(
            "deck_equip_$color",
            $this->tokenLocation($dwarfPick),
            "Dwarf Pick should still be in the deck after only 2 kills"
        );

        // Third kill — counter ticks to 3, check passes, gainEquip runs.
        $this->game->machine->push("dealDamage", $color, ["target" => $goblinHexes[2], "count" => 2]);
        $this->game->machine->dispatchAll();
        $this->assertEquals("supply_monster", $this->tokenLocation($goblins[2]), "Goblin 3 should be killed");

        $this->assertEquals("tableau_$color", $this->tokenLocation($dwarfPick), "Dwarf Pick should land on tableau after 3 monster kills");
        $this->assertEquals(0, $this->countTokens("crystal_red", $dwarfPick), "Op_gainEquip should sweep tracker crystals back to supply");

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Orebiter should surface as the new deck-top");
    }

    /**
     * Dwarf Mail (card_equip_4_18): quest_on=TStep,
     * quest_r=adj(mountain):gainTracker,check('countTracker>=7'):gainEquip.
     *
     * Counter quest mirroring Belt of Youth / Raven's Claw, but the gate is
     * adj(mountain) instead of in(forest). Op_adj passes when the hero's hex
     * has at least one neighbor whose terrain is "mountain". After 7 TStep
     * firings on mountain-adjacent hexes, the card leaves deck_equip_<color>
     * and lands on tableau_<color>, with all progress crystals swept to supply.
     *
     * Bonus: a single non-mountain-adjacent step (hex_7_11 → hex_7_12, both
     * plains with no mountain neighbors) is performed first to verify the
     * adj(mountain) gate suppresses the tracker tick when the destination is
     * not adjacent to a mountain.
     *
     * Bypasses the actionMove turn flow and pushes single-step `move` ops
     * directly. Hero oscillates between hex_6_11 and hex_6_12 — both plains,
     * both adjacent to the mountain at hex_5_12.
     *
     * Op_check only hides ONE next op — fine here because the chain past `check`
     * is just `gainEquip` (single op).
     */
    public function testDwarfMailLandsOnTableauAfter7MountainAdjacentSteps(): void {
        $color = $this->getActivePlayerColor();

        $dwarfMail = "card_equip_4_18";
        $nextCard = "card_equip_4_19"; // Orebiter — surfaces after Dwarf Mail is claimed
        $this->seedDeck("deck_equip_$color", [$dwarfMail, $nextCard]);
        $this->assertEquals($dwarfMail, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Two adjacent plains hexes, both adjacent to mountain hex_5_12.
        $hexA = "hex_6_11"; // plains, adjacent to mountain hex_5_12
        $hexB = "hex_6_12"; // plains, adjacent to mountain hex_5_12 (and hex_5_13)
        // Two adjacent plains hexes with NO mountain neighbors (negative case).
        $hexNonAdj1 = "hex_7_11"; // plains, no mountain neighbors
        $hexNonAdj2 = "hex_7_12"; // plains, no mountain neighbors
        $this->game->tokens->moveToken($this->heroId, $hexNonAdj1);

        // Bonus: one non-mountain-adjacent step first. From hex_7_11 to hex_7_12.
        // TStep fires at the destination — adj(mountain) gate fails — no crystal added.
        $this->game->machine->push("move", $color, ["target" => $hexNonAdj2, "reason" => "Op_actionMove"]);
        $this->game->machine->dispatchAll();
        $this->assertEquals(
            0,
            $this->countTokens("crystal_red", $dwarfMail),
            "A non-mountain-adjacent step must NOT add a tracker crystal (adj(mountain) gate suppresses the tick)"
        );
        $this->assertEquals(
            "deck_equip_$color",
            $this->tokenLocation($dwarfMail),
            "Dwarf Mail should still be on the deck after a non-mountain-adjacent step"
        );

        // Move hero onto a mountain-adjacent hex so the first scripted move starts cleanly.
        $this->game->tokens->moveToken($this->heroId, $hexA);

        // 7 single-step moves, oscillating A → B → A → B …
        // Each move emits TStep at the destination (mountain-adjacent), so quest_r runs:
        // adj(mountain) passes → gainTracker → check(countTracker>=7) → gainEquip.
        // push() interrupts on top of the active turn op. LIFO order means we
        // push in reverse so the dispatch sequence is i=0, 1, 2, … 6
        // (alternating B, A, B, A, …; hero starts at A so first move is real).
        for ($i = 6; $i >= 0; $i--) {
            $target = $i % 2 === 0 ? $hexB : $hexA;
            $this->game->machine->push("move", $color, ["target" => $target, "reason" => "Op_actionMove"]);
        }

        $this->game->machine->dispatchAll();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($dwarfMail),
            "Dwarf Mail should land on tableau after 7 mountain-adjacent TSteps"
        );
        $this->assertEquals(0, $this->countTokens("crystal_red", $dwarfMail), "Op_gainEquip should sweep tracker crystals back to supply");

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Orebiter should surface as the new deck-top");
    }

    /**
     * Orebiter (card_equip_4_19): quest_on= (empty — player-initiated),
     * quest_r=2spendXp:gainEquip.
     *
     * Player invokes the top-bar `completeQuest` free action while Orebiter is
     * on top of deck_equip_<color>. The chain pays 2 XP (yellow crystals from
     * tableau) then runs gainEquip, which moves the card to tableau and reveals
     * the next deck-top.
     *
     * Mirrors Mining Equipment's flow (same shape, 2 XP instead of 3, no
     * location gate — pay anywhere).
     */
    public function testOrebiterLandsOnTableauAfterCompleteQuestPays2Xp(): void {
        $color = $this->getActivePlayerColor();

        $orebiter = "card_equip_4_19";
        $nextCard = "card_equip_4_20"; // Dvalin's Pick — surfaces after Orebiter is claimed
        $this->seedDeck("deck_equip_$color", [$orebiter, $nextCard]);
        $this->assertEquals($orebiter, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Seed Boldur with 2 yellow crystals (XP) on tableau so the 2spendXp cost can be paid.
        $this->game->effect_moveCrystals($this->heroId, "yellow", 2, "tableau_$color", ["message" => ""]);
        $xpBefore = $this->countTokens("crystal_yellow", "tableau_$color");
        $this->assertGreaterThanOrEqual(2, $xpBefore, "Need at least 2 XP on tableau to pay the quest cost");

        $this->respond("completeQuest");

        // completeQuest prompts for the deck-top card; pick Orebiter.
        $this->assertOperation("completeQuest");
        $this->assertValidTarget($orebiter);
        $this->respond($orebiter);

        // Chained 2spendXp is a single-confirm op (count-prefixed) — accept it.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($orebiter),
            "Orebiter should land on tableau after completeQuest pays 2 XP"
        );

        $this->assertEquals(
            $xpBefore - 2,
            $this->countTokens("crystal_yellow", "tableau_$color"),
            "2 yellow crystals should have been spent paying the quest cost"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Dvalin's Pick should surface as the new deck-top");
    }

    /**
     * Dvalin's Pick (card_equip_4_20): quest_on=TMove,
     * quest_r=check('countAdjMountains>=3'):gainEquip.
     *
     * End-of-movement positional. When Boldur ends a move on hex_4_15 (plains),
     * three of its six neighbors are mountains (hex_3_15, hex_5_15, hex_3_16 —
     * SpewingMountain cluster), so countAdjMountains==3. The gate passes and
     * gainEquip moves the card to tableau.
     */
    public function testDvalinsPickLandsOnTableauWhenMoveEndsAdjacentTo3Mountains(): void {
        $color = $this->getActivePlayerColor();

        $dvalinsPick = "card_equip_4_20";
        $nextCard = "card_equip_4_19"; // Orebiter
        $this->seedDeck("deck_equip_$color", [$dvalinsPick, $nextCard]);

        $startHex = "hex_4_14"; // plains, only 1 adjacent mountain (hex_3_15)
        $endHex = "hex_4_15"; // plains, 3 adjacent mountains (hex_3_15, hex_5_15, hex_3_16)
        $this->game->tokens->moveToken($this->heroId, $startHex);

        $this->respond($endHex);

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($dvalinsPick),
            "Dvalin's Pick should land on tableau after move ends adjacent to 3 mountains"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Orebiter should surface as the new deck-top");
    }

    /**
     * Eitri's Pick (card_equip_4_22): quest_on=TMove,
     * quest_r=check('countAdjMonsters>=4 or countAdjLegends>=1'):gainEquip.
     *
     * "4 monsters" branch: end on hex_11_8 with four goblins on its non-Grimheim
     * neighbors (hex_12_8, hex_11_9, hex_11_7, hex_12_7). countAdjMonsters==4
     * → check passes → gainEquip moves the pick to tableau.
     */
    public function testEitrisPickLandsOnTableauWhenMoveEndsAdjacentTo4Monsters(): void {
        $color = $this->getActivePlayerColor();

        $eitrisPick = "card_equip_4_22";
        $nextCard = "card_equip_4_19"; // Orebiter
        $this->seedDeck("deck_equip_$color", [$eitrisPick, $nextCard]);

        $startHex = "hex_10_9"; // Grimheim plains, adjacent to hex_11_8
        $endHex = "hex_11_8";
        $this->game->tokens->moveToken($this->heroId, $startHex);

        $monsterHexes = ["hex_12_8", "hex_11_9", "hex_11_7", "hex_12_7"];
        $monsters = ["monster_goblin_1", "monster_goblin_2", "monster_goblin_3", "monster_goblin_4"];
        foreach ($monsters as $i => $m) {
            $this->game->getMonster($m)->moveTo($monsterHexes[$i], "");
        }

        $this->respond($endHex);

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($eitrisPick),
            "Eitri's Pick should land on tableau after move ends adjacent to 4 monsters"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertEquals($nextCard, $newTop["key"], "Orebiter should surface as the new deck-top");
    }

    /**
     * Eitri's Pick "1 legend" branch: end on hex_11_8 with a single legend
     * monster on hex_12_8. countAdjMonsters==1 (the legend itself) and
     * countAdjLegends==1 → the OR predicate's legend disjunct passes.
     */
    public function testEitrisPickLandsOnTableauWhenMoveEndsAdjacentToLegend(): void {
        $color = $this->getActivePlayerColor();

        $eitrisPick = "card_equip_4_22";
        $nextCard = "card_equip_4_19";
        $this->seedDeck("deck_equip_$color", [$eitrisPick, $nextCard]);

        $startHex = "hex_10_9";
        $endHex = "hex_11_8";
        $this->game->tokens->moveToken($this->heroId, $startHex);

        $this->game->getMonster("monster_legend_1_1")->moveTo("hex_12_8", "");

        $this->respond($endHex);

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($eitrisPick),
            "Eitri's Pick should land on tableau after move ends adjacent to a legend"
        );
    }

    /**
     * Negative path: end on a hex with no adjacent monsters or legends — both
     * disjuncts fail, check evaluates to 0, gainEquip hidden, pick stays in deck.
     */
    public function testEitrisPickStaysInDeckWhenMoveEndsAwayFromMonstersAndLegends(): void {
        $color = $this->getActivePlayerColor();

        $eitrisPick = "card_equip_4_22";
        $this->seedDeck("deck_equip_$color", [$eitrisPick, "card_equip_4_19"]);

        $startHex = "hex_7_9";
        $endHex = "hex_7_8";
        $this->game->tokens->moveToken($this->heroId, $startHex);

        $this->respond($endHex);

        $this->assertEquals(
            "deck_equip_$color",
            $this->tokenLocation($eitrisPick),
            "Eitri's Pick should stay in deck — no adjacent monsters or legends"
        );
    }

    /**
     * Negative path: ending a move on a hex with fewer than 3 adjacent mountains
     * makes the check evaluate to 0, hiding gainEquip — pick stays in deck.
     */
    public function testDvalinsPickStaysInDeckWhenMoveEndsAwayFromMountains(): void {
        $color = $this->getActivePlayerColor();

        $dvalinsPick = "card_equip_4_20";
        $nextCard = "card_equip_4_19";
        $this->seedDeck("deck_equip_$color", [$dvalinsPick, $nextCard]);

        $startHex = "hex_7_9"; // plains, no mountains anywhere near
        $endHex = "hex_7_8";
        $this->game->tokens->moveToken($this->heroId, $startHex);

        $this->respond($endHex);

        $this->assertEquals(
            "deck_equip_$color",
            $this->tokenLocation($dvalinsPick),
            "Dvalin's Pick should stay in deck — gate countAdjMountains>=3 failed"
        );
    }

    /**
     * Shield (card_equip_4_16): bespoke (CardEquip_Shield), quest_on=custom.
     * Two OR-branches:
     *   A. Enter Ogre Valley → auto-claim
     *   B. Skip XP from troll kill → optional ?(blockXp:gainEquip) prompt
     *
     * Both branches stay live until the card is claimed.
     */

    public function testShieldClaimsItselfOnEnteringOgreValley(): void {
        $color = $this->getActivePlayerColor();

        $shield = "card_equip_4_16";
        $nextCard = "card_equip_4_19"; // Orebiter — surfaces after Shield is claimed
        $this->seedDeck("deck_equip_$color", [$shield, $nextCard]);

        // Boldur on a hex adjacent to the OgreValley cluster; step into hex_2_8 (forest, OgreValley).
        $this->game->tokens->moveToken($this->heroId, "hex_3_7");
        $ogreValleyHex = "hex_2_8";

        $this->respond($ogreValleyHex);

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($shield),
            "Shield should land on tableau after stepping into Ogre Valley"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Orebiter should surface as the new deck-top");
    }

    public function testShieldStaysInDeckWhenSteppingOutsideOgreValley(): void {
        $color = $this->getActivePlayerColor();

        $shield = "card_equip_4_16";
        $nextCard = "card_equip_4_19";
        $this->seedDeck("deck_equip_$color", [$shield, $nextCard]);

        // Boldur on a plains hex; step into another plains hex (not Ogre Valley).
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $this->respond("hex_7_8");

        $this->assertEquals("deck_equip_$color", $this->tokenLocation($shield), "Shield stays in deck — destination wasn't Ogre Valley");
    }

    public function testShieldClaimsOnTrollKillWhenAccepted(): void {
        $color = $this->getActivePlayerColor();

        $shield = "card_equip_4_16";
        $nextCard = "card_equip_4_19";
        $this->seedDeck("deck_equip_$color", [$shield, $nextCard]);

        $heroHex = "hex_11_8";
        $trollHex = "hex_12_8";
        $this->game->tokens->moveToken($this->heroId, $heroHex);
        $this->game->getMonster("monster_troll_1")->moveTo($trollHex, "");

        $xpBefore = $this->countXp();

        $this->game->machine->push("dealDamage", $color, ["target" => $trollHex, "count" => 7]);
        $this->game->machine->dispatchAll();

        // Optional ?(blockXp:gainEquip) prompt — accept it.
        $this->confirmCardEffect();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_troll_1"), "Troll should be killed");
        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($shield),
            "Shield should land on tableau on troll kill when player accepts"
        );
        $this->assertEquals($xpBefore, $this->countXp(), "blockXp should suppress the troll's XP reward");

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Orebiter should surface as the new deck-top");
    }

    public function testShieldDeclinedOnTrollKillKeepsXp(): void {
        $color = $this->getActivePlayerColor();

        $shield = "card_equip_4_16";
        $nextCard = "card_equip_4_19";
        $this->seedDeck("deck_equip_$color", [$shield, $nextCard]);

        $this->game->tokens->moveToken($this->heroId, "hex_11_8");
        $this->game->getMonster("monster_troll_1")->moveTo("hex_12_8", "");

        $baseXp = $this->game->getMonster("monster_troll_1")->getXpReward();
        $xpBefore = $this->countXp();

        $this->game->machine->push("dealDamage", $color, ["target" => "hex_12_8", "count" => 7]);
        $this->game->machine->dispatchAll();

        // Decline the optional ?(blockXp:gainEquip) prompt.
        $this->skip();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_troll_1"));
        $this->assertEquals("deck_equip_$color", $this->tokenLocation($shield), "Shield stays in deck on decline");
        $this->assertEquals($xpBefore + $baseXp, $this->countXp(), "Troll XP awarded normally when player declines");
    }

    public function testShieldIgnoresNonTrollKills(): void {
        $color = $this->getActivePlayerColor();

        $shield = "card_equip_4_16";
        $nextCard = "card_equip_4_19";
        $this->seedDeck("deck_equip_$color", [$shield, $nextCard]);

        $this->game->tokens->moveToken($this->heroId, "hex_11_8");
        $this->game->getMonster("monster_brute_1")->moveTo("hex_12_8", "");

        $xpBefore = $this->countXp();

        $this->game->machine->push("dealDamage", $color, ["target" => "hex_12_8", "count" => 3]);
        $this->game->machine->dispatchAll();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_brute_1"));
        $this->assertEquals("deck_equip_$color", $this->tokenLocation($shield), "Shield stays in deck — brute isn't a troll");
        $this->assertEquals($xpBefore + 2, $this->countXp(), "Brute XP awarded normally when chain doesn't fire");
    }
}
