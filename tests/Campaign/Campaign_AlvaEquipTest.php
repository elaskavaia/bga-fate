<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Alva's equipment cards.
 * Scripts full game turns using the harness GameDriver in-process.
 */
class Campaign_AlvaEquipTest extends CampaignBaseTest {
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
    // Passive main weapon: +1 strength, attack range 2.

    public function testFirstBowGrantsStrengthAndRangeAndIsNotUsable(): void {
        $color = $this->getActivePlayerColor();
        // On tableau at game start
        $this->assertEquals("tableau_$color", $this->tokenLocation("card_equip_2_15"));

        $hero = $this->game->getHeroById($this->heroId);
        // Alva hero I: strength=2, First Bow: +1 → 3; range 1 + 1 → 2
        $this->assertEquals(3, $hero->getAttackStrength());
        $this->assertEquals(2, $hero->getAttackRange());

        // Place a goblin 2 hexes from Alva (range 2 reach)
        $this->game->tokens->moveToken($this->heroId, "hex_9_9");
        $this->game->getMonster("monster_goblin_20")->moveTo("hex_9_7", "");
        $this->assertValidTarget("hex_9_7");

        // Passive — not offered as a useCard target
        $this->assertNotValidTarget("card_equip_2_15");
    }

    // --- Elven Arrows (card_equip_2_24) ---
    // Passive: +2 strength.

    public function testElvenArrowsGrantsStrengthAndIsNotUsable(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_equip_2_24", "tableau_$color");
        $hero = $this->game->getHeroById($this->heroId);
        $hero->recalcTrackers();
        // Alva hero I: 2 + First Bow 1 + Elven Arrows 2 → 5
        $this->assertEquals(5, $hero->getAttackStrength());
        $this->assertNotValidTarget("card_equip_2_24");
    }

    // --- Throwing Darts (card_equip_2_17) ---
    // r=spendDurab:3roll(adj), durability 2: pay 1 durability → roll 3 dice vs adjacent monster

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

