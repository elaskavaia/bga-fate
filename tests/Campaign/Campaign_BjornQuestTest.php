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
        $this->setupGame([1]); // Solo Bjorn — Bone Bane Bow lives in Bjorn's deck
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $bow = "card_equip_1_16";
        $nextCard = "card_equip_1_17"; // Throwing Axes — surfaces after Bone Bane Bow is claimed
        $this->seedDeck("deck_equip_$color", [$bow, $nextCard]);
        $this->assertEquals($bow, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Place Bjorn on a Nailfare hex so the in(Nailfare) gate passes.
        $nailfareHex = "hex_16_5";
        $this->game->tokens->moveToken($heroId, $nailfareHex);

        $this->game->machine->push("completeQuest", $color);
        $this->game->machine->dispatchAll();

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
        $this->setupGame([1]); // Solo Bjorn — Black Arrows lives in Bjorn's deck
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $blackArrows = "card_equip_1_20";
        $nextCard = "card_equip_1_17"; // Throwing Axes — placeholder behind Black Arrows
        $this->seedDeck("deck_equip_$color", [$blackArrows, $nextCard]);
        $this->assertEquals($blackArrows, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Place Bjorn on the Robber Camp hex so the in(RobberCamp) gate passes.
        $robberCampHex = "hex_5_11";
        $this->game->tokens->moveToken($heroId, $robberCampHex);

        $this->game->machine->push("completeQuest", $color);
        $this->game->machine->dispatchAll();

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
        $this->setupGame([1]); // Solo Bjorn — Home Sewn Cape lives in Bjorn's deck
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $cape = "card_equip_1_24";
        $nextCard = "card_equip_1_17"; // Throwing Axes — placeholder behind Cape
        $this->seedDeck("deck_equip_$color", [$cape, $nextCard]);
        $this->assertEquals($cape, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Plains hex outside Grimheim, no adjacent monsters (clearMonstersFromMap above).
        $heroHex = "hex_7_9";
        $this->game->tokens->moveToken($heroId, $heroHex);

        $this->game->machine->push("completeQuest", $color);
        $this->game->machine->dispatchAll();

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
        $this->setupGame([1]);
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $cape = "card_equip_1_24";
        $nextCard = "card_equip_1_17";
        $this->seedDeck("deck_equip_$color", [$cape, $nextCard]);

        $heroHex = "hex_7_9";
        $this->game->tokens->moveToken($heroId, $heroHex);
        $this->game->getMonster("monster_goblin_1")->moveTo("hex_7_8", "");

        $this->game->machine->push("completeQuest", $color);
        $this->game->machine->dispatchAll();

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
        $this->setupGame([1]); // Solo Bjorn — Home Sewn Tunic lives in Bjorn's deck
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        $tunic = "card_equip_1_23";
        $nextCard = "card_equip_1_17"; // Throwing Axes — placeholder behind Tunic
        $this->seedDeck("deck_equip_$color", [$tunic, $nextCard]);
        $this->assertEquals($tunic, $this->game->tokens->getTokenOnTop("deck_equip_$color")["key"]);

        // Seed Bjorn with 1 yellow crystal (XP) on tableau so the spendXp cost can be paid.
        $this->game->effect_moveCrystals($heroId, "yellow", 1, "tableau_$color", ["message" => ""]);
        $xpBefore = $this->countTokens("crystal_yellow", "tableau_$color");
        $this->assertGreaterThanOrEqual(1, $xpBefore, "Need at least 1 XP on tableau to pay the quest cost");

        $this->game->machine->push("completeQuest", $color);
        $this->game->machine->dispatchAll();

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
}
