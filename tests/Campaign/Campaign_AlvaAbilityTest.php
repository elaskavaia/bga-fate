<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Alva's ability and hero cards.
 * Scripts full game turns using the harness GameDriver in-process.
 */
class Campaign_AlvaAbilityTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([2]); // Solo Alva
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());

        $this->seedDeck("deck_monster_yellow", ["card_monster_7", "card_monster_8", "card_monster_9", "card_monster_10"]);
        $this->seedDeck("deck_event_" . $this->getActivePlayerColor(), [
            "card_event_2_31_1", // Rest
            "card_event_2_31_2", // Rest
        ]);

        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    // --- Alva Hero I (card_hero_2_1) ---
    // "End your move action in a forest to add 1 mana [MANA] to any card."
    // Listens on Trigger::ActionMove; queues ?gainMana when Alva ends the move action in a forest.

    public function testAlvaHeroIAddsManaWhenMoveActionEndsInForest(): void {
        // Hail of Arrows I (card_ability_2_3, mana=1) is on Alva's starting tableau and is the
        // sole mana-target card → Op_gainMana auto-picks it without prompting.
        $hailId = "card_ability_2_3";
        $manaBefore = $this->countTokens("crystal_green", $hailId);

        // Place Alva on a plains hex with a forest neighbor (hex_5_9 plains → hex_5_8 forest)
        $this->game->tokens->moveToken($this->heroId, "hex_5_9");

        $this->respond("hex_5_8"); // turn op inlines actionMove targets, so picking the hex directly works

        // Trigger fired, ?gainMana auto-resolved on the sole valid target — back at PlayerTurn
        // with the second action available.
        $this->assertEquals("PlayerTurn", $this->getStateArgs()["name"]);
        $this->assertEquals("hex_5_8", $this->tokenLocation($this->heroId));
        $this->assertEquals($manaBefore + 1, $this->countTokens("crystal_green", $hailId));
    }

    // --- Alva Hero II (card_hero_2_2) ---
    // "End any movement in a forest to add 1 mana [MANA] to any card."
    // Listens on Trigger::Move (any movement, not just action move).

    public function testAlvaHeroIIAddsManaWhenMoveActionEndsInForest(): void {
        $color = $this->getActivePlayerColor();
        // Swap Alva Hero I → Alva Hero II on tableau
        $this->game->tokens->moveToken("card_hero_2_1", "limbo");
        $this->game->tokens->moveToken("card_hero_2_2", "tableau_$color");

        $hailId = "card_ability_2_3";
        $manaBefore = $this->countTokens("crystal_green", $hailId);

        $this->game->tokens->moveToken($this->heroId, "hex_5_9");

        $this->respond("hex_5_8");

        // Hero II listens on Trigger::Move (any movement). Op_actionMove queues Op_move which
        // emits Trigger::Move on completion → ?gainMana auto-resolves on Hail of Arrows I.
        $this->assertEquals("PlayerTurn", $this->getStateArgs()["name"]);
        $this->assertEquals("hex_5_8", $this->tokenLocation($this->heroId));
        $this->assertEquals($manaBefore + 1, $this->countTokens("crystal_green", $hailId));
    }

    // --- Flexibility I (card_ability_2_13) ---
    // r=(spendUse:1spendMana:gainAtt_move)/(spendUse:2spendMana:gainAtt_range)/(on(TActionAttack):2spendMana:2addDamage)
    // 1[MANA]: Move +1 (once/turn) | 2[MANA]: Range +1 this turn (once/turn) | 2[MANA]: +2 dmg mid-attack (per-attack)

    private function placeFlexibility(int $mana = 2): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_2_13";
        $this->game->tokens->moveToken($cardId, "tableau_$color");
        if ($mana > 0) {
            $this->game->effect_moveCrystals($this->heroId, "green", $mana, $cardId, ["message" => ""]);
        }
    }

    public function testFlexibilityIMoveBranchGainsExtraMove(): void {
        $this->placeFlexibility(1); // only enough for branch 1 (1 mana)

        $hero = $this->game->getHeroById($this->heroId);
        $baseMoves = $hero->getNumberOfMoves();

        // Park Alva on plains far from any forest so Alva Hero I doesn't fire mid-test.
        $this->game->tokens->moveToken($this->heroId, "hex_9_9");

        $this->respond("card_ability_2_13");
        // Only one branch is viable (1 mana, not in attack) → or op auto-collapses
        $this->respond("choice_0");

        // Mana drained and tracker_move incremented
        $this->assertEquals(0, $this->countTokens("crystal_green", "card_ability_2_13"));
        $this->assertEquals($baseMoves + 1, $hero->getNumberOfMoves());
        // Card marked used
        $this->assertEquals(1, $this->game->tokens->getTokenState("card_ability_2_13"));
    }

    public function testFlexibilityIRangeBranchGainsAttackRange(): void {
        $this->placeFlexibility(2); // enough for branch 2 (2 mana)

        $hero = $this->game->getHeroById($this->heroId);
        $baseRange = $hero->getAttackRange();

        $this->game->tokens->moveToken($this->heroId, "hex_9_9");

        $this->respond("card_ability_2_13");
        // Two branches viable (1-mana move and 2-mana range) → pick branch 1 (range)

        $this->respond("choice_1"); // range branch

        $this->assertEquals(0, $this->countTokens("crystal_green", "card_ability_2_13"));
        $this->assertEquals($baseRange + 1, $hero->getAttackRange());
        $this->assertEquals(1, $this->game->tokens->getTokenState("card_ability_2_13"));
    }

    public function testFlexibilityIAddDamageBranchDuringAttack(): void {
        $this->placeFlexibility(2);

        // Move Alva out of Grimheim and place a brute (health=3) at range 2
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $brute = "monster_brute_1";
        $this->game->getMonster($brute)->moveTo("hex_5_9", "");

        // Seed 3 miss dice so base attack damage = 0; Flexibility I adds 2.
        $this->seedRand([1, 1, 1]);
        // turn op inlines attack-target hexes directly — pick the brute's hex
        $this->respond("hex_5_9");
        // Mid-attack trigger offers Flexibility I via on(TActionAttack)
        $this->respond("card_ability_2_13");
        $this->respond("choice_2");

        // 2 mana spent; brute took 2 damage (base=0 + addDamage 2)
        $this->assertEquals(0, $this->countTokens("crystal_green", "card_ability_2_13"));
        $this->assertEquals("hex_5_9", $this->tokenLocation($brute));
        $this->assertEquals(2, $this->countDamage($brute));
        // addDamage branch has no spendUse — card state should still be 0
        $this->assertEquals(0, $this->game->tokens->getTokenState("card_ability_2_13"));
    }

    // --- Flexibility II (card_ability_2_14) ---
    // Superset of Flexibility I with a 4th branch: 2[MANA]: Draw 1 card.
    // r=(spendUse:1spendMana:gainAtt_move)/(spendUse:2spendMana:gainAtt_range)/(on(TActionAttack):2spendMana:2addDamage)/(spendUse:2spendMana:drawEvent)
    // Branches 1-3 are already covered by Flexibility I tests; this test covers the new draw branch only.

    public function testFlexibilityIIDrawBranch(): void {
        $color = $this->getActivePlayerColor();
        // Swap Flexibility I → Flexibility II
        $this->game->tokens->moveToken("card_ability_2_13", "limbo");
        $cardId = "card_ability_2_14";
        $this->game->tokens->moveToken($cardId, "tableau_$color");
        $this->game->effect_moveCrystals($this->heroId, "green", 2, $cardId, ["message" => ""]);

        // Seed event deck so drawEvent has a card to give
        $restCard = "card_event_2_31_1";
        $this->seedDeck("deck_event_$color", [$restCard]);

        // Park Alva on plains far from any forest so Alva Hero I doesn't fire mid-test
        $this->game->tokens->moveToken($this->heroId, "hex_9_9");

        $this->assertValidTarget($cardId);
        $this->respond($cardId);
        // Branches 0=move, 1=range, 2=addDamage (filtered out, not in attack), 3=drawEvent
        $this->respond("choice_3");
        $this->respond("confirm"); // drawEvent requires confirmation

        // Card marked used, 2 mana consumed, Rest card drawn into hand
        $this->assertEquals(1, $this->game->tokens->getTokenState($cardId));
        $this->assertEquals(0, $this->countTokens("crystal_green", $cardId));
        $this->assertEquals("hand_$color", $this->tokenLocation($restCard));
    }

    // --- Snipe I (card_ability_2_11) ---
    // r=spendUse:2roll(inRange), no trigger — manual free-action, once per turn.
    // Rolls 2 attack dice against a monster within Alva's attack range (2 via First Bow).

    public function testSnipeIRolls2DiceAtMonsterInRange(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_2_11";
        $this->game->tokens->moveToken($cardId, "tableau_$color", 0);

        // Alva at hex_7_9, brute (health=3) at hex_5_9 (range 2, inside First Bow's range).
        // Brute survives 2 damage so crystals stay on the token.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $brute = "monster_brute_1";
        $this->game->getMonster($brute)->moveTo("hex_5_9", "");

        // 2 dice, both hits.
        $this->seedRand([5, 5]);

        $this->assertValidTarget($cardId);
        $this->respond($cardId);
        $this->respond("hex_5_9"); // roll sub-op prompts for target hex

        // 2 hits × 1 damage each → brute takes 2 damage
        $this->assertEquals(2, $this->countDamage($brute));
        // Card marked used (spendUse flipped state to 1)
        $this->assertEquals(1, $this->game->tokens->getTokenState($cardId));
    }

    // --- Snipe II (card_ability_2_12) ---
    // r=spendUse:5roll(inRange), no trigger — same pattern as Snipe I but 5 dice.

    public function testSnipeIIRolls5DiceAtMonsterInRange(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_2_12";
        $this->game->tokens->moveToken($cardId, "tableau_$color", 0);

        // Troll (health=6) survives 5 hits so damage sticks to the token.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_5_9", "");

        $this->seedRand([5, 5, 5, 5, 5]);

        $this->assertValidTarget($cardId);
        $this->respond($cardId);
        $this->respond("hex_5_9");

        $this->assertEquals(5, $this->countDamage($troll));
        $this->assertEquals(1, $this->game->tokens->getTokenState($cardId));
    }

    // --- Hail of Arrows I (card_ability_2_3) ---
    // r=spendUse:c_hail, no trigger — manual free-action, once per turn.
    // 3[MANA]: deal 1 damage to up to 3 different monsters within attack range (fixed cost).

    public function testHailOfArrowsIDealsOneDamageTo3Targets(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_2_3"; // Hail I is on Alva's starting tableau with 1 mana by default
        $currentMana = $this->countTokens("crystal_green", $cardId);
        $this->game->effect_moveCrystals($this->heroId, "green", 3 - $currentMana, $cardId, ["message" => ""]);
        $this->assertEquals(3, $this->countTokens("crystal_green", $cardId));

        // Alva at hex_7_9; 3 brutes (health=3, survive 1 damage) adjacent.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $this->game->getMonster("monster_brute_1")->moveTo("hex_7_8", "");
        $this->game->getMonster("monster_brute_2")->moveTo("hex_6_9", "");
        $this->game->getMonster("monster_brute_3")->moveTo("hex_8_9", "");

        $this->assertValidTarget($cardId);
        $this->respond($cardId);
        $this->confirmCardEffect(); // useCard paygain asks to confirm before resolving r
        // c_hail multi-select prompt: pick all 3 hexes at once.
        $this->respond(["hex_7_8", "hex_6_9", "hex_8_9"]);

        // Each brute took 1 damage; all 3 mana spent; card marked used.
        $this->assertEquals(1, $this->countDamage("monster_brute_1"));
        $this->assertEquals(1, $this->countDamage("monster_brute_2"));
        $this->assertEquals(1, $this->countDamage("monster_brute_3"));
        $this->assertEquals(0, $this->countTokens("crystal_green", $cardId));
        $this->assertEquals(1, $this->game->tokens->getTokenState($cardId));
    }

    // --- Hail of Arrows II (card_ability_2_4) ---
    // r=spendUse:c_hailII, no trigger — manual free-action, once per turn.
    // 1-4[MANA]: deal 1 damage to N different monsters within attack range (pays N mana).

    public function testHailOfArrowsIISpendsManaEqualToTargetsPicked(): void {
        $color = $this->getActivePlayerColor();
        // Swap Hail I → Hail II on tableau.
        $this->game->tokens->moveToken("card_ability_2_3", "limbo");
        $cardId = "card_ability_2_4";
        $this->game->tokens->moveToken($cardId, "tableau_$color", 0);
        $this->game->effect_moveCrystals($this->heroId, "green", 4, $cardId, ["message" => ""]);

        // 4 brutes adjacent to Alva; we'll only hit 2 of them.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $this->game->getMonster("monster_brute_1")->moveTo("hex_7_8", "");
        $this->game->getMonster("monster_brute_2")->moveTo("hex_6_9", "");
        $this->game->getMonster("monster_brute_3")->moveTo("hex_8_9", "");
        $this->game->getMonster("monster_brute_4")->moveTo("hex_7_10", "");

        $this->respond($cardId);
        $this->confirmCardEffect();
        $this->respond(["hex_7_8", "hex_8_9"]);

        // Only 2 mana spent (= targets picked); only brute_1 and brute_3 damaged.
        $this->assertEquals(2, $this->countTokens("crystal_green", $cardId));
        $this->assertEquals(1, $this->countDamage("monster_brute_1"));
        $this->assertEquals(0, $this->countDamage("monster_brute_2"));
        $this->assertEquals(1, $this->countDamage("monster_brute_3"));
        $this->assertEquals(0, $this->countDamage("monster_brute_4"));
    }

    // --- Suppressive Fire I (card_ability_2_9) ---
    // r=c_supfire(inRange3,'rank<=2'), on=TMonsterMove.
    // On monster turn, once per turn, pick a rank 1/2 monster within range 3 — it cannot move.

    public function testSuppressiveFireIPreventsRank1MonsterMovement(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_2_9";
        $this->game->tokens->moveToken($cardId, "tableau_$color", 0);

        // Goblin (rank 1) within range 3 of Alva's starting hex.
        $goblin = "monster_goblin_20";
        $goblinHex = "hex_7_8";
        $this->game->getMonster($goblin)->moveTo($goblinHex, "");

        // Burn the player turn → monster turn.
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skip();
        $this->skipIfOp("drawEvent");

        // useCard prompt for Suppressive Fire I on TMonsterMove.
        $this->assertOperation("useCard");
        $this->assertValidTarget($cardId);
        $this->respond($cardId);

        // c_supfire prompts for the target hex.
        $this->assertOperation("c_supfire");
        $this->respond($goblinHex);

        // Goblin stayed put; stun marker remains for the "can't pick again next turn" rule.
        $this->assertEquals($goblinHex, $this->tokenLocation($goblin));
        $this->assertCount(1, $this->game->tokens->getTokensOfTypeInLocation("stunmarker", $goblin));
    }

    // --- Suppressive Fire II (card_ability_2_10) ---
    // r=c_supfire(inRange3), on=TMonsterMove. No rank filter — can stun any monster in range 3.

    public function testSuppressiveFireIIPreventsRank3MonsterMovement(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_2_10";
        $this->game->tokens->moveToken($cardId, "tableau_$color", 0);

        // Troll (rank 3) within range 3 — Suppressive Fire I would reject this, II accepts it.
        $troll = "monster_troll_1";
        $trollHex = "hex_7_8";
        $this->game->getMonster($troll)->moveTo($trollHex, "");

        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skip();
        $this->skipIfOp("drawEvent");

        $this->assertOperation("useCard");
        $this->assertValidTarget($cardId);
        $this->respond($cardId);

        $this->assertOperation("c_supfire");
        $this->assertValidTarget($trollHex);
        $this->respond($trollHex);

        $this->assertEquals($trollHex, $this->tokenLocation($troll));
        $this->assertCount(1, $this->game->tokens->getTokensOfTypeInLocation("stunmarker", $troll));
    }

    // --- Treetreader II (card_ability_2_6) ---
    // r=(in(forest):move)/move(forest), on=custom — manual free-action.
    // In forest: move to any adjacent hex. Outside: move into an adjacent forest hex.
    // Passive: each time the hero moves into a forest area, heal 1 damage (onStep handler).

    public function testTreetreaderIIHealsWhenMovingIntoForest(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_2_6";
        $this->game->tokens->moveToken($cardId, "tableau_$color", 0);

        // Alva on plains (hex_5_9), 2 damage on hero. Adjacent forest: hex_5_8.
        $this->game->tokens->moveToken($this->heroId, "hex_5_9");
        $this->game->effect_moveCrystals("supply_red", "red", 2, $this->heroId, ["message" => ""]);
        $this->assertEquals(2, $this->countDamage($this->heroId));

        $this->assertValidTarget($cardId);
        $this->respond($cardId);
        // Branches: 0=in(forest):move (not applicable here), 1=move(forest). Only branch 1 viable.
        $this->respond("choice_1");
        $this->respond("hex_5_8");

        $this->assertEquals("hex_5_8", $this->tokenLocation($this->heroId));
        $this->assertEquals(1, $this->countDamage($this->heroId));
    }

    public function testTreetreaderIIDoesNotHealWhenLeavingForest(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_2_6";
        $this->game->tokens->moveToken($cardId, "tableau_$color", 0);

        // Alva already in forest (hex_5_8) with 2 damage; moving out to plains (hex_5_9).
        $this->game->tokens->moveToken($this->heroId, "hex_5_8");
        $this->game->effect_moveCrystals("supply_red", "red", 2, $this->heroId, ["message" => ""]);
        $this->assertEquals(2, $this->countDamage($this->heroId));

        $this->assertValidTarget($cardId);
        $this->respond($cardId);
        // Branches: 0=in(forest):move (applicable, Alva is in forest), 1=move(forest). Pick 0.
        $this->respond("choice_0");
        $this->respond("hex_5_9");

        // Hero stepped into plains, not forest → heal guard returns early, no damage removed.
        $this->assertEquals("hex_5_9", $this->tokenLocation($this->heroId));
        $this->assertEquals(2, $this->countDamage($this->heroId));
    }

    // --- Starsong I (card_ability_2_7) ---
    // r=drawEvent, on=TTurnEnd — at end of your turn, draw 1 additional card.

    public function testStarsongIDrawsExtraCardAtTurnEnd(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_2_7";
        $this->game->tokens->moveToken($cardId, "tableau_$color");

        // Seed the event deck so the trigger's drawEvent has a specific card to draw.
        $extraCard = "card_event_2_31_1"; // Rest
        $this->seedDeck("deck_event_$color", [$extraCard]);

        // Burn a turn — practice + focus → end turn fires TTurnEnd.
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skip();

        // TTurnEnd → useCard prompt for Starsong I (confirm=true even with single card).
        $this->assertOperation("useCard");
        $this->assertValidTarget($cardId);
        $this->respond($cardId);

        // drawEvent prompts for confirm before pulling the card.
        $this->respond("confirm");

        // Starsong's seeded card landed in hand.
        $this->assertEquals("hand_$color", $this->tokenLocation($extraCard));
    }

    // --- Starsong II (card_ability_2_8) ---
    // r=2drawEvent, on=TTurnEnd — draw 2 cards at turn end; hand limit becomes 5.
    // (Hand-limit assertion is covered by HeroTest::testStarsongIIRaisesHandLimitTo5.)

    public function testStarsongIIDrawsTwoExtraCardsAtTurnEnd(): void {
        $color = $this->getActivePlayerColor();
        // Swap Starsong I → Starsong II so only the II trigger fires.
        $this->game->tokens->moveToken("card_ability_2_7", "limbo");
        $cardId = "card_ability_2_8";
        $this->game->tokens->moveToken($cardId, "tableau_$color");

        $first = "card_event_2_31_1"; // Rest
        $second = "card_event_2_31_2"; // Rest
        $this->seedDeck("deck_event_$color", [$first, $second]);

        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skip();

        $this->assertOperation("useCard");
        $this->assertValidTarget($cardId);
        $this->respond($cardId);

        // 2drawEvent loops internally on a single confirm — both cards drawn in one resolve.
        $this->respond("confirm");

        $this->assertEquals("hand_$color", $this->tokenLocation($first));
        $this->assertEquals("hand_$color", $this->tokenLocation($second));
    }
}
