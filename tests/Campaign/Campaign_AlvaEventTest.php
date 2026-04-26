<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Alva's event cards.
 * Scripts full game turns using the harness GameDriver in-process.
 */
class Campaign_AlvaEventTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([2]); // Solo Alva
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());

        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    // --- Take a Knee (card_event_2_29) ---
    // r=c_supfire(inRange,not_legend) — suppress a non-Legend monster within attack range.

    public function testTakeAKneeSuppressesNonLegendMonsterInRange(): void {
        $color = $this->getActivePlayerColor();
        $takeAKnee = "card_event_2_29_1";
        $this->seedHand($takeAKnee, $color);

        // Alva at hex_7_9 (plains, outside Grimheim). Goblin at hex_5_9 (range 2 — within Alva's attack range).
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $goblin = "monster_goblin_20";
        $this->game->getMonster($goblin)->moveTo("hex_5_9", "");

        $this->assertValidTarget($takeAKnee);
        $this->respond($takeAKnee);
        // c_supfire prompts for target hex (one eligible monster in range)
        $this->respond("hex_5_9");

        // Stun marker placed on the goblin
        $this->assertEquals($goblin, $this->tokenLocation("stunmarker_$takeAKnee"));
    }

    // --- Rest (card_event_2_31) ---
    // r=2heal(self) — heal 2 damage from Alva.

    public function testRestHeals2DamageFromAlva(): void {
        $color = $this->getActivePlayerColor();
        $rest = "card_event_2_31_1";
        $this->seedHand($rest, $color);
        $this->game->effect_moveCrystals($this->heroId, "red", 3, $this->heroId, ["message" => ""]);

        $this->assertValidTarget($rest);
        $this->respond($rest);
        $this->confirmCardEffect();

        $this->assertEquals(1, $this->countDamage($this->heroId));
    }

    // --- Agility (card_event_2_28) ---
    // r=2move — move 2 areas.

    public function testAgilityMovesHeroTwoAreas(): void {
        $color = $this->getActivePlayerColor();
        $agility = "card_event_2_28_1";
        $this->seedHand($agility, $color);

        // Start Alva at hex_7_9; 2move resolves to a single 2-step destination
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");

        $this->assertValidTarget($agility);
        $this->respond($agility);
        $this->respond("hex_5_9");

        $this->assertEquals("hex_5_9", $this->tokenLocation($this->heroId));
    }

    // --- Popular (card_event_2_32) ---
    // r=in(Grimheim):2gainXp — gain 2 XP when played in Grimheim.

    public function testPopularGains2XpInGrimheim(): void {
        $color = $this->getActivePlayerColor();
        $popular = "card_event_2_32_1";
        $this->seedHand($popular, $color);

        // Alva starts in Grimheim — confirm then play
        $this->assertTrue($this->game->hexMap->isInGrimheim($this->tokenLocation($this->heroId)));
        $xpBefore = $this->countXp();

        $this->assertValidTarget($popular);
        $this->respond($popular);
        $this->confirmCardEffect();

        $this->assertEquals($xpBefore + 2, $this->countXp());
    }

    // --- Inspire Defense (card_event_2_30) ---
    // r=in(Grimheim):2spendManaAny:addTownPiece — in Grimheim, spend 2 mana (any tableau card) to add a town piece.

    public function testInspireDefenseAddsTownPieceInGrimheim(): void {
        $color = $this->getActivePlayerColor();
        $inspire = "card_event_2_30_1";
        $this->seedHand($inspire, $color);

        // Seed 1 mana on two different tableau cards to prove cross-card spending works.
        // card_ability_2_3 is on Alva's starting tableau; move card_ability_2_13 onto it too.
        $cardA = "card_ability_2_3"; // Hail of Arrows I (mana cap 1; seeded with 1 mana at setup)
        $cardB = "card_ability_2_13"; // Flexibility I
        $this->game->tokens->moveToken($cardB, "tableau_$color");
        $this->game->effect_moveCrystals($this->heroId, "green", 1, $cardB, ["message" => ""]);

        // Destroy a house so addTownPiece has something to restore
        $house = array_key_first($this->game->tokens->getTokensOfTypeInLocation("house", "hex%"));
        $this->game->tokens->moveToken($house, "limbo");

        $this->assertValidTarget($inspire);
        $this->respond($inspire);
        $this->confirmCardEffect();
        // First mana: pick cardA (2 candidates). The re-queued iteration has 1 eligible
        // target left (cardB) and auto-resolves.
        $this->respond($cardA);

        // Both cards drained; house restored
        $this->assertEquals(0, $this->countTokens("crystal_green", $cardA));
        $this->assertEquals(0, $this->countTokens("crystal_green", $cardB));
        $this->assertNotEquals("limbo", $this->tokenLocation($house));
    }

    // --- Back Down! (card_event_2_35) ---
    // r=killMonster(inRange,'rank<=2 and closerToGrimheim')

    public function testBackDownKillsRankLowMonsterCloserToGrimheim(): void {
        $color = $this->getActivePlayerColor();
        $backDown = "card_event_2_35_1";
        $this->seedHand($backDown, $color);

        // Move hero away from Grimheim so a goblin can be "closer"
        $this->game->tokens->moveToken($this->heroId, "hex_5_9");
        $goblin = "monster_goblin_20";
        $goblinHex = "hex_7_9"; // closer to Grimheim than hex_5_9, within Alva's range 2
        $this->game->getMonster($goblin)->moveTo($goblinHex, "");

        $this->assertValidTarget($backDown);
        $this->respond($backDown);
        $this->respond($goblinHex);

        $this->assertEquals("supply_monster", $this->tokenLocation($goblin));
    }

    // --- Prey (card_event_2_36) ---
    // r=c_prey — mark undamaged rank 3 / legend with 2 gold.

    public function testPreyMarksRank3WithTwoYellow(): void {
        $color = $this->getActivePlayerColor();
        $prey = "card_event_2_36_1";
        $this->seedHand($prey, $color);

        $troll = "monster_troll_1";
        $trollHex = "hex_7_9";
        $this->game->getMonster($troll)->moveTo($trollHex, "");

        $this->assertValidTarget($prey);
        $this->respond($prey);
        $this->respond($trollHex);

        // 2 yellow crystals placed on the troll
        $this->assertEquals(2, $this->countTokens("crystal_yellow", $troll));
    }

    // --- Piercing Arrows (card_event_2_34) ---
    // r=counter(countRunes):addDamage, on=TRoll — add 1 damage per rune rolled.

    public function testPiercingArrowsAddsOneDamagePerRune(): void {
        $color = $this->getActivePlayerColor();
        $piercing = "card_event_2_34_1";
        $this->seedHand($piercing, $color);

        // Alva out of Grimheim, brute (health=3) at range 2
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $brute = "monster_brute_1";
        $this->game->getMonster($brute)->moveTo("hex_5_9", "");

        // Roll: 2 runes (side 3), 1 miss (side 1) → 0 base hits, +2 damage from 2 runes
        $this->seedRand([3, 3, 1]);
        $this->respond("actionAttack");

        // TRoll trigger offers Piercing Arrows
        $this->assertOperation("useCard");
        $this->assertValidTarget($piercing);
        $this->respond($piercing);
        $this->confirmCardEffect();
        $this->skipIfOp("useCard");

        // Brute took 2 damage from the 2 addDamage dice
        $this->assertEquals(2, $this->countDamage($brute));
    }

    // --- Mastery (card_event_2_27) ---
    // r=4addRoll, on=TActionAttack — add 4 dice to the current attack roll.

    public function testMasteryAddsFourDiceToAttackRoll(): void {
        $color = $this->getActivePlayerColor();
        $mastery = "card_event_2_27_1";
        $this->seedHand($mastery, $color);

        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $brute = "monster_brute_1";
        $this->game->getMonster($brute)->moveTo("hex_5_9", "");

        // Base roll: 3 dice, all miss. Mastery's 4 added dice: all hit.
        // Alva strength=3 (hero 2 + First Bow 1) → base 3 dice + 4 added = 7 dice rolled.
        $this->seedRand([1, 1, 1, 5, 5, 5, 5]);
        $this->respond("actionAttack");

        $this->assertOperation("useCard");
        $this->assertValidTarget($mastery);
        $this->respond($mastery);
        $this->confirmCardEffect();
        $this->skipIfOp("useCard");

        // 4 hits added → brute dies (health=3)
        $this->assertEquals("supply_monster", $this->tokenLocation($brute));
    }

    // --- Multi-Shot (card_event_2_26) ---
    // r=2roll(inRange),2roll(inRange) — roll 2 dice at each of up to 2 different monsters in range.

    public function testMultiShotRollsAgainstTwoDifferentMonsters(): void {
        $color = $this->getActivePlayerColor();
        $multishot = "card_event_2_26_1";
        $this->seedHand($multishot, $color);

        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        // Two monsters in range 2
        $goblin = "monster_goblin_20";
        $brute = "monster_brute_1";
        $this->game->getMonster($goblin)->moveTo("hex_5_9", ""); // range 2
        $this->game->getMonster($brute)->moveTo("hex_7_8", ""); // adjacent

        // Two rolls of 2 dice each: seed hits for both
        $this->seedRand([5, 5, 5, 5]);

        $this->assertValidTarget($multishot);
        $this->respond($multishot);
        $this->confirmCardEffect(); // seq op wraps the two rolls, confirm first
        // Pick attacked hexes
        $this->respond(["hex_5_9", "hex_7_8"]);

        // Goblin (health=2) dead; brute (health=3) took 2 damage
        $this->assertEquals("supply_monster", $this->tokenLocation($goblin));
        $this->assertEquals(2, $this->countDamage($brute));
    }

    // --- Speedy Attack (card_event_2_33) ---
    // r=discardEvent:actionAttack — discard another card from hand to perform an attack action.

    public function testSpeedyAttackDiscardsCardAndPerformsAttack(): void {
        $color = $this->getActivePlayerColor();
        $speedy = "card_event_2_33_1";
        $toDiscard = "card_event_2_31_1"; // another Rest card
        $this->seedHand($speedy, $color);
        $this->seedHand($toDiscard, $color);

        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $goblin = "monster_goblin_20";
        $this->game->getMonster($goblin)->moveTo("hex_7_8", "");

        // 3 hits — enough to kill the goblin (health=2)
        $this->seedRand([5, 5, 5]);

        $this->assertValidTarget($speedy);
        $this->respond($speedy);
        // Speedy is discarded by useCard before its effect runs; only $toDiscard
        // is left in hand, so discardEvent auto-resolves; actionAttack auto-picks
        // the lone adjacent goblin.
        $this->skipIfOp("useCard"); // any post-attack triggers

        $this->assertEquals("supply_monster", $this->tokenLocation($goblin));
        $this->assertNotEquals("hand_$color", $this->tokenLocation($toDiscard));
    }
}
