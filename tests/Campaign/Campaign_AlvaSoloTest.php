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
    // r=gainDamage:3roll(adj), durability 2: pay 1 durability → roll 3 dice vs adjacent monster

    private function placeAdjacentGoblin(string $goblinId = "monster_goblin_20"): string {
        // Move Alva out of Grimheim (heroes can't fight from inside the Grimheim location),
        // then place a goblin on the first non-Grimheim plains/forest neighbor.
        $heroHex = "hex_7_9"; // plains, outside Grimheim
        $this->assertFalse($this->game->hexMap->isInGrimheim($heroHex), "$heroHex should not be Grimheim");
        $this->game->tokens->moveToken($this->heroId, $heroHex);
        $this->game->hexMap->invalidateOccupancy();

        $neighbors = $this->game->hexMap->getAdjacentHexes($heroHex);
        foreach ($neighbors as $hex) {
            if ($this->game->hexMap->isInGrimheim($hex)) {
                continue;
            }
            $terrain = $this->game->material->getRulesFor($hex, "terrain", "");
            if ($terrain === "plains" || $terrain === "forest") {
                $this->game->getMonster($goblinId)->moveTo($hex, "");
                $this->game->hexMap->invalidateOccupancy();
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
        // Fill up durability (2) — no room for gainDamage
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

        // Any chained useCard triggers (e.g. Alva hero card on=roll) — skip them
        $this->skipTriggers();
        $this->skipIfOp("useCard");

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

        // drawEvent op queues a confirm prompt — accept it
        if (($this->getOpArgs()["type"] ?? "") === "drawEvent") {
            $this->respond("confirm");
        }

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
        $this->game->hexMap->invalidateOccupancy();

        // Seed 3 miss dice so base attack damage = 0; Bloodline Crystal will add 2.
        // Strength=3 (Alva hero 2 + First Bow 1).
        $this->seedRand([1, 1, 1]);
        $this->respond("actionAttack");
        $this->respond("hex_5_9");

        // The actionAttack trigger offers Bloodline Crystal as a single-choice useCard,
        // which auto-collapses into the or op. Pick the add-damage branch.
        $this->assertEquals("or", $this->getOpArgs()["type"] ?? "");
        $this->respond("choice_0");

        // Drain any remaining free-action useCard prompts back to the turn state
        while (($this->getOpArgs()["type"] ?? "") !== "turn") {
            $this->skip();
        }

        // 3 mana spent (branch A)
        $this->assertEquals(0, $this->countTokens("crystal_green", "card_equip_2_25"));
        // Brute took 2 damage from branch A (base attack missed, so damage = 0 + 2 = 2);
        // brute health=3 so it's still alive
        $this->assertEquals("hex_5_9", $this->tokenLocation($brute));
        $this->assertEquals(2, $this->countDamage($brute));
    }

    public function testBloodlineCrystalPlayerPicksDrawBranchDuringAttack(): void {
        // Mid-attack, both branches are viable — verify the user can pick draw (choice_1)
        // instead of add-damage (choice_0), proving the `or` op really offers a choice.
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_equip_2_25", "tableau_$color");
        $this->game->effect_moveCrystals($this->heroId, "green", 3, "card_equip_2_25", ["message" => ""]);

        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $brute = "monster_brute_1";
        $this->game->getMonster($brute)->moveTo("hex_5_9", "");
        $this->game->hexMap->invalidateOccupancy();

        $this->seedRand([1, 1, 1]);
        $this->respond("actionAttack");
        $this->respond("hex_5_9");

        $this->assertEquals("or", $this->getOpArgs()["type"] ?? "");
        $this->respond("choice_1"); // drawEvent branch
        if (($this->getOpArgs()["type"] ?? "") === "drawEvent") {
            $this->respond("confirm");
        }
        while (($this->getOpArgs()["type"] ?? "") !== "turn") {
            $this->skip();
        }

        $this->assertEquals(0, $this->countTokens("crystal_green", "card_equip_2_25"));
        // Brute took no damage — all dice missed and no add-damage
        $this->assertEquals(0, $this->countDamage($brute));
        // Hand grew by 1 from the draw branch
        $this->assertEquals(1, $this->countTokens("card_event", "hand_$color"));
    }

    public function testFirstBowEnablesRangedAttack(): void {
        // Place a goblin 2 hexes from Alva — should be attackable thanks to range 2
        $goblin = "monster_goblin_20";
        $heroStart = $this->tokenLocation($this->heroId);
        // Find a hex 2 away along axis: pick one empty plains hex two rows from hero start
        // Grimheim hex_9_9 → hex_9_7 is 2 away. Move hero to hex_9_9 then place goblin at hex_9_7.
        $this->game->tokens->moveToken($this->heroId, "hex_9_9");
        $this->game->getMonster($goblin)->moveTo("hex_9_7", "");
        $this->game->hexMap->invalidateOccupancy();

        // Goblin hex should be a valid attack target at range 2
        $this->assertValidTarget("hex_9_7");
    }
}
