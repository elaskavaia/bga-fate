<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Bjorn's event cards.
 * Scripts full game turns using the harness GameDriver in-process.
 * Split from Campaign_BjornSoloTest for readability.
 */
class Campaign_BjornEventTest extends CampaignBaseTest {
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

    // --- Rest (card_event_1_27) ---

    public function testRestHeals2DamageFromBjorn(): void {
        $restCard = "card_event_1_27_1";
        $color = $this->getActivePlayerColor();
        // Put Rest in hand and add 3 damage to hero
        $this->seedHand($restCard, $color);
        $this->game->effect_moveCrystals($this->heroId, "red", 3, $this->heroId, ["message" => ""]);
        $this->assertEquals(3, $this->countDamage($this->heroId));

        // Play Rest — r=2heal(self), auto-resolves (self target)
        $this->assertValidTarget($restCard);
        $this->respond($restCard);
        $this->confirmCardEffect();

        // Hero should have 1 damage (3 - 2 healed)
        $this->assertEquals(1, $this->countDamage($this->heroId));
    }

    public function testRestNotOfferedWhenNoDamage(): void {
        $restCard = "card_event_1_27_1";
        $color = $this->getActivePlayerColor();
        $this->seedHand($restCard, $color);
        $this->assertEquals(0, $this->countDamage($this->heroId));

        // Rest should NOT be a valid target (no damage to heal)
        $this->assertNotValidTarget($restCard, "Rest should not be offered when hero has no damage");
    }

    // --- Sewing (card_event_1_30) ---

    public function testSewingRemovesOneDamageFromEachCard(): void {
        $sewing = "card_event_1_30_1";
        $color = $this->getActivePlayerColor();
        $this->seedHand($sewing, $color);

        // Make sure a few cards are on tableau and pre-damage them to varying amounts.
        $this->game->tokens->moveToken("card_equip_1_15", "tableau_$color"); // Bjorn's First Bow
        $this->game->tokens->moveToken("card_equip_1_21", "tableau_$color"); // Helmet
        $this->game->tokens->moveToken("card_ability_1_7", "tableau_$color"); // Stitching I (undamaged)

        $this->game->effect_moveCrystals("card_equip_1_15", "red", 2, "card_equip_1_15", ["message" => ""]);
        $this->game->effect_moveCrystals("card_equip_1_21", "red", 1, "card_equip_1_21", ["message" => ""]);

        // Play Sewing — r=1repairCard(all), auto-resolves (single "confirm" target)
        $this->assertValidTarget($sewing);
        $this->respond($sewing);
        $this->confirmCardEffect();

        // Each damaged card loses 1 damage; undamaged card stays at 0.
        $this->assertEquals(1, $this->countDamage("card_equip_1_15"));
        $this->assertEquals(0, $this->countDamage("card_equip_1_21"));
        $this->assertEquals(0, $this->countDamage("card_ability_1_7"));

        // Sewing should be discarded from hand after play.
        $this->assertNotEquals("hand_$color", $this->tokenLocation($sewing));
    }

    // --- Seek Shelter (card_event_1_34) ---

    public function testSeekShelterMovesHeroToLocation(): void {
        $seekShelter = "card_event_1_34_1";
        $color = $this->getActivePlayerColor();
        $this->seedHand($seekShelter, $color);

        // Move hero out to a non-location hex with a known named location reachable within 2.
        // From hex_11_8 the hero can reach Grimheim within 2 steps.
        $this->game->tokens->dbSetTokenLocation($this->heroId, "hex_11_8");

        // Sanity: before playing Seek Shelter, the hero's move tracker should be > 0.
        $hero = $this->game->getHero($color);
        $this->assertGreaterThan(0, $hero->getNumberOfMoves(), "Hero should start the turn with moves available");

        // Play Seek Shelter — r=[0,2]move(locationOnly),0setAtt(move). Prompts for a location hex.
        $this->assertValidTarget($seekShelter);
        $this->respond($seekShelter);
        $this->confirmCardEffect();

        // Every offered target must be a named-location hex.
        $args = $this->getOpArgs();
        $targets = $args["target"] ?? [];
        $this->assertNotEmpty($targets, "Seek Shelter should offer at least one location hex");
        foreach ($targets as $hexId) {
            $this->assertNotEquals("", $this->game->hexMap->getHexNamedLocation($hexId), "Seek Shelter offered non-location hex $hexId");
        }

        // Pick the first offered hex and resolve.
        $chosen = $targets[0];
        $this->respond($chosen);

        // Hero should have moved. If chosen hex was Grimheim, move redirects to the hero's
        // home hex, so only assert the hero ended up on a named-location hex.
        $finalHex = $this->tokenLocation($this->heroId);
        $this->assertNotEquals("", $this->game->hexMap->getHexNamedLocation($finalHex), "Hero should end on a named-location hex");

        // Seek Shelter is discarded from hand.
        $this->assertNotEquals("hand_$color", $this->tokenLocation($seekShelter));

        // After Seek Shelter resolves, the move tracker should be 0 — hero may not move more this turn.
        $this->assertEquals(0, $hero->getNumberOfMoves(), "Move tracker should be zeroed after Seek Shelter");
        // actionMove delegates to [1,N]move where N = move tracker; with N=0 the op has no valid
        // targets, so the turn state no longer offers actionMove as a valid action.
        $this->assertNotValidTarget("actionMove", "Hero should not be able to take a move action this turn");
    }

