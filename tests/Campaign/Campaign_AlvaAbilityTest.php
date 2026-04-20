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
}
