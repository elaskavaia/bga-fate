<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration test: Solo Bjorn campaign.
 * Scripts full game turns using the harness GameDriver in-process.
 */
class Campaign_BjornSoloTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1]); // Solo Bjorn
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());

        // Seed monster deck — need several simple cards (setup draws 1, each turn end draws 1)
        $this->seedDeck("deck_monster_yellow", [
            "card_monster_7", // Fiery Projectiles (Highlands, J,J,E)
            "card_monster_8", // Whirlwinds (Highlands, E,E,E,E,E)
            "card_monster_9", // Trending Monsters (Highlands, J,E,E,S,S)
            "card_monster_10", // Burnt Offerings
        ]);
        // Seed event deck with non-custom cards (Rest x2) to avoid Op_custom errors
        $this->seedDeck("deck_event_" . $this->getActivePlayerColor(), [
            "card_event_1_27_1", // Rest
            "card_event_1_27_2", // Rest
        ]);
        // Clear random event cards from hand to avoid flaky triggers
        $hand = $this->game->tokens->getTokensOfTypeInLocation("card_event", "hand_" . $this->getActivePlayerColor());
        foreach ($hand as $card) {
            $this->game->tokens->moveToken($card["key"], "limbo");
        }
        $this->clearMonstersFromMap();
    }

    public function testMoveAttackMendKill(): void {
        //$this->driver->setVerbose(1);
        $goblin = "monster_goblin_20";
        $heroHexTurn1 = "hex_5_9";
        $goblinHexTurn1 = "hex_5_8";

        // Place a goblin 3 hexes from Grimheim, adjacent to heroHex
        // Use goblin_20 to avoid conflict with reinforcement-spawned goblins
        $this->game->getMonster($goblin)->moveTo($goblinHexTurn1, "");

        // === Turn 1: Move toward goblin, attack (miss), end turn ===

        // Check initial state — heroHex reachable by move, attack not valid (too far)
        $this->assertValidTarget($heroHexTurn1);
        $this->assertNotValidTarget("actionAttack"); // no monsters in range from Grimheim

        // Move 3 steps via delegate target
        $this->respond($heroHexTurn1);
        $this->assertEquals($heroHexTurn1, $this->tokenLocation($this->heroId));

        // Check state — second action, goblin hex should be attackable (adjacent)
        $this->assertValidTarget($goblinHexTurn1);

        // Attack goblin — all dice miss (bgaRand returns 1=miss by default)
        $this->respond($goblinHexTurn1);

        // Goblin should still be alive (no damage from misses)
        $this->assertEquals($goblinHexTurn1, $this->tokenLocation($goblin));
        $this->assertEquals(0, $this->countDamage($goblin));

        // Skip free actions → end turn
        $this->skip();

        // Monster turn ran automatically (goblin attack missed — default dice = miss)
        // Manually add 1 damage to hero to simulate a hit for the mend test
        $this->game->effect_moveCrystals($this->heroId, "red", 1, $this->heroId, ["message" => ""]);

        // === Turn 2: Mend, then attack to kill ===

        $state = $this->getStateArgs();
        $this->assertEquals("PlayerTurn", $state["name"]);

        // Turn start: drawEvent may be queued — skip it to get to the turn actions
        $this->skipIfOp("drawEvent");

        // Check state — mend should be available
        $this->assertValidTarget("actionMend");

        // Mend action — auto-resolves (only 1 heal target: hero's hex)
        $this->respond("actionMend");

        // Hero healed (mend heals 2, had 1 damage)
        $this->assertEquals(0, $this->countDamage($this->heroId));

        // Attack again — seed 3 dice as hits (side 5). Bjorn strength=3 (2 base + 1 weapon)
        $this->seedRand([5, 5, 5]);

        // Check state — goblin hex should be attackable as second action
        $this->assertValidTarget($goblinHexTurn1);

        $this->respond($goblinHexTurn1);

        // Goblin is dead (health=2, took 2 hits)
        $this->assertNotEquals($goblinHexTurn1, $this->tokenLocation($goblin));

        // Hero gained XP for the kill (goblin = 1 XP)
        $this->assertGreaterThanOrEqual(3, $this->countXp()); // 2 from setup + 1 from kill

        // Skip free actions → end turn
        $this->skip();
    }

    public function testBjornIHeroCardSpendsFocusAndDealsDamage(): void {
        $troll = "monster_troll_1";
        $trollHex = "hex_7_9"; // adjacent to hero start (hex_8_9), not in Grimheim

        $this->game->getMonster($troll)->moveTo($trollHex, "");

        // First action: attack adjacent troll (health=5)
        // actionAttack auto-resolves (single monster in range)
        $this->seedRand([5, 5, 5]); // 3 hits from roll
        $this->respond("actionAttack");

        // Trigger fires — Bjorn I hero card offered (1 action remaining, focus not taken)
        $this->assertValidTarget("card_hero_1_1");
        $this->respond("card_hero_1_1");
        $this->confirmCardEffect();
        // paygain expands: spendAction(actionFocus) + 2dealDamage both auto-resolve (single targets)

        // Troll took 2 damage from hero card + 3 hits from roll = 5 total (health=7, alive)
        $this->assertEquals($trollHex, $this->tokenLocation($troll));
        $this->assertEquals(5, $this->countDamage($troll));

        // Focus action was spent
        $hero = $this->game->getHero($this->getActivePlayerColor());
        $this->assertContains("actionFocus", $hero->getActionsTaken());
    }

    public function testEagleEyeIAndIIAddStrength(): void {
        $color = $this->getActivePlayerColor();
        // Eagle Eye I: +1 strength (base 3 + 1 = 4)
        $this->game->tokens->moveToken("card_ability_1_9", "tableau_$color");
        $hero = $this->game->getHero($color);
        $hero->recalcTrackers();
        $this->assertEquals(4, $hero->getAttackStrength());
        $this->assertNotValidTarget("card_ability_1_9"); // passive, not a useCard target

        // Swap to Eagle Eye II: +2 strength (base 3 + 2 = 5)
        $this->game->tokens->moveToken("card_ability_1_9", "limbo");
        $this->game->tokens->moveToken("card_ability_1_10", "tableau_$color");
        $hero->recalcTrackers();
        $this->assertEquals(5, $hero->getAttackStrength());
    }

    public function testLongShotINotOfferedAtRange1(): void {
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        // Place both Long Shot cards on tableau — II ensures trigger isn't void
        $this->game->tokens->moveToken("card_ability_1_11", "tableau_$color");
        $this->game->tokens->moveToken("card_ability_1_12", "tableau_$color");
        // Place goblin adjacent (range 1)
        $this->game->tokens->moveToken("marker_" . $color . "_1", "aslot_" . $color . "_empty_1");
        $this->game->tokens->moveToken("marker_" . $color . "_2", "aslot_" . $color . "_empty_2");
        $goblinHex = "hex_7_9"; // adjacent to hero start hex_8_9
        $this->game->getMonster("monster_goblin_20")->moveTo($goblinHex, "");

        $this->seedRand([5, 5, 5]);
        $this->respond("actionAttack");

        $this->assertOperation("useCard");
        $targets = $this->getOpArgs()["target"] ?? [];
        $this->assertNotContains("card_ability_1_11", $targets, "Long Shot I should not be offered at range 1");
        $this->assertContains("card_ability_1_12", $targets, "Long Shot II should be offered at range 1");
    }

    public function testLongShotIOfferedAtRange2(): void {
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_ability_1_11", "tableau_$color");
        $this->game->tokens->moveToken("marker_" . $color . "_1", "aslot_" . $color . "_empty_1");
        $this->game->tokens->moveToken("marker_" . $color . "_2", "aslot_" . $color . "_empty_2");

        $this->game->getMonster("monster_goblin_20")->moveTo("hex_6_9", ""); // range 2
        $this->seedRand([5, 5, 5]);
        $this->respond("actionAttack");

        $this->assertOperation("useCard");
        $targets = $this->getOpArgs()["target"] ?? [];
        $this->assertContains("card_ability_1_11", $targets, "Long Shot I should be offered at range 2");
    }

    public function testNailedTogetherIPiercesDamage(): void {
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        // Place Nailed Together I on tableau
        $this->game->tokens->moveToken("card_ability_1_13", "tableau_$color");
        // Use both action markers so only attack is available
        $this->game->tokens->moveToken("marker_" . $color . "_1", "aslot_" . $color . "_empty_1");
        $this->game->tokens->moveToken("marker_" . $color . "_2", "aslot_" . $color . "_empty_2");

        // Hero at hex_8_9 (default). Goblin at hex_7_9 (adjacent, range 1).
        // Brute at hex_6_9 (behind goblin, range 2 from hero).
        $goblin = "monster_goblin_20";
        $brute = "monster_brute_1";
        $this->game->getMonster($goblin)->moveTo("hex_7_9", "");
        $this->game->getMonster($brute)->moveTo("hex_6_9", "");

        // Bjorn strength=3, goblin health=2 → 3 hits = kill + 1 overkill
        $this->seedRand([5, 5, 5]); // 3 hits
        $this->respond("actionAttack");
        $this->respond("hex_7_9"); // pick the goblin as attack target

        // New flow: triggers auto-resolve. Bjorn hero card (on=roll) is offered as a useCard prompt; skip it.
        // Then Nailed Together I (on=monsterKilled) should be the next prompt.
        $args = $this->getOpArgs();
        if (($args["type"] ?? "") === "useCard" && in_array("card_hero_1_1", $args["target"] ?? [])) {
            $this->skip();
            $args = $this->getOpArgs();
        }

        $this->assertEquals("useCard", $args["type"] ?? "");
        $this->assertValidTarget("card_ability_1_13");

        // Use Nailed Together I — auto-resolves since only one monster behind
        $this->respond("card_ability_1_13");
        $this->confirmCardEffect();

        // Brute should have 1 damage (overkill from goblin)
        $this->assertEquals(1, $this->countDamage($brute), "Brute should have 1 overkill damage");
        // Goblin should be dead
        $this->assertEquals("supply_monster", $this->tokenLocation($goblin));
    }

    public function testNailedTogetherIIChainWithChoice(): void {
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        // Place Nailed Together II on tableau
        $this->game->tokens->moveToken("card_ability_1_14", "tableau_$color");
        $this->game->tokens->moveToken("marker_" . $color . "_1", "aslot_" . $color . "_empty_1");
        $this->game->tokens->moveToken("marker_" . $color . "_2", "aslot_" . $color . "_empty_2");

        // Hero at hex_8_9. Layout in a line:
        //   hex_7_9: goblin_20 (target, pre-damaged 1 → dies with overkill 2)
        //   hex_6_9: goblin_1 (behind, pre-damaged 1 → 2 pierce kills with overkill 1, chains)
        //   hex_7_8: goblin_2 (also behind hex_7_9 — player must choose)
        //   hex_5_9: brute_1 (behind hex_6_9 — gets chain damage)
        $goblin20 = "monster_goblin_20";
        $goblin1 = "monster_goblin_1";
        $goblin2 = "monster_goblin_2";
        $brute = "monster_brute_1";

        $this->game->getMonster($goblin20)->moveTo("hex_7_9", "");
        $this->game->getMonster($goblin1)->moveTo("hex_6_9", "");
        $this->game->getMonster($goblin2)->moveTo("hex_7_8", "");
        $this->game->getMonster($brute)->moveTo("hex_5_9", "");

        // Pre-damage goblin_20 (1 existing + 3 hits = 4 total, health=2, overkill=2)
        $this->game->effect_moveCrystals("hero_1", "red", 1, $goblin20, ["message" => ""]);
        // Pre-damage goblin_1 (1 existing + 2 pierce = 3 total, health=2, overkill=1)
        $this->game->effect_moveCrystals("hero_1", "red", 1, $goblin1, ["message" => ""]);

        // Bjorn strength=3, all hits
        $this->seedRand([5, 5, 5]);
        $this->respond("actionAttack");
        $this->respond("hex_7_9"); // pick goblin_20

        // New flow: triggers auto-resolve. Bjorn hero card (on=roll) is offered first; skip it.
        $args = $this->getOpArgs();
        if (($args["type"] ?? "") === "useCard" && in_array("card_hero_1_1", $args["target"] ?? [])) {
            $this->skip();
            $args = $this->getOpArgs();
        }

        // useCard prompt for Nailed Together II (on=monsterKilled)
        $this->assertEquals("useCard", $args["type"] ?? "");
        $this->assertValidTarget("card_ability_1_14");
        $this->respond("card_ability_1_14");

        // c_nailed — two monsters behind hex_7_9: hex_6_9 and hex_7_8
        $args = $this->getOpArgs();
        $this->assertEquals("c_nailed", $args["type"] ?? "");
        $this->assertValidTarget("hex_6_9");
        $this->assertValidTarget("hex_7_8");

        // Choose goblin_1 at hex_6_9 — it dies (1 pre-damage + 2 overkill = 3 ≥ 2), chain continues
        $this->respond("hex_6_9");

        // Chain: c_nailed again — brute at hex_5_9 is behind hex_6_9
        // Auto-resolves since only one monster behind
        // Brute should have 1 damage (chain overkill from goblin_1)
        $this->assertEquals(1, $this->countDamage($brute), "Brute should have 1 chain damage");
        // Both goblins should be dead
        $this->assertEquals("supply_monster", $this->tokenLocation($goblin20));
        $this->assertEquals("supply_monster", $this->tokenLocation($goblin1));
        // Goblin_2 should be untouched
        $this->assertEquals(0, $this->countDamage($goblin2));
    }

    public function testBjornIIHeroCardDeals3Damage(): void {
        $this->clearMonstersFromMap();
        $troll = "monster_troll_1";
        $trollHex = "hex_7_9";

        // Upgrade hero card: swap level I for level II
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_hero_1_1", "limbo");
        $this->game->tokens->moveToken("card_hero_1_2", "tableau_$color");

        $this->game->getMonster($troll)->moveTo($trollHex, "");

        $this->seedRand([5, 5, 5]);
        $this->respond("actionAttack");

        // Trigger fires — Bjorn II hero card offered
        $this->assertValidTarget("card_hero_1_2");
        $this->respond("card_hero_1_2");
        $this->confirmCardEffect();

        // Troll took 3 damage from hero card + 3 hits from roll = 6 total (health=7, alive)
        $this->assertEquals($trollHex, $this->tokenLocation($troll));
        $this->assertEquals(6, $this->countDamage($troll));

        $hero = $this->game->getHero($this->getActivePlayerColor());
        $this->assertContains("actionFocus", $hero->getActionsTaken());
    }

    public function testFirstTurnMoveAndPrepare(): void {
        $moveTarget = "hex_7_8";

        // First action: Move — click a hex directly (delegate target from turn op)
        $this->respond($moveTarget);
        $this->assertEquals($moveTarget, $this->tokenLocation($this->heroId));

        // Second action: Prepare — draws Rest (seeded on top)
        $this->respond("actionPrepare");
        $this->respond("confirm"); // confirm the draw

        // Rest card should now be in hand
        $restCard = "card_event_1_27_1";
        $this->assertEquals("hand_" . $this->getActivePlayerColor(), $this->tokenLocation($restCard));

        // End turn (skip free actions)
        $this->skip();

        // Monster turn runs automatically, then back to player turn
        $state = $this->getStateArgs();
        $this->assertEquals("PlayerTurn", $state["name"]);
    }

    // --- Suppressive Fire I/II (card_ability_1_5 / card_ability_1_6) ---

    public function testSuppressiveFireIPreventsMonsterMovement(): void {
        $color = $this->getActivePlayerColor();
        // Place Suppressive Fire I on tableau
        $this->game->tokens->dbSetTokenLocation("card_ability_1_5", "tableau_$color", 0);

        // Place a goblin (rank 1) within range 3 of hero start (hex_8_9)
        $goblin = "monster_goblin_20";
        $goblinHex = "hex_7_8"; // range 2 from hex_8_9
        $this->game->getMonster($goblin)->moveTo($goblinHex, "");

        // Place a brute far away — it should still move toward Grimheim
        $brute = "monster_brute_1";
        $bruteHex = "hex_1_15";
        $this->game->getMonster($brute)->moveTo($bruteHex, "");

        // Do two actions then end turn
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skip(); // skip free actions → end turn → monster turn starts

        // Skip drawEvent if queued by turnEnd
        $this->skipIfOp("drawEvent");

        // New flow: trigger(monsterMove) auto-resolves; useCard for Suppressive Fire I is queued.
        $args = $this->getOpArgs();
        $this->assertEquals("useCard", $args["type"] ?? "");
        $this->assertValidTarget("card_ability_1_5");

        // Use Suppressive Fire I — pick the goblin's hex
        $this->respond("card_ability_1_5");
        $args = $this->getOpArgs();
        $this->assertEquals("c_supfire", $args["type"] ?? "");
        $this->assertValidTarget($goblinHex);
        $this->respond($goblinHex);

        // Monster movement runs — goblin should NOT have moved
        // Wait for next player turn
        $state = $this->getStateArgs();
        $this->assertEquals("PlayerTurn", $state["name"]);
        $this->assertEquals($goblinHex, $this->tokenLocation($goblin), "Suppressed goblin should not have moved");

        // Green crystal should still be on the goblin
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", $goblin);
        $this->assertCount(1, $crystals, "Green crystal should remain on goblin after movement phase");

        // Brute (not suppressed) should have moved closer to Grimheim

        $bruteHex2 = $this->tokenLocation($brute);
        $this->assertNotEquals($bruteHex, $bruteHex2, "Non-suppressed brute should have moved $bruteHex2");
    }

    public function testSuppressiveFireCannotChooseSameMonsterNextTurn(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->dbSetTokenLocation("card_ability_1_5", "tableau_$color", 0);

        $goblin = "monster_goblin_20";
        $brute = "monster_brute_1";
        $goblinHex = "hex_7_8";
        $bruteHex = "hex_6_9";
        $this->game->getMonster($goblin)->moveTo($goblinHex, "");
        $this->game->getMonster($brute)->moveTo($bruteHex, "");

        // Turn 1: suppress the goblin
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skip(); // end turn

        $this->skipIfOp("drawEvent");
        $args = $this->getOpArgs();
        $this->assertEquals("useCard", $args["type"] ?? "");
        $this->respond("card_ability_1_5");
        $this->respond($goblinHex);

        // Wait for next player turn
        $state = $this->getStateArgs();
        $this->assertEquals("PlayerTurn", $state["name"]);

        // Turn 2: end turn
        $this->skipIfOp("drawEvent");
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skip();

        // Skip drawEvent if queued
        $this->skipIfOp("drawEvent");

        // Monster turn — Suppressive Fire offered again as useCard
        $args = $this->getOpArgs();
        $this->assertEquals("useCard", $args["type"] ?? "");
        $this->respond("card_ability_1_5");

        // Goblin should NOT be a valid target (still has green crystal)
        $args = $this->getOpArgs();
        $this->assertEquals("c_supfire", $args["type"] ?? "");
        $this->assertNotValidTarget($goblinHex, "Goblin should be excluded (has green crystal from last turn)");
        // Brute moved toward Grimheim after turn 1 — use its current hex
        $bruteCurrentHex = $this->tokenLocation($brute);
        $this->assertValidTarget($bruteCurrentHex, "Brute should be available");
    }

    public function testSuppressiveFireIExcludesRank3(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->dbSetTokenLocation("card_ability_1_5", "tableau_$color", 0);

        // Only a troll (rank 3) in range — Level I should not offer it
        $troll = "monster_troll_1";
        $trollHex = "hex_7_9";
        $this->game->getMonster($troll)->moveTo($trollHex, "");

        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skip(); // end turn

        // The trigger(monsterMove) auto-skips because Suppressive Fire I has no valid
        // targets (troll is rank 3, filter is rank<=2). Monster turn proceeds automatically.
        // We should be back at player turn 2 (or the troll entered Grimheim).
        $state = $this->getStateArgs();
        $this->assertEquals("PlayerTurn", $state["name"], "Trigger should auto-skip when no valid targets");
    }

    public function testSuppressiveFireSkipRemovesCrystal(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->dbSetTokenLocation("card_ability_1_5", "tableau_$color", 0);

        $goblin = "monster_goblin_20";
        $brute = "monster_brute_1";
        $goblinHex = "hex_7_8";
        $bruteHex = "hex_6_9";
        $this->game->getMonster($goblin)->moveTo($goblinHex, "");
        $this->game->getMonster($brute)->moveTo($bruteHex, "");

        // Turn 1: suppress the goblin
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skip(); // end turn

        $this->skipIfOp("drawEvent");
        $args = $this->getOpArgs();
        $this->assertEquals("useCard", $args["type"] ?? "");
        $this->respond("card_ability_1_5");
        $this->respond($goblinHex);

        $state = $this->getStateArgs();
        $this->assertEquals("PlayerTurn", $state["name"]);

        // Turn 2: end turn
        $this->skipIfOp("drawEvent");
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skip(); // end turn

        // Skip drawEvent if queued
        $this->skipIfOp("drawEvent");

        // Monster turn — use Suppressive Fire but SKIP c_supfire
        $args = $this->getOpArgs();
        $this->assertEquals("useCard", $args["type"] ?? "");
        $this->respond("card_ability_1_5");

        // Skip c_supfire
        $args = $this->getOpArgs();
        $this->assertEquals("c_supfire", $args["type"] ?? "");
        $this->skip();

        // After monster turn, crystal should be removed from goblin
        $state = $this->getStateArgs();
        $this->assertEquals("PlayerTurn", $state["name"]);
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", $goblin);
        $this->assertCount(0, $crystals, "Crystal should be removed when player skips Suppressive Fire");
    }

    // --- Stitching I/II (card_ability_1_7 / card_ability_1_8) ---

    public function testStitchingIChooseHealOverRepair(): void {
        $color = $this->getActivePlayerColor();
        $equipCard = "card_equip_1_15";
        $this->game->tokens->dbSetTokenLocation("card_ability_1_7", "tableau_$color", 0);
        $this->game->tokens->dbSetTokenLocation($equipCard, "tableau_$color", 0);
        $this->game->effect_moveCrystals($this->heroId, "red", 2, $this->heroId, ["message" => ""]);
        $this->game->effect_moveCrystals($this->heroId, "red", 1, $equipCard, ["message" => ""]);

        $this->respond("card_ability_1_7");
        $this->confirmCardEffect();
        $this->respond("choice_0"); // choose heal over repair
        // heal(adj) with sole hero → auto-resolves

        $this->assertEquals(1, $this->countDamage($this->heroId));
        $this->assertEquals(1, $this->countDamage($equipCard));
    }

    public function testStitchingIChooseRepairOverHeal(): void {
        $color = $this->getActivePlayerColor();
        $equipCard = "card_equip_1_15";
        $this->game->tokens->dbSetTokenLocation("card_ability_1_7", "tableau_$color", 0);
        $this->game->tokens->dbSetTokenLocation($equipCard, "tableau_$color", 0);
        $this->game->effect_moveCrystals($this->heroId, "red", 2, $this->heroId, ["message" => ""]);
        $this->game->effect_moveCrystals($this->heroId, "red", 1, $equipCard, ["message" => ""]);

        $this->respond("card_ability_1_7");
        $this->confirmCardEffect();
        $this->respond("choice_1"); // choose repairCard
        // repairCard with sole damaged card → auto-resolves

        $this->assertEquals(2, $this->countDamage($this->heroId));
        $this->assertEquals(0, $this->countDamage($equipCard));
    }

    // --- Monster Attack ---

    public function testMonsterAttacksAdjacentHero(): void {
        // Place goblin adjacent to hero (hero starts in Grimheim hex_8_9)
        $goblin = "monster_goblin_20";
        $heroHex = "hex_7_8";
        $goblinHex = "hex_7_9";
        $this->game->getMonster($goblin)->moveTo($goblinHex, "");
        $this->game->tokens->dbSetTokenLocation($this->heroId, $heroHex);

        // Seed dice: goblin strength=1 → 1 die, roll a hit (side 5)
        $this->seedRand([5]);

        // Do two actions then end turn
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skip(); // end turn → monster turn

        // Skip drawEvent if queued by turnEnd
        $this->skipIfOp("drawEvent");

        // Monster turn runs: move + attack + reinforcement → back to player turn
        $state = $this->getStateArgs();
        $this->assertEquals("PlayerTurn", $state["name"]);

        // Hero should have taken damage from the goblin attack
        $this->assertGreaterThan(0, $this->countDamage($this->heroId), "Hero should have taken damage from monster attack");
    }

    // --- Sure Shot I (card_ability_1_3) ---

    public function testSureShotISpendsManaAndDealsDamage(): void {
        $sureShotId = "card_ability_1_3";

        // Add 3 mana to Sure Shot I (it starts with 1 from setup, add 2 more)
        $this->game->effect_moveCrystals($this->heroId, "green", 2, $sureShotId, ["message" => ""]);
        $this->assertEquals(3, $this->countTokens("crystal_green", $sureShotId));

        // Place a goblin within attack range (Bjorn range=2 with First Bow)
        $goblin = "monster_goblin_20";
        $goblinHex = "hex_6_9"; // range 2 from hero start hex_8_9
        $this->game->getMonster($goblin)->moveTo($goblinHex, "");

        // Sure Shot I should be offered as a free action
        $this->assertValidTarget($sureShotId);
        $this->respond($sureShotId);
        $this->confirmCardEffect();

        // spendMana auto-resolves (only one card with enough mana)
        // dealDamage auto-resolves (only one monster in range) → goblin killed (health=2, 3 damage)
        $this->assertEquals("supply_monster", $this->tokenLocation($goblin));

        // Mana should be spent (3 → 0)
        $this->assertEquals(0, $this->countTokens("crystal_green", $sureShotId));
    }

    // --- Sure Shot II (card_ability_1_4) ---

    public function testSureShotIISelectMonsterThenMana(): void {
        $color = $this->getActivePlayerColor();
        $sureShotId = "card_ability_1_4";

        // Swap Sure Shot I for Sure Shot II on tableau
        $this->game->tokens->moveToken("card_ability_1_3", "limbo");
        $this->game->tokens->dbSetTokenLocation($sureShotId, "tableau_$color", 0);

        // Add 4 mana to Sure Shot II
        $this->game->effect_moveCrystals($this->heroId, "green", 4, $sureShotId, ["message" => ""]);

        // Place a brute within attack range (Bjorn range=2 with First Bow)
        // Brute health=3
        $brute = "monster_brute_1";
        $bruteHex = "hex_6_9"; // range 2 from hero start hex_8_9
        $this->game->getMonster($brute)->moveTo($bruteHex, "");

        // Sure Shot II should be offered as a free action
        $this->assertValidTarget($sureShotId);
        $this->respond($sureShotId);
        $this->confirmCardEffect();

        // Step 1 auto-resolves (only one monster in range)
        // Step 2: choose mana amount — brute health=3, so max=3
        $this->assertOperation("c_sureshotII");
        $this->assertValidTarget("choice_2");
        $this->assertValidTarget("choice_3");
        $this->assertNotValidTarget("choice_4");
        $this->respond("choice_3");

        // spendMana + dealDamage auto-resolve → brute killed (health=3, 3 damage)
        $this->assertEquals("supply_monster", $this->tokenLocation($brute));

        // Mana should be spent (4 → 1)
        $this->assertEquals(1, $this->countTokens("crystal_green", $sureShotId));
    }

    public function testStitchingIIHealsTwoDamage(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->dbSetTokenLocation("card_ability_1_8", "tableau_$color", 0);
        $this->game->effect_moveCrystals($this->heroId, "red", 3, $this->heroId, ["message" => ""]);

        $this->respond("card_ability_1_8");
        $this->confirmCardEffect();
        // Stitching II r=2heal(adj)/2repairCard/(heal(adj),repairCard) — pick the 2heal branch
        $this->respond("choice_0");
        $this->assertEquals(1, $this->countDamage($this->heroId));
    }
}