    // --- Back Down (card_event_1_29) ---

    public function testBackDownKillsMonsterCloserToGrimheim(): void {
        $backDown = "card_event_1_29_1";
        $color = $this->getActivePlayerColor();
        $this->seedHand($backDown, $color);

        // Move hero away from Grimheim so monsters can be closer
        $this->game->tokens->dbSetTokenLocation($this->heroId, "hex_5_9");

        // Place a goblin (rank 1) closer to Grimheim than hero
        $goblin = "monster_goblin_20";
        $goblinHex = "hex_7_9"; // closer to Grimheim than hex_5_9
        $this->game->getMonster($goblin)->moveTo($goblinHex, "");

        // Play Back Down
        $this->assertValidTarget($backDown);
        $this->respond($backDown);

        // killMonster is skippable so doesn't auto-resolve — select target
        $args = $this->getOpArgs();
        $this->assertEquals("killMonster", $args["type"] ?? "");
        $this->assertValidTarget($goblinHex);
        $this->respond($goblinHex);

        // Goblin should be dead
        $this->assertEquals("supply_monster", $this->tokenLocation($goblin));
    }

    public function testBackDownNotOfferedWhenMonsterFartherFromGrimheim(): void {
        $backDown = "card_event_1_29_1";
        $color = $this->getActivePlayerColor();
        $this->seedHand($backDown, $color);

        // Hero in Grimheim (hex_8_9), goblin farther away
        $goblin = "monster_goblin_20";
        $goblinHex = "hex_5_9";
        $this->game->getMonster($goblin)->moveTo($goblinHex, "");

        // Back Down should NOT be valid (goblin is farther from Grimheim than hero)
        $this->assertNotValidTarget($backDown, "Back Down should not be offered when no monster is closer to Grimheim");
    }

    public function testBackDownExcludesRank3(): void {
        $backDown = "card_event_1_29_1";
        $color = $this->getActivePlayerColor();
        $this->seedHand($backDown, $color);

        // Move hero away from Grimheim
        $this->game->tokens->dbSetTokenLocation($this->heroId, "hex_5_9");

        // Place a troll (rank 3) closer to Grimheim — should not be targetable
        $troll = "monster_troll_1";
        $trollHex = "hex_7_9";
        $this->game->getMonster($troll)->moveTo($trollHex, "");

        // Back Down should NOT be valid (troll is rank 3)
        $this->assertNotValidTarget($backDown, "Back Down should not be offered for rank 3 monsters");
    }

    // --- Prey (card_event_1_25) ---

    public function testPreyMarksRank3MonsterWithTwoYellow(): void {
        $prey = "card_event_1_25_1";
        $color = $this->getActivePlayerColor();
        $this->seedHand($prey, $color);

        // Place a troll (rank 3) somewhere on the map
        $troll = "monster_troll_1";
        $trollHex = "hex_7_9";
        $this->game->getMonster($troll)->moveTo($trollHex, "");

        // Play Prey, then select the troll hex
        $this->assertValidTarget($prey);
        $this->respond($prey);
        $this->assertValidTarget($trollHex);
        $this->respond($trollHex);

        // Troll should now carry 2 yellow crystals (bonus XP)
        $this->assertEquals(2, $this->countTokens("crystal_yellow", $troll));
    }

