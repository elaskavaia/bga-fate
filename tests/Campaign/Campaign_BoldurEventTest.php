<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Boldur's event cards.
 * Scripts full game turns using the harness GameDriver in-process.
 */
class Campaign_BoldurEventTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]); // Solo Boldur
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());

        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    // --- Berserk (card_event_4_32) ---
    // r=spendHealth:3addDamage, on=TActionAttack
    // "Take 1 unpreventable damage to add 3 damage to this attack."

    public function testBerserkTakesUnpreventableDamageAndAddsAttackDamage(): void {
        $color = $this->getActivePlayerColor();
        $berserk = "card_event_4_32_1";
        $this->seedHand($berserk, $color);

        // Move Boldur out of Grimheim, place a troll (health=6) adjacent to survive 3 damage
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_7_8", "");

        $heroDamageBefore = $this->countDamage($this->heroId);
        // Seed 3 miss dice → base attack deals 0 damage; Berserk adds 3.
        $this->seedRand([1, 1, 1]);
        $this->respond("actionAttack");

        // TActionAttack trigger offers Berserk
        $this->assertOperation("useCard");
        $this->assertValidTarget($berserk);
        $this->respond($berserk);
        $this->confirmCardEffect();
        $this->skipIfOp("useCard");

        // Boldur took 1 unpreventable damage (armor is bypassed)
        $this->assertEquals($heroDamageBefore + 1, $this->countDamage($this->heroId));
        // Troll took 3 damage from addDamage (base roll = 0 hits)
        $this->assertEquals(3, $this->countDamage($troll));
    }

    // --- Boldur's Gate (card_event_4_36) ---
    // r=in(Grimheim):2spendXp:addTownPiece — spend 2 XP in Grimheim to restore a town piece.

    public function testBoldursGateSpendsTwoXpToRestoreTownPiece(): void {
        $color = $this->getActivePlayerColor();
        $gate = "card_event_4_36_1";
        $this->seedHand($gate, $color);

        // Boldur starts in Grimheim — confirm.
        $this->assertTrue($this->game->hexMap->isInGrimheim($this->tokenLocation($this->heroId)));

        // Seed 2 gold/XP on the tableau.
        $this->game->effect_moveCrystals($this->heroId, "yellow", 2, "tableau_$color", ["message" => ""]);
        $xpBefore = $this->countXp();

        // Destroy a house so addTownPiece has something to restore.
        $house = array_key_first($this->game->tokens->getTokensOfTypeInLocation("house", "hex%"));
        $this->game->tokens->moveToken($house, "limbo");

        $this->assertValidTarget($gate);
        $this->respond($gate);
        $this->confirmCardEffect();

        $this->assertEquals($xpBefore - 2, $this->countXp());
        $this->assertNotEquals("limbo", $this->tokenLocation($house));
    }

    // --- Portable Smithy (card_event_4_38) ---
    // r=spendAction(actionPrepare):gainEquip — spend prepare action to draw top of equip deck onto tableau.

    public function testPortableSmithySpendsPrepareActionToGainEquipment(): void {
        $color = $this->getActivePlayerColor();
        $smithy = "card_event_4_38_1";
        $this->seedHand($smithy, $color);

        // Seed a known equip card on top of the deck so gainEquip is deterministic.
        $equip = "card_equip_4_25"; // Dwarf Pick — passive +1 strength, no onCardEnter side effects
        $this->seedDeck("deck_equip_$color", [$equip]);

        $hero = $this->game->getHero($color);
        $this->assertNotContains("actionPrepare", $hero->getActionsTaken());

        $this->assertValidTarget($smithy);
        $this->respond($smithy);
        $this->confirmCardEffect(); // spendAction(actionPrepare) confirm

        // Prepare action consumed.
        $this->assertContains("actionPrepare", $hero->getActionsTaken());
        // Equip card moved to tableau.
        $this->assertEquals("tableau_$color", $this->tokenLocation($equip));
    }

    // --- Miner (card_event_4_27) ---
    // r=adj(mountain):2gainXp — gain 2 XP if hero is adjacent to a mountain area.

    public function testMinerGainsTwoXpWhenAdjacentToMountain(): void {
        $color = $this->getActivePlayerColor();
        $miner = "card_event_4_27_1";
        $this->seedHand($miner, $color);

        // hex_14_2 is plains, adjacent to mountain hex_14_1 — gate passes.
        $this->game->tokens->moveToken($this->heroId, "hex_14_2");
        $xpBefore = $this->countXp();

        $this->assertValidTarget($miner);
        $this->respond($miner);
        $this->confirmCardEffect();

        $this->assertEquals($xpBefore + 2, $this->countXp());
    }

    // --- Short Temper (card_event_4_28) ---
    // r=killMonster(adj,'healthRem<=2') — instantly kill an adjacent monster with healthRem<=2.

    public function testShortTemperKillsAdjacentLowHealthMonster(): void {
        $color = $this->getActivePlayerColor();
        $shortTemper = "card_event_4_28_1";
        $this->seedHand($shortTemper, $color);

        // Boldur outside Grimheim; goblin (health=2, healthRem=2) adjacent → matches filter.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $goblin = "monster_goblin_20";
        $this->game->getMonster($goblin)->moveTo("hex_7_8", "");

        $this->assertValidTarget($shortTemper);
        $this->respond($shortTemper);
        // killMonster prompts for the target hex.
        $this->respond("hex_7_8");

        $this->assertEquals("supply_monster", $this->tokenLocation($goblin));
    }

    // --- Dodge (card_event_4_35) ---
    // r=2preventDamage, on=TResolveHits — reactively prevent up to 2 incoming damage.

    public function testDodgePreventsIncomingMonsterDamage(): void {
        $color = $this->getActivePlayerColor();
        $dodge = "card_event_4_35_1";
        $this->seedHand($dodge, $color);

        // Move Boldur out of Grimheim, place a goblin adjacent so it attacks on the monster turn.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $goblin = "monster_goblin_20";
        $this->game->getMonster($goblin)->moveTo("hex_7_8", "");
        $this->seedRand([5]); // goblin str=1 — one guaranteed hit

        // Burn both player actions → end of player turn → monster turn → goblin attacks Boldur.
        $this->respond("actionPractice");
        $this->respond("actionFocus");

        $this->skipOp("turn");
        $this->skipOp("drawEvent");

        // TResolveHits fires before dealDamage resolves — Dodge offered as a useCard target.
        $this->assertOperation("useCard");
        $this->assertValidTarget($dodge);
        $this->respond($dodge);

        // 1 hit prevented (Dodge prevents up to 2, only 1 incoming) → Boldur takes 0 damage.
        $this->assertEquals(0, $this->countDamage($this->heroId));
        // Card moved out of hand (discarded after use).
        $this->assertNotEquals("hand_$color", $this->tokenLocation($dodge));
    }

    // --- Rest (card_event_4_30) ---
    // r=2heal(self) — "Heal 2 damage from Boldur."

    public function testRestHeals2DamageFromBoldur(): void {
        $color = $this->getActivePlayerColor();
        $rest = "card_event_4_30_1";
        $this->seedHand($rest, $color);

        $this->game->effect_moveCrystals($this->heroId, "red", 3, $this->heroId, ["message" => ""]);
        $this->assertEquals(3, $this->countDamage($this->heroId));

        $this->assertValidTarget($rest);
        $this->respond($rest);
        $this->confirmCardEffect();

        $this->assertEquals(1, $this->countDamage($this->heroId));
        $this->assertNotEquals("hand_$color", $this->tokenLocation($rest));
    }

    // --- Kick (card_event_4_31) ---
    // r=dealDamage(adj),moveMonster(marked)
    // Deal 1 damage to an adjacent monster and move it 1 area.
    public function testKickDamagesAdjacentMonsterAndMovesIt(): void {
        $color = $this->getActivePlayerColor();
        $kick = "card_event_4_31_1";
        $this->seedHand($kick, $color);

        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $troll = "monster_troll_1"; // hp=6 so it survives the 1 damage
        $this->game->getMonster($troll)->moveTo("hex_7_8", "");

        $this->assertValidTarget($kick);
        $this->respond($kick);
        // dealDamage(adj) auto-resolves (single adjacent monster); marker_attack set on hex_7_8.
        // moveMonster(marked) Phase 1 auto-resolves to that hex; Phase 2 prompts for destination.
        $this->assertOperation("moveMonster");
        $destinations = $this->getOpArgs()["target"] ?? [];
        $this->assertNotEmpty($destinations, "Kick should offer hexes to push the troll to");
        $newHex = $destinations[0];
        $this->respond($newHex);

        $this->assertEquals(1, $this->countDamage($troll), "Troll takes 1 damage from Kick");
        $this->assertEquals($newHex, $this->tokenLocation($troll), "Troll was moved by Kick");
        $this->assertNotEquals("hex_7_8", $newHex, "Troll moved off its original hex");
        $this->assertNotEquals("hand_$color", $this->tokenLocation($kick), "Kick discarded after use");
    }

    // --- Maneuver (card_event_4_29) ---
    // r=1move — "Move 1 area."

    public function testManeuverMovesHeroOneArea(): void {
        $color = $this->getActivePlayerColor();
        $maneuver = "card_event_4_29_1";
        $this->seedHand($maneuver, $color);

        // Park Boldur on a plains hex outside Grimheim so destinations are unambiguous.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");

        $this->assertValidTarget($maneuver);
        $this->respond($maneuver);

        // 1move sub-op prompts for destination hex.
        $this->respond("hex_6_9");

        $this->assertEquals("hex_6_9", $this->tokenLocation($this->heroId));
        $this->assertNotEquals("hand_$color", $this->tokenLocation($maneuver));
    }

    // --- Durability (card_event_4_34) ---
    // r=repairCard(max) — "Remove all damage from an equipment card."
    public function testDurabilityRemovesAllDamageFromChosenEquipCard(): void {
        $color = $this->getActivePlayerColor();
        $durability = "card_event_4_34_1";
        $this->seedHand($durability, $color);

        $picked = "card_equip_4_15"; // Boldur's First Pick
        $other = "card_equip_4_25"; // Dwarf Pick
        $this->game->tokens->moveToken($picked, "tableau_$color");
        $this->game->tokens->moveToken($other, "tableau_$color");

        // Damage both cards so the player has a real choice (multi-target prompt).
        $this->game->effect_moveCrystals($this->heroId, "red", 3, $picked, ["message" => ""]);
        $this->game->effect_moveCrystals($this->heroId, "red", 2, $other, ["message" => ""]);

        $this->assertValidTarget($durability);
        $this->respond($durability);
        // repairCard(max) prompts for the target card; pick Boldur's First Pick.
        $this->assertOperation("repairCard");
        $this->respond($picked);

        $this->assertEquals(0, $this->countDamage($picked), "All damage stripped from the chosen card");
        $this->assertEquals(2, $this->countDamage($other), "Other card untouched");
        $this->assertNotEquals("hand_$color", $this->tokenLocation($durability), "Durability discarded after use");
    }

    // --- Seek Shelter (card_event_4_37) ---
    // r=[0,2]move(locationOnly),0setAtt(move)
    // "Move up to 2 areas into a location. You may not move more this turn."

    public function testSeekShelterMovesHeroToLocationAndZerosMove(): void {
        $color = $this->getActivePlayerColor();
        $seekShelter = "card_event_4_37_1";
        $this->seedHand($seekShelter, $color);

        // hex_11_8 is within 2 steps of Grimheim — a named location.
        $this->game->tokens->dbSetTokenLocation($this->heroId, "hex_11_8");

        $hero = $this->game->getHero($color);
        $this->assertGreaterThan(0, $hero->getNumberOfMoves(), "Hero should start the turn with moves available");

        $this->assertValidTarget($seekShelter);
        $this->respond($seekShelter);
        $this->confirmCardEffect();

        // Every offered target must be a named-location hex.
        $targets = $this->getOpArgs()["target"] ?? [];
        $this->assertNotEmpty($targets, "Seek Shelter should offer at least one location hex");
        foreach ($targets as $hexId) {
            $this->assertNotEquals("", $this->game->hexMap->getHexNamedLocation($hexId), "Seek Shelter offered non-location hex $hexId");
        }

        $this->respond($targets[0]);

        $finalHex = $this->tokenLocation($this->heroId);
        $this->assertNotEquals("", $this->game->hexMap->getHexNamedLocation($finalHex), "Hero should end on a named-location hex");
        $this->assertNotEquals("hand_$color", $this->tokenLocation($seekShelter));
        $this->assertEquals(0, $hero->getNumberOfMoves(), "Move tracker should be zeroed after Seek Shelter");
        $this->assertNotValidTarget("actionMove", "Hero should not be able to take a move action this turn");
    }

    // --- Focus (card_event_4_33) ---
    // r=spendAction(actionFocus):gainXp:gainMana:drawEvent
    // Spend a focus action to gain 1 XP, 1 mana on a card, and draw 1 event.
    public function testFocusSpendsFocusActionForXpManaAndCardDraw(): void {
        $color = $this->getActivePlayerColor();
        $focus = "card_event_4_33_1";
        $this->seedHand($focus, $color);

        // Boldur's Rapid Strike I (mana=1) is the sole mana-holding card on tableau →
        // gainMana auto-resolves onto it.
        $manaCard = "card_ability_4_3";
        $manaBefore = $this->countTokens("crystal_green", $manaCard);

        // Seed one event card so drawEvent has something to pull.
        $drawnCard = "card_event_4_35_1"; // Dodge
        $this->seedDeck("deck_event_$color", [$drawnCard]);

        $xpBefore = $this->countXp();

        $this->assertValidTarget($focus);
        $this->respond($focus);
        $this->confirmCardEffect(); // spendAction(actionFocus) confirm

        // Focus action slot consumed.
        $hero = $this->game->getHero($color);
        $this->assertContains("actionFocus", $hero->getActionsTaken());
        $this->assertEquals($xpBefore + 1, $this->countXp());
        $this->assertEquals($manaBefore + 1, $this->countTokens("crystal_green", $manaCard));
        $this->assertEquals("hand_$color", $this->tokenLocation($drawnCard));
        $this->assertNotEquals("hand_$color", $this->tokenLocation($focus));
    }
}
