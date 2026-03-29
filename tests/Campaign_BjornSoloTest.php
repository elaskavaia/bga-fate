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
        $this->heroId = $this->game->getHeroTokenId($this->playerColor());

        // Seed monster deck — need several simple cards (setup draws 1, each turn end draws 1)
        $this->seedDeck("deck_monster_yellow", [
            "card_monster_7", // Fiery Projectiles (Highlands, J,J,E)
            "card_monster_8", // Whirlwinds (Highlands, E,E,E,E,E)
            "card_monster_9", // Trending Monsters (Highlands, J,E,E,S,S)
            "card_monster_10", // Burnt Offerings
        ]);
        // Seed event deck with non-custom cards (Rest x2) to avoid Op_custom errors
        $this->seedDeck("deck_event_" . $this->playerColor(), [
            "card_event_1_27_1", // Rest
            "card_event_1_27_2", // Rest
        ]);
        // Clear random event cards from hand to avoid flaky triggers
        $hand = $this->game->tokens->getTokensOfTypeInLocation("card_event", "hand_" . $this->playerColor());
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
        $this->skipTriggers(); // Bjorn hero card triggers on roll

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
        $args = $this->getOpArgs();
        if (($args["type"] ?? "") === "drawEvent") {
            $this->skip();
        }

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
        $this->skipTriggers(); // Bjorn hero card triggers on roll — no actions left to spend

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
        // paygain expands: spendAction(actionFocus) + 2dealDamage both auto-resolve (single targets)

        // Troll took 2 damage from hero card + 3 hits from roll = 5 total (health=7, alive)
        $this->assertEquals($trollHex, $this->tokenLocation($troll));
        $this->assertEquals(5, $this->countDamage($troll));

        // Focus action was spent
        $hero = $this->game->getHero($this->playerColor());
        $this->assertContains("actionFocus", $hero->getActionsTaken());
    }

    public function testEagleEyeIAddsStrength(): void {
        $color = $this->playerColor();
        // Place Eagle Eye I on tableau
        $this->game->tokens->moveToken("card_ability_1_9", "tableau_$color");
        $hero = $this->game->getHero($color);
        $hero->recalcTrackers();

        // Starting strength 3 (hero=2 + bow=1) + Eagle Eye I (str=1) = 4
        $this->assertEquals(4, $hero->getAttackStrength());

        // Not offered as a free action (r is empty)
        $op = $this->game->machine->instanciateOperation("useAbility", $color);
        $targets = $op->getArgs()["target"];
        $this->assertNotContains("card_ability_1_9", $targets);
    }

    public function testEagleEyeIIAddsStrength(): void {
        $color = $this->playerColor();
        $this->game->tokens->moveToken("card_ability_1_10", "tableau_$color");
        $hero = $this->game->getHero($color);
        $hero->recalcTrackers();

        // Starting strength 3 + Eagle Eye II (str=2) = 5
        $this->assertEquals(5, $hero->getAttackStrength());
    }

    public function testLongShotINotOfferedAtRange1(): void {
        $this->clearMonstersFromMap();
        $color = $this->playerColor();
        // Place both Long Shot cards on tableau — II ensures trigger isn't void
        $this->game->tokens->moveToken("card_ability_1_11", "tableau_$color");
        $this->game->tokens->moveToken("card_ability_1_12", "tableau_$color");
        // Place goblin adjacent (range 1)
        $this->game->tokens->moveToken("marker_" . $color . "_1", "aslot_" . $color . "_empty_1");
        $this->game->tokens->moveToken("marker_" . $color . "_2", "aslot_" . $color . "_empty_2");
        $goblinHex = "hex_7_9"; // adjacent to hero start hex_8_9
        $this->game->getMonster("monster_goblin_20")->moveTo($goblinHex, "");

        // Attack adjacent goblin
        $this->seedRand([5, 5, 5]);
        $this->respond("actionAttack");

        // trigger(roll) — hero card offered; skip to reach trigger(actionAttack)
        $args = $this->getOpArgs();
        $this->assertEquals("trigger", $args["type"] ?? "");
        $this->assertEquals("roll", $args["data"]["params"] ?? "");
        $this->skip();

        // trigger(actionAttack) — Long Shot II (dist) is offered, Long Shot I (range 2+) is NOT
        $args = $this->getOpArgs();
        $this->assertEquals("trigger", $args["type"] ?? "");
        $targets = $args["target"] ?? [];
        $this->assertNotContains("card_ability_1_11", $targets, "Long Shot I should not be offered at range 1");
        $this->assertContains("card_ability_1_12", $targets, "Long Shot II should be offered at range 1");
    }

    public function testLongShotIOfferedAtRange2(): void {
        $this->clearMonstersFromMap();
        $color = $this->playerColor();
        // Place Long Shot I on tableau
        $this->game->tokens->moveToken("card_ability_1_11", "tableau_$color");
        // Use both action markers
        $this->game->tokens->moveToken("marker_" . $color . "_1", "aslot_" . $color . "_empty_1");
        $this->game->tokens->moveToken("marker_" . $color . "_2", "aslot_" . $color . "_empty_2");
        // Place goblin at range 2
        $goblinHex = "hex_6_9"; // 2 hexes from hero start hex_8_9
        $this->game->getMonster("monster_goblin_20")->moveTo($goblinHex, "");

        // Attack goblin at range 2
        $this->seedRand([5, 5, 5]);
        $this->respond("actionAttack");

        // Skip trigger(roll) if it appears, then check trigger(actionAttack)
        $args = $this->getOpArgs();
        $this->assertEquals("trigger", $args["type"] ?? "");
        if (($args["data"]["params"] ?? "") === "roll") {
            $this->skip();
            $args = $this->getOpArgs();
            $this->assertEquals("trigger", $args["type"] ?? "");
        }
        $this->assertEquals("actionAttack", $args["data"]["params"] ?? "");
        $targets = $args["target"] ?? [];
        $this->assertContains("card_ability_1_11", $targets, "Long Shot I should be offered at range 2");
    }

    public function testNailedTogetherIPiercesDamage(): void {
        $this->clearMonstersFromMap();
        $color = $this->playerColor();
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
        // Skip trigger(roll) and trigger(actionAttack), wait for trigger(monsterKilled)
        $args = $this->getOpArgs();
        $this->assertEquals("trigger", $args["type"] ?? "");
        $this->assertEquals("roll", $args["data"]["params"] ?? "");
        $this->skip();

        // trigger(actionAttack) may appear — skip if so
        $args = $this->getOpArgs();
        if (($args["data"]["params"] ?? "") === "actionAttack") {
            $this->skip();
            $args = $this->getOpArgs();
        }

        // trigger(monsterKilled) — Nailed Together I offered
        $this->assertEquals("trigger", $args["type"] ?? "", "Expected monsterKilled trigger");
        $this->assertEquals("monsterKilled", $args["data"]["params"] ?? "");
        $this->assertValidTarget("card_ability_1_13");

        // Use Nailed Together I — auto-resolves since only one monster behind
        $this->respond("card_ability_1_13");

        // Brute should have 1 damage (overkill from goblin)
        $this->assertEquals(1, $this->countDamage($brute), "Brute should have 1 overkill damage");
        // Goblin should be dead
        $this->assertEquals("supply_monster", $this->tokenLocation($goblin));
    }

    public function testNailedTogetherIIChainWithChoice(): void {
        $this->clearMonstersFromMap();
        $color = $this->playerColor();
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

        // Skip roll and actionAttack triggers
        $args = $this->getOpArgs();
        while (($args["type"] ?? "") === "trigger" && ($args["data"]["params"] ?? "") !== "monsterKilled") {
            $this->skip();
            $args = $this->getOpArgs();
        }

        // trigger(monsterKilled) — Nailed Together II offered
        $this->assertEquals("trigger", $args["type"] ?? "");
        $this->assertEquals("monsterKilled", $args["data"]["params"] ?? "");
        $this->assertValidTarget("card_ability_1_14");
        $this->respond("card_ability_1_14");

        // nailedTogether — two monsters behind hex_7_9: hex_6_9 and hex_7_8
        $args = $this->getOpArgs();
        $this->assertEquals("nailedTogether", $args["type"] ?? "");
        $this->assertValidTarget("hex_6_9");
        $this->assertValidTarget("hex_7_8");

        // Choose goblin_1 at hex_6_9 — it dies (1 pre-damage + 2 overkill = 3 ≥ 2), chain continues
        $this->respond("hex_6_9");

        // Chain: nailedTogether again — brute at hex_5_9 is behind hex_6_9
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
        $color = $this->playerColor();
        $this->game->tokens->moveToken("card_hero_1_1", "limbo");
        $this->game->tokens->moveToken("card_hero_1_2", "tableau_$color");

        $this->game->getMonster($troll)->moveTo($trollHex, "");

        $this->seedRand([5, 5, 5]);
        $this->respond("actionAttack");

        // Trigger fires — Bjorn II hero card offered
        $this->assertValidTarget("card_hero_1_2");
        $this->respond("card_hero_1_2");
        $this->skipTriggers(); // skip any remaining triggers (actionAttack, etc.)

        // Troll took 3 damage from hero card + 3 hits from roll = 6 total (health=7, alive)
        $this->assertEquals($trollHex, $this->tokenLocation($troll));
        $this->assertEquals(6, $this->countDamage($troll));

        $hero = $this->game->getHero($this->playerColor());
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
        $this->assertEquals("hand_" . $this->playerColor(), $this->tokenLocation($restCard));

        // End turn (skip free actions)
        $this->skip();

        // Monster turn runs automatically, then back to player turn
        $state = $this->getStateArgs();
        $this->assertEquals("PlayerTurn", $state["name"]);
    }
}
