<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * End-to-end integration tests for Bjorn-specific quests.
 *
 * Each test scripts a scenario that exercises one of Bjorn's quest cards
 * (defined in misc/card_equip_material.csv with quest_on / quest_r) end-to-end:
 * trigger fires → quest_r runs → progress accumulates / equip lands on tableau.
 */
class Campaign_BjornQuestTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1]); // Solo Bjorn
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());
        $this->clearMonstersFromMap();
    }

    /**
     * Bone Bane Bow (card_equip_1_16): quest_on= (empty — player-initiated),
     * quest_r=in(Nailfare):spendAction(actionMend):gainEquip.
     *
     * Player invokes the top-bar `completeQuest` free action while Bone Bane Bow
     * is on top of deck_equip_<color> AND the hero stands on a Nailfare hex
     * (named location, lake terrain — explicitly passable per HexMap rules).
     * The chain spends a Mend action then runs gainEquip, which moves the card
     * to tableau and reveals the next deck-top.
     */
    public function testBoneBaneBowLandsOnTableauAfterCompleteQuestOnNailfare(): void {
        $color = $this->getActivePlayerColor();

        $bow = "card_equip_1_16";
        $nextCard = "card_equip_1_17"; // Throwing Axes — surfaces after Bone Bane Bow is claimed
        $this->seedDeck("deck_equip_$color", [$bow, $nextCard]);
        $this->assertEquals($bow, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Place Bjorn on a Nailfare hex so the in(Nailfare) gate passes.
        $nailfareHex = "hex_16_5";
        $this->game->tokens->moveToken($this->heroId, $nailfareHex);

        $this->respond("completeQuest");

        $this->assertOperation("completeQuest");
        $this->assertValidTarget($bow);
        $this->respond($bow);

        // Chained spendAction(actionMend) is a single-confirm op — accept it.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($bow),
            "Bone Bane Bow should land on tableau after completeQuest on Nailfare"
        );

        $hero = $this->game->getHero($color);
        $this->assertContains(
            "actionMend",
            $hero->getActionsTaken(),
            "Mend action should be marked as taken after spendAction(actionMend)"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Throwing Axes should surface as the new deck-top");
    }

    /**
     * Black Arrows (card_equip_1_20): quest_on= (empty — player-initiated),
     * quest_r=in(RobberCamp):spendAction(actionAttack):gainEquip.
     *
     * Player invokes the top-bar `completeQuest` free action while Black Arrows
     * is on top of deck_equip_<color> AND the hero stands on the Robber Camp hex.
     * The chain spends an Attack action then runs gainEquip, landing the card
     * on the tableau and revealing the next deck-top.
     */
    public function testBlackArrowsLandsOnTableauAfterCompleteQuestOnRobberCamp(): void {
        $color = $this->getActivePlayerColor();

        $blackArrows = "card_equip_1_20";
        $nextCard = "card_equip_1_17"; // Throwing Axes — placeholder behind Black Arrows
        $this->seedDeck("deck_equip_$color", [$blackArrows, $nextCard]);
        $this->assertEquals($blackArrows, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Place Bjorn on the Robber Camp hex so the in(RobberCamp) gate passes.
        $robberCampHex = "hex_5_11";
        $this->game->tokens->moveToken($this->heroId, $robberCampHex);

        $this->respond("completeQuest");

        $this->assertOperation("completeQuest");
        $this->assertValidTarget($blackArrows);
        $this->respond($blackArrows);

        // Chained spendAction(actionAttack) is a single-confirm op — accept it.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($blackArrows),
            "Black Arrows should land on tableau after completeQuest on Robber Camp"
        );

        $hero = $this->game->getHero($color);
        $this->assertContains(
            "actionAttack",
            $hero->getActionsTaken(),
            "Attack action should be marked as taken after spendAction(actionAttack)"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Throwing Axes should surface as the new deck-top");
    }

    /**
     * Home Sewn Cape (card_equip_1_24): quest_on= (empty — player-initiated),
     * quest_r=check('countAdjMonsters==0'):spendAction(actionAttack):gainEquip.
     *
     * Positive path: hero stands on a hex with no adjacent monsters → check
     * evaluates 0==0 = 1 (truthy) → spendAction(actionAttack) prompts → gainEquip
     * lands the cape on tableau and reveals the next deck-top.
     */
    public function testHomeSewnCapeLandsOnTableauAfterCompleteQuestWithNoAdjacentMonsters(): void {
        $color = $this->getActivePlayerColor();

        $cape = "card_equip_1_24";
        $nextCard = "card_equip_1_17"; // Throwing Axes — placeholder behind Cape
        $this->seedDeck("deck_equip_$color", [$cape, $nextCard]);
        $this->assertEquals($cape, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Plains hex outside Grimheim, no adjacent monsters (clearMonstersFromMap above).
        $heroHex = "hex_7_9";
        $this->game->tokens->moveToken($this->heroId, $heroHex);

        $this->respond("completeQuest");

        $this->assertOperation("completeQuest");
        $this->assertValidTarget($cape);
        $this->respond($cape);

        // Chained spendAction(actionAttack) is a single-confirm op — accept it.
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($cape),
            "Home Sewn Cape should land on tableau after completeQuest with no adjacent monsters"
        );

        $hero = $this->game->getHero($color);
        $this->assertContains(
            "actionAttack",
            $hero->getActionsTaken(),
            "Attack action should be marked as taken after spendAction(actionAttack)"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Throwing Axes should surface as the new deck-top");
    }

    /**
     * Negative path for Home Sewn Cape — adjacent monster makes
     * check('countAdjMonsters==0') evaluate to 0, which hides the grouped
     * (spendAction(actionAttack):gainEquip) chain so neither op fires.
     */
    public function testHomeSewnCapeQuestBlockedWhenMonsterAdjacent(): void {
        $color = $this->getActivePlayerColor();

        $cape = "card_equip_1_24";
        $nextCard = "card_equip_1_17";
        $this->seedDeck("deck_equip_$color", [$cape, $nextCard]);

        $heroHex = "hex_7_9";
        $this->game->tokens->moveToken($this->heroId, $heroHex);
        $this->game->getMonster("monster_goblin_1")->moveTo("hex_7_8", "");

        $this->respond("completeQuest");

        $this->assertOperation("completeQuest");
        $this->respond($cape);

        $this->assertEquals(
            "deck_equip_$color",
            $this->tokenLocation($cape),
            "Cape should stay in deck — gate countAdjMonsters==0 fails with monster adjacent"
        );

        $hero = $this->game->getHero($color);
        $this->assertNotContains(
            "actionAttack",
            $hero->getActionsTaken(),
            "Attack action should NOT be marked taken — gate hid the chain"
        );

        $this->assertEquals(
            $cape,
            $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"],
            "Cape should still be on top of deck"
        );
    }

    /**
     * Home Sewn Tunic (card_equip_1_23): quest_on= (empty — player-initiated),
     * quest_r=spendAction(actionPractice):spendXp:gainEquip.
     *
     * Player invokes the top-bar `completeQuest` free action while Home Sewn Tunic
     * is on top of deck_equip_<color>. The chain spends a Practice action AND
     * 1 XP (yellow crystal from tableau), then runs gainEquip — landing the card
     * on the tableau and revealing the next deck-top.
     *
     * No location gate (the quest text "Spend 1 practice action and 1 experience"
     * has no place restriction).
     */
    public function testHomeSewnTunicLandsOnTableauAfterCompleteQuestPaysPracticeAndXp(): void {
        $color = $this->getActivePlayerColor();

        $tunic = "card_equip_1_23";
        $nextCard = "card_equip_1_17"; // Throwing Axes — placeholder behind Tunic
        $this->seedDeck("deck_equip_$color", [$tunic, $nextCard]);
        $this->assertEquals($tunic, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Seed Bjorn with 1 yellow crystal (XP) on tableau so the spendXp cost can be paid.
        $this->game->effect_moveCrystals($this->heroId, "yellow", 1, "tableau_$color", ["message" => ""]);
        $xpBefore = $this->countTokens("crystal_yellow", "tableau_$color");
        $this->assertGreaterThanOrEqual(1, $xpBefore, "Need at least 1 XP on tableau to pay the quest cost");

        $this->respond("completeQuest");

        $this->assertOperation("completeQuest");
        $this->assertValidTarget($tunic);
        $this->respond($tunic);

        // Chain has two prompts: spendAction(actionPractice) then spendXp.
        // Both are single-confirm ops — accept each.
        $this->confirmCardEffect();
        $this->confirmCardEffect();

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($tunic),
            "Home Sewn Tunic should land on tableau after completeQuest pays Practice + 1 XP"
        );

        $hero = $this->game->getHero($color);
        $this->assertContains(
            "actionPractice",
            $hero->getActionsTaken(),
            "Practice action should be marked as taken after spendAction(actionPractice)"
        );

        $this->assertEquals(
            $xpBefore - 1,
            $this->countTokens("crystal_yellow", "tableau_$color"),
            "1 yellow crystal should have been spent paying the quest cost"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Throwing Axes should surface as the new deck-top");
    }

    /**
     * Quiver (card_equip_1_18): quest_on=TMonsterKilled,
     * quest_r=killed('rank>=3'):?(blockXp:gainEquip).
     *
     * Trigger-driven replacement-reward quest: when Bjorn kills a rank-3 (or
     * higher) monster while Quiver is on top of the deck, the player gets a
     * yes/no prompt — accept to claim Quiver and forfeit the kill's XP, skip
     * to take the XP normally.
     */
    public function testQuiverClaimsItselfOnRank3KillWhenAccepted(): void {
        $color = $this->getActivePlayerColor();

        $quiver = "card_equip_1_18";
        $nextCard = "card_equip_1_17"; // Throwing Axes
        $this->seedDeck("deck_equip_$color", [$quiver, $nextCard]);

        $heroHex = "hex_11_8";
        $trollHex = "hex_12_8";
        $this->game->tokens->moveToken($this->heroId, $heroHex);
        $this->game->getMonster("monster_troll_1")->moveTo($trollHex, "");

        $xpBefore = $this->countXp();

        // Kill the troll outright via dealDamage(7) — fires TMonsterKilled in the standard pipeline.
        $this->game->machine->push("dealDamage", $color, ["target" => $trollHex, "count" => 7]);
        $this->game->machine->dispatchAll();

        // Optional ?(blockXp:gainEquip) inside the quest_r pops a confirm prompt — accept it.
        $this->confirmCardEffect();
        $this->game->machine->dispatchAll();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_troll_1"), "Troll should be killed");
        $this->assertEquals("tableau_$color", $this->tokenLocation($quiver), "Quiver should land on tableau on rank-3 kill");
        $this->assertEquals($xpBefore, $this->countXp(), "blockXp should suppress the troll's XP reward");

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Throwing Axes should surface as the new deck-top");
    }

    /**
     * Player declines the optional claim — Quiver stays in deck, XP awarded normally.
     */
    public function testQuiverDeclinedKeepsXp(): void {
        $color = $this->getActivePlayerColor();

        $quiver = "card_equip_1_18";
        $nextCard = "card_equip_1_17";
        $this->seedDeck("deck_equip_$color", [$quiver, $nextCard]);

        $this->game->tokens->moveToken($this->heroId, "hex_11_8");
        $this->game->getMonster("monster_troll_1")->moveTo("hex_12_8", "");

        $baseXp = $this->game->getMonster("monster_troll_1")->getXpReward();
        $xpBefore = $this->countXp();

        $this->game->machine->push("dealDamage", $color, ["target" => "hex_12_8", "count" => 7]);
        $this->game->machine->dispatchAll();

        // Decline the optional ?(blockXp:gainEquip) prompt.
        $this->skip();
        $this->game->machine->dispatchAll();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_troll_1"));
        $this->assertEquals("deck_equip_$color", $this->tokenLocation($quiver), "Quiver stays in deck on decline");
        $this->assertEquals($xpBefore + $baseXp, $this->countXp(), "XP awarded when player declines the claim");
    }

    /**
     * Negative path: rank-1 (goblin) kill doesn't satisfy killed('rank>=3'),
     * so the chain voids before reaching the optional prompt — no choice
     * presented, Quiver stays in deck, XP awarded normally.
     */
    public function testQuiverStaysInDeckWhenKillIsRank1(): void {
        $color = $this->getActivePlayerColor();

        $quiver = "card_equip_1_18";
        $nextCard = "card_equip_1_17";
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
     * Helmet (card_equip_1_21): quest_on=TMonsterKilled,
     * quest_r=killed('brute or skeleton'):?(blockXp:gainEquip).
     *
     * Trigger-driven replacement-reward quest. Same shape as Quiver, only the
     * killed() predicate matches the bareword tribe terms `brute` / `skeleton`
     * (any monster_brute_* or monster_skeleton_*) instead of rank>=3.
     */
    public function testHelmetClaimsItselfOnBruteKillWhenAccepted(): void {
        $color = $this->getActivePlayerColor();

        $helmet = "card_equip_1_21";
        $nextCard = "card_equip_1_17"; // Throwing Axes
        $this->seedDeck("deck_equip_$color", [$helmet, $nextCard]);

        $bruteHex = "hex_12_8";
        $this->game->tokens->moveToken($this->heroId, "hex_11_8");
        $this->game->getMonster("monster_brute_1")->moveTo($bruteHex, "");

        $xpBefore = $this->countXp();

        // Brute health=3 — dealDamage(3) kills it, fires TMonsterKilled.
        $this->game->machine->push("dealDamage", $color, ["target" => $bruteHex, "count" => 3]);
        $this->game->machine->dispatchAll();

        // Optional ?(blockXp:gainEquip) inside the quest_r pops a confirm prompt — accept it.
        $this->confirmCardEffect();
        $this->game->machine->dispatchAll();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_brute_1"), "Brute should be killed");
        $this->assertEquals("tableau_$color", $this->tokenLocation($helmet), "Helmet should land on tableau on brute kill");
        $this->assertEquals($xpBefore, $this->countXp(), "blockXp should suppress the brute's XP reward");

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Throwing Axes should surface as the new deck-top");
    }

    /**
     * Player declines the optional claim — Helmet stays in deck, XP awarded normally.
     */
    public function testHelmetDeclinedKeepsXp(): void {
        $color = $this->getActivePlayerColor();

        $helmet = "card_equip_1_21";
        $nextCard = "card_equip_1_17";
        $this->seedDeck("deck_equip_$color", [$helmet, $nextCard]);

        $this->game->tokens->moveToken($this->heroId, "hex_11_8");
        $this->game->getMonster("monster_skeleton_1")->moveTo("hex_12_8", "");

        $baseXp = $this->game->getMonster("monster_skeleton_1")->getXpReward();
        $xpBefore = $this->countXp();

        // Skeleton health=3 — dealDamage(3) kills it, fires TMonsterKilled.
        $this->game->machine->push("dealDamage", $color, ["target" => "hex_12_8", "count" => 3]);
        $this->game->machine->dispatchAll();

        // Decline the optional ?(blockXp:gainEquip) prompt.
        $this->skip();
        $this->game->machine->dispatchAll();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_skeleton_1"));
        $this->assertEquals("deck_equip_$color", $this->tokenLocation($helmet), "Helmet stays in deck on decline");
        $this->assertEquals($xpBefore + $baseXp, $this->countXp(), "XP awarded when player declines the claim");
    }

    /**
     * Negative path: killing a goblin (tribe=goblin doesn't match brute|skeleton)
     * voids the chain before the optional prompt — no choice presented, Helmet
     * stays in deck, XP awarded normally.
     */
    public function testHelmetStaysInDeckWhenKillIsGoblin(): void {
        $color = $this->getActivePlayerColor();

        $helmet = "card_equip_1_21";
        $nextCard = "card_equip_1_17";
        $this->seedDeck("deck_equip_$color", [$helmet, $nextCard]);

        $this->game->tokens->moveToken($this->heroId, "hex_11_8");
        $this->game->getMonster("monster_goblin_1")->moveTo("hex_12_8", "");

        $xpBefore = $this->countXp();

        $this->game->machine->push("dealDamage", $color, ["target" => "hex_12_8", "count" => 2]);
        $this->game->machine->dispatchAll();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_goblin_1"));
        $this->assertEquals("deck_equip_$color", $this->tokenLocation($helmet), "Helmet stays in deck — goblin doesn't match brute|skeleton");
        $this->assertEquals($xpBefore + 1, $this->countXp(), "Goblin's 1 XP awarded normally when chain voids");
    }

    /**
     * Leather Purse (card_equip_1_19): quest_on=TMonsterKilled,
     * quest_r=killed(trollkin):?(2spawn(brute):gainEquip).
     *
     * Trigger-driven optional claim — predicate matches any trollkin (goblin,
     * brute, troll). On accept, 2 brutes spawn adjacent to the hero (the "cost"
     * — bag's previous owners want it back) and Leather Purse lands on tableau.
     * No blockXp in the chain — the kill XP is awarded either way.
     */
    public function testLeatherPurseClaimsItselfOnTrollkinKillSpawning2Brutes(): void {
        $color = $this->getActivePlayerColor();

        $purse = "card_equip_1_19";
        $nextCard = "card_equip_1_17"; // Throwing Axes — surfaces after Purse is claimed
        $this->seedDeck("deck_equip_$color", [$purse, $nextCard]);

        $heroHex = "hex_11_8";
        $goblinHex = "hex_12_8";
        $this->game->tokens->moveToken($this->heroId, $heroHex);
        $this->game->getMonster("monster_goblin_1")->moveTo($goblinHex, "");

        $brutesBefore = count($this->game->tokens->getTokensOfTypeInLocation("monster_brute", "supply_monster"));
        $this->assertGreaterThanOrEqual(2, $brutesBefore, "Need at least 2 brutes in supply for the spawn cost");

        $this->game->machine->push("dealDamage", $color, ["target" => $goblinHex, "count" => 2]);
        $this->game->machine->dispatchAll();

        // Optional ?(2spawn(brute):gainEquip) pops a confirm prompt — accept it.
        $this->confirmCardEffect();
        $this->game->machine->dispatchAll();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_goblin_1"), "Goblin should be killed");
        $this->assertEquals("tableau_$color", $this->tokenLocation($purse), "Leather Purse should land on tableau on trollkin kill");

        $brutesAfter = count($this->game->tokens->getTokensOfTypeInLocation("monster_brute", "supply_monster"));
        $this->assertEquals($brutesBefore - 2, $brutesAfter, "2 brutes should leave supply (spawned next to hero)");

        // Both spawned brutes should be on hexes adjacent to the hero.
        $adjBrutes = 0;
        foreach ($this->game->hexMap->getAdjacentHexes($heroHex) as $hex) {
            $charId = $this->game->hexMap->getCharacterOnHex($hex);
            if ($charId !== null && str_starts_with($charId, "monster_brute")) {
                $adjBrutes++;
            }
        }
        $this->assertEquals(2, $adjBrutes, "Both brutes should spawn on hexes adjacent to the hero");

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Throwing Axes should surface as the new deck-top");
    }

    /** Player declines the optional claim — no spawns, card stays in deck. */
    public function testLeatherPurseDeclinedKeepsCardInDeck(): void {
        $color = $this->getActivePlayerColor();

        $purse = "card_equip_1_19";
        $nextCard = "card_equip_1_17";
        $this->seedDeck("deck_equip_$color", [$purse, $nextCard]);

        $this->game->tokens->moveToken($this->heroId, "hex_11_8");
        $this->game->getMonster("monster_goblin_1")->moveTo("hex_12_8", "");

        $brutesBefore = count($this->game->tokens->getTokensOfTypeInLocation("monster_brute", "supply_monster"));

        $this->game->machine->push("dealDamage", $color, ["target" => "hex_12_8", "count" => 2]);
        $this->game->machine->dispatchAll();

        // Decline the optional ?(2spawn(brute):gainEquip) prompt.
        $this->skip();
        $this->game->machine->dispatchAll();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_goblin_1"));
        $this->assertEquals("deck_equip_$color", $this->tokenLocation($purse), "Leather Purse stays in deck on decline");
        $this->assertEquals(
            $brutesBefore,
            count($this->game->tokens->getTokensOfTypeInLocation("monster_brute", "supply_monster")),
            "No brutes should spawn when player declines"
        );
    }

    /**
     * Negative path: killed(trollkin) fails on a firehorde monster (sprite),
     * so the chain voids before the optional prompt — no choice, card stays.
     */
    public function testLeatherPurseStaysInDeckWhenKillIsNotTrollkin(): void {
        $color = $this->getActivePlayerColor();

        $purse = "card_equip_1_19";
        $nextCard = "card_equip_1_17";
        $this->seedDeck("deck_equip_$color", [$purse, $nextCard]);

        $this->game->tokens->moveToken($this->heroId, "hex_11_8");
        $this->game->getMonster("monster_sprite_1")->moveTo("hex_12_8", "");

        $brutesBefore = count($this->game->tokens->getTokensOfTypeInLocation("monster_brute", "supply_monster"));

        $this->game->machine->push("dealDamage", $color, ["target" => "hex_12_8", "count" => 5]);
        $this->game->machine->dispatchAll();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_sprite_1"));
        $this->assertEquals("deck_equip_$color", $this->tokenLocation($purse), "Leather Purse stays in deck — sprite isn't trollkin");
        $this->assertEquals(
            $brutesBefore,
            count($this->game->tokens->getTokensOfTypeInLocation("monster_brute", "supply_monster")),
            "No brutes spawn when chain voids"
        );
    }

    /**
     * Trollbane (card_equip_1_22): quest_on=TMonsterKilled,
     * quest_r=killed(trollkin):counter(countMonsterXp):gainTracker,check('countTracker>=5'):gainEquip.
     *
     * Counter quest where each kill bumps the tracker by the killed monster's XP
     * (goblin=1, brute=2, troll=3). Reaches the threshold after 1+2+3 = 6 ≥ 5.
     * Uses the new `countMonsterXp` Math term (Game::evaluateTerm).
     */
    public function testTrollbaneLandsOnTableauAfterCollecting5XpFromTrollkin(): void {
        $color = $this->getActivePlayerColor();

        $trollbane = "card_equip_1_22";
        $nextCard = "card_equip_1_17"; // Throwing Axes — surfaces after Trollbane is claimed
        $this->seedDeck("deck_equip_$color", [$trollbane, $nextCard]);

        $heroHex = "hex_11_8";
        $killHex = "hex_12_8";
        $this->game->tokens->moveToken($this->heroId, $heroHex);

        // Three trollkin kills with increasing XP: 1 + 2 + 3 = 6 ≥ 5.
        $kills = [
            ["monster_goblin_1", 2, 1], // dealDamage to kill HP=2; XP=1 → tracker after = 1
            ["monster_brute_1", 3, 3],  //                  HP=3; XP=2 → tracker after = 3
            ["monster_troll_1", 7, 6],  //                  HP=7; XP=3 → tracker after = 6 (claim fires)
        ];

        foreach ($kills as $idx => [$monsterId, $hp, $expectedTracker]) {
            $this->game->getMonster($monsterId)->moveTo($killHex, "");
            $this->game->machine->push("dealDamage", $color, ["target" => $killHex, "count" => $hp]);
            $this->game->machine->dispatchAll();
            $this->assertEquals("supply_monster", $this->tokenLocation($monsterId), "$monsterId should be killed");
        }

        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation($trollbane),
            "Trollbane should land on tableau after collecting 6 XP from trollkin kills"
        );
        $this->assertEquals(0, $this->countTokens("crystal_red", $trollbane), "gainEquip should sweep tracker crystals back to supply");

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$color");
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Throwing Axes should surface as the new deck-top");
    }

    /**
     * Negative path: killing a non-trollkin monster (firehorde sprite) should
     * NOT bump the tracker — killed(trollkin) voids the chain.
     */
    public function testTrollbaneIgnoresNonTrollkinKills(): void {
        $color = $this->getActivePlayerColor();

        $trollbane = "card_equip_1_22";
        $nextCard = "card_equip_1_17";
        $this->seedDeck("deck_equip_$color", [$trollbane, $nextCard]);

        $this->game->tokens->moveToken($this->heroId, "hex_11_8");
        $this->game->getMonster("monster_sprite_1")->moveTo("hex_12_8", "");

        $this->game->machine->push("dealDamage", $color, ["target" => "hex_12_8", "count" => 5]);
        $this->game->machine->dispatchAll();

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_sprite_1"));
        $this->assertEquals(
            "deck_equip_$color",
            $this->tokenLocation($trollbane),
            "Trollbane stays in deck — sprite isn't trollkin"
        );
        $this->assertEquals(0, $this->countTokens("crystal_red", $trollbane), "No tracker crystal added on non-trollkin kill");
    }
}
