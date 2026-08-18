<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Bjorn's ability and hero cards.
 * Scripts full game turns using the harness GameDriver in-process.
 */
class Campaign_BjornAbilityTest extends CampaignBaseTest {
    private string $heroId;
    private string $color;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1]); // Solo Bjorn
        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->clearEquipDecks();
        $this->seedDeck("deck_monster_yellow", ["card_monster_7", "card_monster_8", "card_monster_9", "card_monster_10"]);
        $this->seedDeck("deck_event_$this->color", ["card_event_1_27_1", "card_event_1_27_2"]);
        $this->clearMonstersFromMap();
        $this->clearHand($this->color);
    }

    public function testBjornIHeroCardSpendsFocusAndDealsDamage(): void {
        $this->moveHeroOutOfGrimheim();
        [$troll] = $this->spawnMonsterAdjacent("troll");
        $trollHex = $this->tokenLocation($troll);

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

    public function testBjornIIHeroCardDeals3Damage(): void {
        $this->clearMonstersFromMap();

        // Upgrade hero card: swap level I for level II
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_hero_1_1", "limbo");
        $this->game->tokens->moveToken("card_hero_1_2", "tableau_$color");

        $this->moveHeroOutOfGrimheim();
        [$troll] = $this->spawnMonsterAdjacent("troll");
        $trollHex = $this->tokenLocation($troll);

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

    private function addAbility(string $cardId): void {
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->color);
        $this->game->getHero($this->color)->recalcTrackers();
    }

    // --- Sure Shot I (card_ability_1_3) ---

    public function testSureShotISpendsManaAndDealsDamage(): void {
        $sureShotId = "card_ability_1_3";

        // Add 3 mana to Sure Shot I (it starts with 1 from setup, add 2 more)
        $this->game->effect_moveCrystals($this->heroId, "green", 2, $sureShotId, ["message" => ""]);
        $this->assertEquals(3, $this->countTokens("crystal_green", $sureShotId));

        $this->moveHeroOutOfGrimheim();
        // Place a goblin within attack range (Bjorn range=2 with First Bow)
        $goblin = "monster_goblin_20";
        $goblinHex = "hex_5_9"; // range 2 from hero hex_7_9
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

        $this->moveHeroOutOfGrimheim();
        // Place a brute within attack range (Bjorn range=2 with First Bow)
        // Brute health=3
        $brute = "monster_brute_1";
        $bruteHex = "hex_5_9"; // range 2 from hero hex_7_9
        $this->game->getMonster($brute)->moveTo($bruteHex, "");

        // Sure Shot II should be offered as a free action
        $this->assertValidTarget($sureShotId);
        $this->respond($sureShotId);
        $this->confirmCardEffect();

        // Step 1 auto-resolves (only one monster in range)
        // Step 2: choose mana amount — mana=4, overkill allowed so max=4 (brute health=3)
        $this->assertOperation("c_sureshotII");
        $this->assertValidTarget("choice_2");
        $this->assertValidTarget("choice_3");
        $this->assertValidTarget("choice_4");
        $this->respond("choice_3");

        // spendMana + dealDamage auto-resolve → brute killed (health=3, 3 damage)
        $this->assertEquals("supply_monster", $this->tokenLocation($brute));

        // Mana should be spent (4 → 1)
        $this->assertEquals(1, $this->countTokens("crystal_green", $sureShotId));
    }

    // --- Suppressive Fire I/II (card_ability_1_5 / card_ability_1_6) ---

    public function testSuppressiveFireIPreventsMonsterMovement(): void {
        $color = $this->getActivePlayerColor();
        // Place Suppressive Fire I on tableau
        $this->game->tokens->dbSetTokenLocation("card_ability_1_5", "tableau_$color", 0);

        $this->moveHeroOutOfGrimheim();
        // Place a goblin (rank 1) within range 3 of hero (hex_7_9)
        $goblin = "monster_goblin_20";
        $goblinHex = "hex_6_8"; // within range 3 of hex_7_9
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

        // Stun marker should still be on the goblin
        $markers = $this->game->tokens->getTokensOfTypeInLocation("stunmarker", $goblin);
        $this->assertCount(1, $markers, "Stun marker should remain on goblin after movement phase");

        // Brute (not suppressed) should have moved closer to Grimheim

        $bruteHex2 = $this->tokenLocation($brute);
        $this->assertNotEquals($bruteHex, $bruteHex2, "Non-suppressed brute should have moved $bruteHex2");
    }

    public function testSuppressiveFireCannotChooseSameMonsterNextTurn(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->dbSetTokenLocation("card_ability_1_5", "tableau_$color", 0);

        $this->moveHeroOutOfGrimheim();
        $goblin = "monster_goblin_20";
        $brute = "monster_brute_1";
        $goblinHex = "hex_6_8";
        $bruteHex = "hex_5_9";
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
        $this->assertNotValidTarget($goblinHex, "Goblin should be excluded (has stun marker from last turn)");
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

        $this->moveHeroOutOfGrimheim();
        $goblin = "monster_goblin_20";
        $brute = "monster_brute_1";
        $goblinHex = "hex_6_8";
        $bruteHex = "hex_5_9";
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

        // After monster turn, marker should be removed from goblin
        $state = $this->getStateArgs();
        $this->assertEquals("PlayerTurn", $state["name"]);
        $markers = $this->game->tokens->getTokensOfTypeInLocation("stunmarker", $goblin);
        $this->assertCount(0, $markers, "Marker should be removed when player skips Suppressive Fire");
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

    // --- Eagle Eye I/II (card_ability_1_9 / card_ability_1_10) ---
    // BGA #237686: the printed cards carry a strength badge in the corner (+1 on level I, +2 on
    // level II) on top of the range text, and Eagle Eye II's text adds "Always ignore the armor".
    // The original CSV had the strength; f7b70ae removed it while adding range, and the armor
    // sentence was never implemented.

    public function testEagleEyeAddsRangeAndStrength(): void {
        $hero = $this->game->getHero($this->color);
        $this->assertEquals(3, $hero->getAttackStrength(), "baseline: 2 hero + 1 starting bow");
        $this->assertEquals(2, $hero->getAttackRange(), "baseline: base 1 + bow 1");

        $this->addAbility("card_ability_1_9"); // Eagle Eye I

        $this->assertEquals(4, $hero->getAttackStrength(), "corner badge grants +1 strength");
        $this->assertEquals(3, $hero->getAttackRange(), "range +1: base 1 + bow 1 + Eagle Eye 1");
        $this->assertNotValidTarget("card_ability_1_9"); // passive, not a useCard target

        $this->game->tokens->moveToken("card_ability_1_9", "limbo");
        $this->addAbility("card_ability_1_10"); // Eagle Eye II

        $this->assertEquals(5, $hero->getAttackStrength(), "corner badge grants +2 strength");
        $this->assertEquals(4, $hero->getAttackRange(), "range +2: base 1 + bow 1 + Eagle Eye 2");
    }

    /** Control: without Eagle Eye II, draugr armor still absorbs 1 hit. */
    public function testDraugrArmorReducesHitsWithoutEagleEyeII(): void {
        $this->game->getMonster("monster_draugr_1")->moveTo("hex_8_8", "");
        $this->game->tokens->moveToken($this->game->getHeroTokenId($this->color), "hex_7_9");

        $this->seedRand([5, 5, 1]); // strength 3: 2 hits, 1 miss
        // Single monster on the map: the attack target auto-resolves and the dice roll at once.
        $this->respond("actionAttack");
        $this->skipIfOp("useCard"); // decline Bjorn Hero I's +1 damage

        $this->assertEquals(1, $this->countDamage("monster_draugr_1"), "2 hits - 1 armor");
    }

    public function testEagleEyeIIIgnoresDraugrArmor(): void {
        $this->addAbility("card_ability_1_10"); // Eagle Eye II
        $this->game->getMonster("monster_draugr_1")->moveTo("hex_8_8", "");
        $this->game->tokens->moveToken($this->game->getHeroTokenId($this->color), "hex_7_9");

        $this->seedRand([1, 1, 1, 5, 5]); // strength 5: the 2 hits are on dice 4-5, so they only exist if all 5 roll
        $this->respond("actionAttack");
        $this->skipIfOp("useCard"); // decline Bjorn Hero I's +1 damage

        $this->assertEquals(2, $this->countDamage("monster_draugr_1"), "both hits land - armor ignored");
    }

    // --- Long Shot I/II (card_ability_1_11 / card_ability_1_12) ---

    public function testLongShotINotOfferedAtRange1(): void {
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        // Place both Long Shot cards on tableau — II ensures trigger isn't void
        $this->game->tokens->moveToken("card_ability_1_11", "tableau_$color");
        $this->game->tokens->moveToken("card_ability_1_12", "tableau_$color");
        $this->moveHeroOutOfGrimheim();
        $goblinHex = "hex_6_9"; // adjacent to hex_7_9, range 1
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
        $this->moveHeroOutOfGrimheim();

        $this->game->getMonster("monster_goblin_20")->moveTo("hex_5_9", ""); // range 2 from hex_7_9
        $this->seedRand([5, 5, 5]);
        $this->respond("actionAttack");

        $this->assertOperation("useCard");
        $targets = $this->getOpArgs()["target"] ?? [];
        $this->assertContains("card_ability_1_11", $targets, "Long Shot I should be offered at range 2");
    }

    /**
     * BGA #234927 - Long Shot I (r=2addDamage(2), on=TActionAttack) reportedly not adding +2
     * damage at range 2. NOT reproducible: when the player selects Long Shot I during the attack
     * and confirms it, the +2 lands at distance exactly 2 (the min-range predicate in Op_addDamage
     * is `dist < minRange` -> at dist 2 with minRange 2 that is false, so the bonus applies).
     * Long Shot I is an OPTIONAL, interactive trigger (a useCard prompt plus a confirm) - it is
     * NOT automatic. If the player does not actively select and confirm it, no +2 is added, which
     * matches the report. This test documents the correct behavior as a regression guard.
     */
    public function testLongShotIAddsPlus2AtRangeExactly2(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_ability_1_11", "tableau_$color");
        $this->moveHeroOutOfGrimheim(); // hero -> hex_7_9

        $brute = "monster_brute_1"; // health 3, survives the +2
        $this->game->getMonster($brute)->moveTo("hex_5_9", ""); // distance exactly 2 from hex_7_9

        // Rig every base attack die to a miss so base damage is 0 - the only damage
        // source is Long Shot I's +2. Bjorn strength (Bjorn I 2 + First Bow 1) = 3 dice.
        $this->seedRand([1, 1, 1]);
        $this->respond("actionAttack"); // single target auto-selected -> roll -> useCard prompt

        $this->assertOperation("useCard");
        $this->assertValidTarget("card_ability_1_11", "Long Shot I should be offered at range 2");
        $this->respond("card_ability_1_11"); // select Long Shot -> queues 2addDamage(2)
        $this->respond("confirm"); // confirm the +2 addDamage
        $this->skipUseCard("card_hero_1_1"); // dismiss Bjorn Hero I (on=Roll)

        $this->assertEquals(2, $this->countDamage($brute), "Long Shot I applies +2 at distance exactly 2");
    }

    // --- Nailed Together I/II (card_ability_1_13 / card_ability_1_14) ---

    public function testNailedTogetherIPiercesDamage(): void {
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        // Place Nailed Together I on tableau
        $this->game->tokens->moveToken("card_ability_1_13", "tableau_$color");

        $this->moveHeroOutOfGrimheim();
        // Hero at hex_7_9. Goblin at hex_6_9 (adjacent). Brute at hex_5_9 (behind goblin).
        $goblin = "monster_goblin_20";
        $brute = "monster_brute_1";
        $this->game->getMonster($goblin)->moveTo("hex_6_9", "");
        $this->game->getMonster($brute)->moveTo("hex_5_9", "");

        // Bjorn strength=3, goblin health=2 → 3 hits = kill + 1 overkill
        $this->seedRand([5, 5, 5]); // 3 hits
        $this->respond("actionAttack");
        $this->respond("hex_6_9"); // pick the goblin as attack target

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

    /**
     * Level I pierces ONE monster: "all remaining damage may be dealt to a second monster
     * behind it". Level II is the one that says "and so on". The pierce kill re-fires
     * TMonsterKilled, so with damage still left over and a third monster further back the card
     * must not be offered a second time - the same shape as the Sweeping Strike loop.
     */
    public function testNailedTogetherIPiercesOnlyOnce(): void {
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_ability_1_13", "tableau_$color"); // Nailed Together I

        $this->moveHeroOutOfGrimheim();
        // Hero at hex_7_9, three monsters straight back: target, pierce victim, then one more.
        $target = "monster_goblin_20";
        $second = "monster_goblin_1";
        $third = "monster_brute_1";
        $this->game->getMonster($target)->moveTo("hex_6_9", "");
        $this->game->getMonster($second)->moveTo("hex_5_9", "");
        $this->game->getMonster($third)->moveTo("hex_4_9", "");

        // Both goblins health 2, pre-damaged 1: 3 hits kill the target with 2 over, the pierce
        // kills the second with 1 still to spare - which is what could feed a third hit.
        $this->game->effect_moveCrystals("hero_1", "red", 1, $target, ["message" => ""]);
        $this->game->effect_moveCrystals("hero_1", "red", 1, $second, ["message" => ""]);

        $this->seedRand([5, 5, 5]); // Bjorn strength 3, all hits
        $this->respond("actionAttack");
        $this->respond("hex_6_9");

        $args = $this->getOpArgs();
        if (($args["type"] ?? "") === "useCard" && in_array("card_hero_1_1", $args["target"] ?? [])) {
            $this->skip(); // Bjorn hero card fires on the roll - not what this test is about
        }
        $this->respond("card_ability_1_13");
        $this->respond("hex_5_9");

        $args = $this->getOpArgs();
        $this->assertNotEquals("useCard", $args["type"] ?? "", "the pierce must not offer itself again");
        $this->assertEquals("supply_monster", $this->tokenLocation($target));
        $this->assertEquals("supply_monster", $this->tokenLocation($second));
        $this->assertEquals(0, $this->countDamage($third), "level I pierces one monster, not a chain");
    }

    public function testNailedTogetherIIChainWithChoice(): void {
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        // Place Nailed Together II on tableau
        $this->game->tokens->moveToken("card_ability_1_14", "tableau_$color");

        $this->moveHeroOutOfGrimheim();
        // Hero at hex_7_9. Layout:
        //   hex_6_9: goblin_20 (target, pre-damaged 1 → dies with overkill 2)
        //   hex_5_9: goblin_1 (behind, pre-damaged 1 → 2 pierce kills with overkill 1, chains)
        //   hex_6_8: goblin_2 (also behind hex_6_9 — player must choose)
        //   hex_4_9: brute_1 (behind hex_5_9 — gets chain damage)
        $goblin20 = "monster_goblin_20";
        $goblin1 = "monster_goblin_1";
        $goblin2 = "monster_goblin_2";
        $brute = "monster_brute_1";

        $this->game->getMonster($goblin20)->moveTo("hex_6_9", "");
        $this->game->getMonster($goblin1)->moveTo("hex_5_9", "");
        $this->game->getMonster($goblin2)->moveTo("hex_6_8", "");
        $this->game->getMonster($brute)->moveTo("hex_4_9", "");

        // Pre-damage goblin_20 (1 existing + 3 hits = 4 total, health=2, overkill=2)
        $this->game->effect_moveCrystals("hero_1", "red", 1, $goblin20, ["message" => ""]);
        // Pre-damage goblin_1 (1 existing + 2 pierce = 3 total, health=2, overkill=1)
        $this->game->effect_moveCrystals("hero_1", "red", 1, $goblin1, ["message" => ""]);

        // Bjorn strength=3, all hits
        $this->seedRand([5, 5, 5]);
        $xpBefore = $this->countXp();
        $this->respond("actionAttack");
        $this->respond("hex_6_9"); // pick goblin_20

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

        // c_nailed — two monsters behind hex_6_9: hex_5_9 and hex_6_8
        $args = $this->getOpArgs();
        $this->assertEquals("c_nailed", $args["type"] ?? "");
        $this->assertValidTarget("hex_5_9");
        $this->assertValidTarget("hex_6_8");

        // Choose goblin_1 at hex_5_9 — it dies (1 pre-damage + 2 overkill = 3 ≥ 2), chain continues
        $this->respond("hex_5_9");

        // The pierce kill fires TMonsterKilled, but CardAbility_NailedTogetherI ignores a kill
        // the pierce itself caused, so the card is not re-offered - the chain below is the
        // c_nailed(chain) step Op_c_nailed re-queued. skipIfOp stays as a defensive no-op.
        $this->skipIfOp("useCard");

        // Chain: c_nailed again — brute at hex_4_9 is the only monster behind hex_5_9. Each
        // pierce is a "may", so the step asks even with one target rather than auto-resolving.
        $this->respond("hex_4_9");

        // Brute should have 1 damage (chain overkill from goblin_1)
        $this->assertEquals(1, $this->countDamage($brute), "Brute should have 1 chain damage");
        // Both goblins should be dead
        $this->assertEquals("supply_monster", $this->tokenLocation($goblin20));
        $this->assertEquals("supply_monster", $this->tokenLocation($goblin1));
        // Goblin_2 should be untouched
        $this->assertEquals(0, $this->countDamage($goblin2));
        // Each pierce kill scores its own monster, not whatever marker_attack has advanced
        // onto (the surviving brute is worth 2) - BGA #234242.
        $this->assertEquals(2, $this->countXp() - $xpBefore, "both goblin kills award 1 XP each");
    }
}