    public function testPreyBonusXpAwardedOnKill(): void {
        $prey = "card_event_1_25_1";
        $color = $this->getActivePlayerColor();
        $this->seedHand($prey, $color);

        $troll = "monster_troll_1";
        $trollHex = "hex_7_9";
        $this->game->getMonster($troll)->moveTo($trollHex, "");

        $baseXp = $this->game->getMonster($troll)->getXpReward();
        $xpBefore = $this->countXp();

        // Mark troll via Prey
        $this->assertValidTarget($prey);
        $this->respond($prey);
        $this->respond($trollHex);

        // Now kill the troll directly — killer receives base reward + 2 bonus.
        $health = $this->game->getMonster($troll)->getHealth();
        for ($i = 0; $i < $health; $i++) {
            $this->game->tokens->moveToken("crystal_red_" . ($i + 1), $troll);
        }
        $this->game->getMonster($troll)->applyDamageEffects(0, $this->heroId);

        $this->assertEquals("supply_monster", $this->tokenLocation($troll));
        $this->assertEquals($xpBefore + $baseXp + 2, $this->countXp());
    }

    public function testPreyNotOfferedWhenNoValidTarget(): void {
        $prey = "card_event_1_25_1";
        $color = $this->getActivePlayerColor();
        $this->seedHand($prey, $color);

        // Only rank-1/2 monsters on map — Prey should not be offered.
        $goblin = "monster_goblin_1";
        $this->game->getMonster($goblin)->moveTo("hex_7_9", "");

        $this->assertNotValidTarget($prey, "Prey should not be offered when no rank 3 / legend is available");
    }

    public function testPreyExcludesDamagedMonster(): void {
        $prey = "card_event_1_25_1";
        $color = $this->getActivePlayerColor();
        $this->seedHand($prey, $color);

        // Damaged troll — not a valid Prey target, and the only rank-3 on the map,
        // so the Prey card itself should not be offered as a free action.
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_7_9", "");
        $this->game->effect_moveCrystals($troll, "red", 1, $troll, ["message" => ""]);

        $this->assertNotValidTarget($prey, "Prey should not be offered when the only rank 3 monster is damaged");
    }

    public function testPreyExcludesDamagedMonsterWhenOtherValid(): void {
        $prey = "card_event_1_25_1";
        $color = $this->getActivePlayerColor();
        $this->seedHand($prey, $color);

        // One damaged troll and one undamaged jotunn — Prey offered, damaged hex rejected.
        $damaged = "monster_troll_1";
        $this->game->getMonster($damaged)->moveTo("hex_7_9", "");
        $this->game->effect_moveCrystals($damaged, "red", 1, $damaged, ["message" => ""]);

        $fresh = "monster_jotunn_1";
        $freshHex = "hex_12_5";
        $this->game->getMonster($fresh)->moveTo($freshHex, "");

        $this->assertValidTarget($prey);
        $this->respond($prey);
        $this->assertNotValidTarget("hex_7_9", "Damaged rank 3 monster should not be a valid Prey target");
        $this->assertValidTarget($freshHex);
    }

    // --- Master Shot (card_event_1_26) ---

    public function testMasterShotAdds2DamageDuringAttack(): void {
        $masterShot = "card_event_1_26_1";
        $color = $this->getActivePlayerColor();
        $this->seedHand($masterShot, $color);

        // Place a troll adjacent (health=7)
        $troll = "monster_troll_1";
        $trollHex = "hex_7_9";
        $this->game->getMonster($troll)->moveTo($trollHex, "");

        // Bjorn strength=3, all hits
        $this->seedRand([5, 5, 5]);
        $this->respond("actionAttack");

        // Hierarchical dispatch: one merged useCard prompt offers Bjorn Hero I
        // (on=Roll) alongside Master Shot (on=ActionAttack).
        $this->assertOperation("useCard");
        $this->assertValidTarget($masterShot);

        $this->respond($masterShot);
        $this->confirmCardEffect();

        $this->skipIfOp("useCard");

        // Master Shot adds 2 damage dice → troll takes 3 hits + 2 bonus = 5 total damage
        $this->assertEquals(5, $this->countDamage($troll), "Troll should have 5 damage (3 hits + 2 from Master Shot)");

        // Master Shot card should be discarded from hand
        $this->assertNotEquals("hand_$color", $this->tokenLocation($masterShot));
    }

    public function testMasterShotNotOfferedOutsideAttack(): void {
        $masterShot = "card_event_1_26_1";
        $color = $this->getActivePlayerColor();
        $this->seedHand($masterShot, $color);

        // Master Shot has on=actionAttack, so it should NOT be a valid free action target
        $this->assertNotValidTarget($masterShot, "Master Shot should not be playable outside an attack");
    }