    public function testThrowingDartsResolveDealsDamageAndCostsDurability(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_equip_2_17", "tableau_$color");
        $goblin = "monster_goblin_20";
        $this->placeAdjacentGoblin($goblin);

        // Seed all-hit dice (5=hit, 1=miss); 3 dice, all hits
        $this->seedRand([5, 5, 5]);
        // turn op inlines useCard as a delegate — pick the card directly from the turn prompt.
        // The full effect (spendUse:spendDurab:3roll(adj)) auto-resolves: only one adjacent
        // goblin so Op_roll(adj) has no target choice, and the chain runs end-to-end.
        $this->respond("card_equip_2_17");

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

    // --- Belt of Youth (card_equip_2_22) ---
    // r=spendUse:1heal(self) — flip card as used, then heal 1 damage from Alva.

    public function testBeltOfYouthResolveHealsAlvaAndMarksUsed(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_equip_2_22", "tableau_$color");
        $this->game->effect_moveCrystals($this->heroId, "red", 2, $this->heroId, ["message" => ""]);
        $this->assertEquals(2, $this->countDamage($this->heroId));
        $this->assertValidTarget("card_equip_2_22");
        // Turn op inlines useCard targets; pick the card then confirm the effect.
        $this->respond("card_equip_2_22");
        $this->confirmCardEffect();

        $this->assertEquals(1, $this->countDamage($this->heroId), "Belt of Youth should heal 1 damage from Alva");
        $this->assertEquals(1, $this->game->tokens->getTokenState("card_equip_2_22"), "card should be marked used (state=1)");
    }

    // --- Elven Blade (card_equip_2_21) ---
    // r=dealDamage(adj), on=TAfterActionAttack — after each attack action, deal 1 damage to a monster adjacent to Alva.

    public function testElvenBladeDealsOneDamageToAdjacentMonsterAfterAttack(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_equip_2_21", "tableau_$color");

        // Alva at hex_7_9 (plains, non-Grimheim). Attack target at range 2, bystander adjacent.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $target = "monster_brute_1";
        $bystander = "monster_goblin_20";
        $this->game->getMonster($target)->moveTo("hex_5_9", ""); // range 2, NOT adjacent to hero
        $this->game->getMonster($bystander)->moveTo("hex_7_8", ""); // adjacent to hero

        // Seed all misses so the attack action deals 0 damage — isolates Elven Blade's +1
        $this->seedRand([1, 1, 1]);
        $this->respond("hex_5_9");

        $this->assertOperation("useCard");
        $this->assertValidTarget("card_equip_2_21");
        $this->respond("card_equip_2_21");
        $this->respond("hex_7_8");

        // Attack target at hex_5_9 is NOT adjacent to Alva, so it's excluded from Elven Blade's reach.
        $this->assertEquals(0, $this->countDamage($target), "Attack target was out of Elven Blade's adj range");
        $this->assertEquals(1, $this->countDamage($bystander), "Elven Blade should deal 1 damage to adjacent monster");
    }

    // --- Alva's Bracers (card_equip_2_23) ---
    // r=spendUse:3spendMana:performAction(actionAttack) — spend 3 mana, mark used, perform extra attack.

    public function testAlvasBracersSpendsManaMarksUsedAndPerformsAttack(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_equip_2_23", "tableau_$color");
        $this->game->effect_moveCrystals($this->heroId, "green", 3, "card_equip_2_23", ["message" => ""]);

        // Place Alva out of Grimheim, brute at range 2
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $brute = "monster_brute_1";
        $this->game->getMonster($brute)->moveTo("hex_5_9", "");

        // Seed all-hit dice: strength=3 (Alva hero I + First Bow) → 3 hits → 3 damage → brute (health=3) dies.
        $this->seedRand([5, 5, 5]);

        $this->respond("card_equip_2_23");
        $this->confirmCardEffect();
        // spendUse + spendMana resolve automatically (unique card target), performAction queues actionAttack,
        // which prompts for a hex target.
        $this->respond("hex_5_9");

        // 3 mana consumed from the card
        $this->assertEquals(0, $this->countTokens("crystal_green", "card_equip_2_23"));
        // Card marked used
        $this->assertEquals(1, $this->game->tokens->getTokenState("card_equip_2_23"));
        // Brute dead
        $this->assertEquals("supply_monster", $this->tokenLocation($brute));
    }

    // --- Windbite (card_equip_2_19) ---
    // r=counter(countRunes):addRoll, on=TRoll, strength=3, range=2.
    // Passive main weapon. After any hero roll, count runes rolled and add that many
    // new attack dice to the roll.

    public function testWindbiteAddsDicePerRuneRolled(): void {
        $color = $this->getActivePlayerColor();
        // Swap First Bow → Windbite (both are Main Weapons)
        $this->game->tokens->moveToken("card_equip_2_15", "limbo");
        $this->game->tokens->moveToken("card_equip_2_19", "tableau_$color");

        // Passive stats: Alva hero I (str=2) + Windbite (str=3) → 5; range 1 + 2 → 2
        $hero = $this->game->getHeroById($this->heroId);
        $hero->recalcTrackers();
        $this->assertEquals(5, $hero->getAttackStrength());
        $this->assertEquals(2, $hero->getAttackRange());

        // Alva out of Grimheim, brute (health=3) at range 2
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $brute = "monster_brute_1";
        $this->game->getMonster($brute)->moveTo("hex_5_9", "");

        // Seed 5 dice for the initial attack roll: 1 rune, 4 misses (side 3=rune, 1=miss).
        // Then seed 1 die for the Windbite addRoll: hit (side 5).
        // Expected: base roll gives 0 hits; addRoll gives 1 hit → brute takes 1 damage (health=3, survives).
        $this->seedRand([3, 1, 1, 1, 1, 5]);
        $this->respond("actionAttack"); // single target in range → auto-picks brute

        // TRoll trigger fires → useCard prompt for Windbite
        $this->assertOperation("useCard");
        $this->assertValidTarget("card_equip_2_19");
        $this->respond("card_equip_2_19");
        $this->confirmCardEffect();

        // Brute took 1 damage from the 1 hit on the Windbite-added die; still alive.
        $this->assertEquals("hex_5_9", $this->tokenLocation($brute));
        $this->assertEquals(1, $this->countDamage($brute));
    }

    // --- Singing Bow (card_equip_2_20) ---
    // r=gainMana, on=TAfterActionAttack, strength=3, range=2.
    // Passive main weapon; after each attack action, add 1 mana to any card.

    public function testSingingBowPassiveStatsAndAfterAttackMana(): void {
        $color = $this->getActivePlayerColor();
        // Swap First Bow (Main Weapon) → Singing Bow (Main Weapon) to avoid double main-weapon
        $this->game->tokens->moveToken("card_equip_2_15", "limbo");
        $this->game->tokens->moveToken("card_equip_2_20", "tableau_$color");

        // Passive stats: Alva hero I (str=2) + Singing Bow (str=3) → 5; range 1 + 2 → 2
        $hero = $this->game->getHeroById($this->heroId);
        $hero->recalcTrackers();
        $this->assertEquals(5, $hero->getAttackStrength());
        $this->assertEquals(2, $hero->getAttackRange());

        // Hail of Arrows I (card_ability_2_3, mana=1) is on Alva's starting tableau → sole mana target.
        $hailId = "card_ability_2_3";
        $manaBefore = $this->countTokens("crystal_green", $hailId);

        // Alva out of Grimheim, brute at range 2 (health=3, survives)
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $brute = "monster_brute_1";
        $this->game->getMonster($brute)->moveTo("hex_5_9", "");

        $this->seedRand([1, 1, 1, 1, 1, 1]); // misses; don't care about damage this test
        $this->respond("hex_5_9");

        // TAfterActionAttack trigger offers Singing Bow as a useCard option post-attack
        $this->assertOperation("useCard");
        $this->assertValidTarget("card_equip_2_20");
        $this->respond("card_equip_2_20");
        $this->confirmCardEffect();
        $this->skipIfOp("useCard"); // dismiss any further trigger prompts

        // 1 mana added to Hail of Arrows I
        $this->assertEquals($manaBefore + 1, $this->countTokens("crystal_green", $hailId));
    }

    // --- Quiver (card_equip_2_18) ---
    // r=spendDurab:addDamage, on=TActionAttack, durability=3, strength=1.
    // Passive +1 strength; during an attack, spend 1 durability (red crystal on card)
    // → add 1 hit die to the attack.

    public function testQuiverAddsDamageDuringAttackAtDurabilityCost(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_equip_2_18", "tableau_$color");

        // Place Alva out of Grimheim, brute (health=3) at range 2
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $brute = "monster_brute_1";
        $this->game->getMonster($brute)->moveTo("hex_5_9", "");

        // Seed 3 miss dice so base attack damage = 0; Quiver adds 1 guaranteed hit.
        // Strength = Alva I(2) + First Bow(1) + Quiver(1) = 4 → 4 dice rolled.
        $this->seedRand([1, 1, 1, 1]);
        $this->respond("hex_5_9");

        // TActionAttack trigger offers Quiver as a useCard option mid-attack
        $this->assertOperation("useCard");
        $this->assertValidTarget("card_equip_2_18");
        $this->respond("card_equip_2_18");
        $this->confirmCardEffect();
        $this->skipIfOp("useCard"); // dismiss any further trigger prompts

        // 1 durability spent (1 red crystal on the card)
        $this->assertEquals(1, $this->countDamage("card_equip_2_18"));
        // Brute takes 1 damage (base=0 + addDamage 1)
        $this->assertEquals(1, $this->countDamage($brute));
        $this->assertEquals("hex_5_9", $this->tokenLocation($brute));
    }

    // --- Tiara (card_equip_2_16) ---
    // r="" (bespoke class only), on="".
    // CardEquip_Tiara::onCardEnter seeds 6 yellow on the card.
    // CardEquip_Tiara::onTurnStart: while card has yellow, move 1 off and gainXp(1) for Alva.

    public function testTiaraOnEnterSeeds6Gold(): void {
        $tiara = "card_equip_2_16";
        $color = $this->getActivePlayerColor();

        // Card starts in supply — no yellow crystals on it
        $this->assertEquals(0, $this->countTokens("crystal_yellow", $tiara));

        // Gain equipment via Op_gainEquip — onCardEnter seeds 6 gold
        $this->seedDeck("deck_equip_$color", [$tiara]);
        $op = $this->game->machine->instantiateOperation("gainEquip", $color);
        $op->resolve();

        $this->assertEquals("tableau_$color", $this->tokenLocation($tiara));
        $this->assertEquals(6, $this->countTokens("crystal_yellow", $tiara));
    }

    public function testTiaraTurnStartMovesOneGoldFromCardToAlva(): void {
        $tiara = "card_equip_2_16";
        $color = $this->getActivePlayerColor();

        // Seed monster + event decks so turnEnd/monster turn don't stall on empty decks
        $this->seedDeck("deck_monster_yellow", ["card_monster_7", "card_monster_8"]);
        $this->seedDeck("deck_event_$color", ["card_event_2_31_1", "card_event_2_31_2"]); // Rest

        // Place Tiara with 6 gold already on it (skip the gainEquip path; covered separately).
        $this->game->tokens->moveToken($tiara, "tableau_$color");
        $this->game->effect_moveCrystals($this->heroId, "yellow", 6, $tiara, ["message" => ""]);
        $this->assertEquals(6, $this->countTokens("crystal_yellow", $tiara));

        $xpBefore = $this->countXp();

        // Burn Alva's two actions, skip free actions → monster turn → Alva's next turnStart fires.
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skip();
        $this->skipIfOp("drawEvent");

        // After the round: Tiara lost 1 gold, Alva's XP gained 1 from Tiara (plus 1 from Practice).
        $this->assertEquals(5, $this->countTokens("crystal_yellow", $tiara));
        $this->assertEquals($xpBefore + 2, $this->countXp(), "Practice (+1) + Tiara (+1) = +2 XP on Alva");
    }

    public function testTiaraEmptyIsNoOpAtTurnStart(): void {
        $tiara = "card_equip_2_16";
        $color = $this->getActivePlayerColor();

        $this->seedDeck("deck_monster_yellow", ["card_monster_7", "card_monster_8"]);
        $this->seedDeck("deck_event_$color", ["card_event_2_31_1", "card_event_2_31_2"]);

        // Place Tiara with 0 gold
        $this->game->tokens->moveToken($tiara, "tableau_$color");
        $this->assertEquals(0, $this->countTokens("crystal_yellow", $tiara));

        $xpBefore = $this->countXp();

        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skip();
        $this->skipIfOp("drawEvent");

        // Still 0 on Tiara; Alva's XP grew only by Practice's +1 (no Tiara contribution).
        $this->assertEquals(0, $this->countTokens("crystal_yellow", $tiara));
        $this->assertEquals($xpBefore + 1, $this->countXp(), "Only Practice's +1 XP; Tiara is empty");
    }
}
