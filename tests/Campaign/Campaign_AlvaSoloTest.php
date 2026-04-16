<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration test: Solo Alva campaign.
 * Focuses on card_equip_2_15 (Alva's First Bow): passive main weapon,
 * grants +1 strength and attack range 2.
 */
class Campaign_AlvaSoloTest extends CampaignBaseTest {
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

    // --- Alva's First Bow (card_equip_2_15) ---

    public function testFirstBowOnTableauAtSetup(): void {
        $color = $this->getActivePlayerColor();
        $this->assertEquals(
            "tableau_$color",
            $this->tokenLocation("card_equip_2_15"),
            "Alva's First Bow should be on Alva's tableau at game start"
        );
    }

    public function testFirstBowGrantsStrengthBonus(): void {
        // Alva hero card I: strength=2, First Bow: +1 → 3
        $hero = $this->game->getHeroById($this->heroId);
        $this->assertEquals(3, $hero->getAttackStrength());
    }

    public function testFirstBowGrantsAttackRange2(): void {
        $hero = $this->game->getHeroById($this->heroId);
        $this->assertEquals(2, $hero->getAttackRange());
    }

    public function testWithoutFirstBowRangeReturnsTo1(): void {
        $this->game->tokens->moveToken("card_equip_2_15", "limbo");
        $hero = $this->game->getHeroById($this->heroId);
        $hero->recalcTrackers();
        $this->assertEquals(1, $hero->getAttackRange());
        $this->assertEquals(2, $hero->getAttackStrength()); // hero card only
    }

    public function testFirstBowHasNoUseCardRule(): void {
        // Passive — useEquipment should not offer First Bow as a valid target
        $this->game->tokens->moveToken("card_equip_2_15", "tableau_" . $this->getActivePlayerColor());
        $this->assertNotValidTarget("card_equip_2_15");
    }

    // --- Elven Arrows (card_equip_2_24) ---

    public function testElvenArrowsGrantsStrengthBonus(): void {
        // Alva hero card I: 2, First Bow: +1, Elven Arrows: +2 → 5
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_equip_2_24", "tableau_$color");
        $hero = $this->game->getHeroById($this->heroId);
        $hero->recalcTrackers();
        $this->assertEquals(5, $hero->getAttackStrength());
    }

    public function testElvenArrowsHasNoUseCardRule(): void {
        // Passive — useEquipment should not offer Elven Arrows as a valid target
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_equip_2_24", "tableau_$color");
        $this->assertNotValidTarget("card_equip_2_24");
    }

    // --- Throwing Darts (card_equip_2_17) ---
    // r=costDamage:3roll(adj), durability 2: pay 1 durability → roll 3 dice vs adjacent monster

    private function placeAdjacentGoblin(string $goblinId = "monster_goblin_20"): string {
        // Move Alva out of Grimheim (heroes can't fight from inside the Grimheim location),
        // then place a goblin on the first non-Grimheim plains/forest neighbor.
        $heroHex = "hex_7_9"; // plains, outside Grimheim
        $this->assertFalse($this->game->hexMap->isInGrimheim($heroHex), "$heroHex should not be Grimheim");
        $this->game->tokens->moveToken($this->heroId, $heroHex);

        $neighbors = $this->game->hexMap->getAdjacentHexes($heroHex);
        foreach ($neighbors as $hex) {
            if ($this->game->hexMap->isInGrimheim($hex)) {
                continue;
            }
            $terrain = $this->game->material->getRulesFor($hex, "terrain", "");
            if ($terrain === "plains" || $terrain === "forest") {
                $this->game->getMonster($goblinId)->moveTo($hex, "");

                return $hex;
            }
        }
        $this->fail("No plains/forest neighbor found for $heroHex");
    }

    public function testThrowingDartsOfferedWhenAdjacentMonster(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_equip_2_17", "tableau_$color");
        $this->placeAdjacentGoblin();

        // Turn op inlines useCard targets as direct delegates on the turn prompt
        $this->assertValidTarget("card_equip_2_17");
    }

    public function testThrowingDartsNotOfferedWhenNoAdjacentMonster(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_equip_2_17", "tableau_$color");
        $this->clearMonstersFromMap();

        $this->assertNotValidTarget("card_equip_2_17", "Throwing Darts should not be offered with no adjacent monster");
    }

    public function testThrowingDartsNotOfferedAtMaxDurability(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_equip_2_17", "tableau_$color");
        // Fill up durability (2) — no room for costDamage
        $this->game->effect_moveCrystals($this->heroId, "red", 2, "card_equip_2_17", ["message" => ""]);
        $this->placeAdjacentGoblin();

        $this->assertNotValidTarget("card_equip_2_17", "Throwing Darts should not be offered when full of damage");
    }

    public function testThrowingDartsResolveDealsDamageAndCostsDurability(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_equip_2_17", "tableau_$color");
        $goblin = "monster_goblin_20";
        $this->placeAdjacentGoblin($goblin);

        // Seed all-hit dice (5=hit, 1=miss); 3 dice, all hits
        $this->seedRand([5, 5, 5]);
        // turn op inlines useCard as a delegate — pick the card directly from the turn prompt
        $this->respond("card_equip_2_17");
        $this->respond("1");

        // Durability spent: 1 red crystal on the card
        $this->assertEquals(1, $this->countDamage("card_equip_2_17"));
        // Goblin dies from 3 hits (goblin health=2) and goes to supply
        $this->assertEquals("supply_monster", $this->tokenLocation($goblin));
    }

    // --- Bloodline Crystal (card_equip_2_25) ---
    // r=(3spendMana:2addDamage)/(3spendMana:drawEvent), on=custom
    // Custom class: CardEquip_BloodlineCrystal — routes actionAttack + manual triggers via useCard.

    public function testBloodlineCrystalDrawCardBranchManual(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_equip_2_25", "tableau_$color");
        $this->game->effect_moveCrystals($this->heroId, "green", 3, "card_equip_2_25", ["message" => ""]);

        // Outside an attack action: only the draw branch is viable
        // (addDamage requires dice on display_battle).
        $this->assertValidTarget("card_equip_2_25");
        $this->respond("card_equip_2_25");
        $this->respond("1");
        $this->respond("confirm");

        // 3 mana spent
        $this->assertEquals(0, $this->countTokens("crystal_green", "card_equip_2_25"));
        // Hand grew by 1 (Rest was seeded in the event deck)
        $this->assertEquals(1, $this->countTokens("card_event", "hand_$color"));
    }

    public function testBloodlineCrystalAddDamageBranchDuringAttack(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_equip_2_25", "tableau_$color");
        $this->game->effect_moveCrystals($this->heroId, "green", 3, "card_equip_2_25", ["message" => ""]);

        // Move Alva out of Grimheim and place a brute (health=3) at range 2
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $brute = "monster_brute_1";
        $this->game->getMonster($brute)->moveTo("hex_5_9", "");

        // Seed 3 miss dice so base attack damage = 0; Bloodline Crystal will add 2.
        // Strength=3 (Alva hero 2 + First Bow 1).
        $this->seedRand([1, 1, 1]);
        //$this->respond("actionAttack");
        $this->respond("hex_5_9");

        // The actionAttack trigger offers Bloodline Crystal
        $this->assertOperation("useCard");

        $this->respond("card_equip_2_25");
        $this->respond("choice_2");
        $this->skipIfOp("drawEvent");
        // 3 mana spent (branch A)
        $this->assertEquals(0, $this->countTokens("crystal_green", "card_equip_2_25"));
        // Brute took 2 damage from branch A (base attack missed, so damage = 0 + 2 = 2);
        // brute health=3 so it's still alive
        $this->assertEquals("hex_5_9", $this->tokenLocation($brute));
        $this->assertEquals(2, $this->countDamage($brute));
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

    public function testFirstBowEnablesRangedAttack(): void {
        // Place a goblin 2 hexes from Alva — should be attackable thanks to range 2
        $goblin = "monster_goblin_20";
        $heroStart = $this->tokenLocation($this->heroId);
        // Find a hex 2 away along axis: pick one empty plains hex two rows from hero start
        // Grimheim hex_9_9 → hex_9_7 is 2 away. Move hero to hex_9_9 then place goblin at hex_9_7.
        $this->game->tokens->moveToken($this->heroId, "hex_9_9");
        $this->game->getMonster($goblin)->moveTo("hex_9_7", "");

        // Goblin hex should be a valid attack target at range 2
        $this->assertValidTarget("hex_9_7");
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

    public function testFlexibilityIOfferedOnTurnWhenManaAvailable(): void {
        $this->placeFlexibility(2);
        // Outside of attack — branches 1 (move+1) and 2 (range+1) are viable given 2 mana
        $this->assertValidTarget("card_ability_2_13");
    }

    public function testFlexibilityINotOfferedWhenUnusedButNoMana(): void {
        $this->placeFlexibility(0);
        $this->assertNotValidTarget("card_ability_2_13", "Flexibility I should not be offered with 0 mana");
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

    public function testFlexibilityIOnceUsedCannotBeUsedAgain(): void {
        $this->placeFlexibility(3); // 3 mana → plenty for a second use after spending 1

        $this->game->tokens->moveToken($this->heroId, "hex_9_9");

        $this->respond("card_ability_2_13"); // first use (move branch)

        $this->respond("choice_0"); // move branch

        // Card state=1 (used); should no longer appear as a valid target even though mana remains
        $this->assertEquals(1, $this->game->tokens->getTokenState("card_ability_2_13"));
        $this->assertEquals(2, $this->countTokens("crystal_green", "card_ability_2_13"));
        $this->assertNotValidTarget("card_ability_2_13", "Flexibility I should be spent for the turn");
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

    public function testFlexibilityINotOfferedOutOfAttackForDamageBranch(): void {
        // Only 2 mana, and spendUse branches (move/range) still available, so card is offered —
        // but we want to confirm the addDamage branch specifically is NOT an option outside of attack.
        // Easiest way: start with card already used (state=1) so move/range branches are gated,
        // and no attack is in progress so addDamage branch has no dice → card should not be offered.
        $this->placeFlexibility(2);
        $this->game->tokens->setTokenState("card_ability_2_13", 1); // mark used

        $this->assertNotValidTarget(
            "card_ability_2_13",
            "Used Flexibility I should not be offered outside of attack (addDamage branch needs dice)"
        );
    }
}