    // --- Limber Bow (card_event_1_32) ---

    public function testLimberBowAddsRange2AndResetsAfterTurn(): void {
        $limberBow = "card_event_1_32_1";
        $color = $this->getActivePlayerColor();
        $this->seedHand($limberBow, $color);

        $hero = $this->game->getHero($color);
        $baseRange = $hero->getAttackRange();

        // Play Limber Bow — auto-resolves (no target needed)
        $this->assertValidTarget($limberBow);
        $this->respond($limberBow);
        $this->confirmCardEffect();

        // Range should be +2
        $this->assertEquals($baseRange + 2, $hero->getAttackRange());

        // Do two actions and end turn
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skip(); // skip free actions → end turn → monster turn

        // Wait for next player turn
        $state = $this->getStateArgs();
        $this->assertEquals("PlayerTurn", $state["name"]);

        // Range should be back to base
        $hero = $this->game->getHero($color);
        $this->assertEquals($baseRange, $hero->getAttackRange());
    }

    // --- Piercing Arrows (card_event_1_33) ---

    public function testPiercingArrowsOfferedOnRollTrigger(): void {
        $piercingArrows = "card_event_1_33_1";
        $color = $this->getActivePlayerColor();
        $this->seedHand($piercingArrows, $color);

        // Place a troll adjacent (health=7, survives the attack so we can check damage)
        $troll = "monster_troll_1";
        $trollHex = "hex_7_9";
        $this->game->getMonster($troll)->moveTo($trollHex, "");

        // Roll: 2 runes (3) + 1 hit (5) → 1 base damage + 2 from Piercing Arrows = 3 total
        $this->seedRand([3, 3, 5]);
        $this->respond("actionAttack");

        // trigger(roll) auto-resolves. useCard offers both hero card and Piercing Arrows.
        $args = $this->getOpArgs();
        $this->assertEquals("useCard", $args["type"] ?? "");
        $this->assertValidTarget($piercingArrows);

        // Play Piercing Arrows (hero card also offered but we pick the event)
        $this->respond($piercingArrows);
        $this->confirmCardEffect();

        $this->skipIfOp("useCard");

        // Troll should have 1 hit + 2 rune damage = 3 total damage
        $this->assertEquals(3, $this->countDamage($troll), "Troll should have 3 damage (1 hit + 2 from Piercing Arrows)");

        // Card should be discarded from hand
        $this->assertNotEquals("hand_$color", $this->tokenLocation($piercingArrows));
    }

    public function testPiercingArrowsNotOfferedWithNoRunes(): void {
        $piercingArrows = "card_event_1_33_1";
        $color = $this->getActivePlayerColor();
        $this->seedHand($piercingArrows, $color);

        // Place a troll adjacent
        $troll = "monster_troll_1";
        $trollHex = "hex_7_9";
        $this->game->getMonster($troll)->moveTo($trollHex, "");

        // Roll: all hits (5), 0 runes → counter(countRunes) evaluates to 0, card should not be offered
        $this->seedRand([5, 5, 5]);
        $this->respond("actionAttack");

        // trigger(roll) auto-resolves. Bjorn hero card offered first; skip it.
        $args = $this->getOpArgs();
        if (($args["type"] ?? "") === "useCard" && in_array("card_hero_1_1", $args["target"] ?? [])) {
            $this->skip();
            $args = $this->getOpArgs();
        }

        // Piercing Arrows should NOT be offered — 0 runes means counter is void
        if (($args["type"] ?? "") === "useCard") {
            $this->assertNotValidTarget($piercingArrows, "Piercing Arrows should not be offered with 0 runes");
        }
        // Otherwise we're already past the trigger phase — also correct

        // Troll should have 3 hits only (no rune bonus damage)
        $this->assertEquals(3, $this->countDamage($troll), "Troll should have 3 damage (3 hits, no Piercing Arrows)");
    }

    public function testPiercingArrowsNotOfferedOutsideRoll(): void {
        $piercingArrows = "card_event_1_33_1";
        $color = $this->getActivePlayerColor();
        $this->seedHand($piercingArrows, $color);

        // Piercing Arrows has on=roll, so it should NOT be playable as a free action
        $this->assertNotValidTarget($piercingArrows, "Piercing Arrows should not be playable outside a roll");
    }
}
