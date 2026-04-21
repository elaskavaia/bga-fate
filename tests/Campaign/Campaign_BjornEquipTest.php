<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Bjorn's equipment cards.
 * Scripts full game turns using the harness GameDriver in-process.
 * Split from Campaign_BjornSoloTest for readability.
 */
class Campaign_BjornEquipTest extends CampaignBaseTest {
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

    // --- Home Sewn Cape (card_equip_1_24) ---

    public function testHomeSewnCapeGainsManaPerRuneRolled(): void {
        $cape = "card_equip_1_24";
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($cape, "tableau_$color");

        // Place a goblin adjacent so the attack has a target.
        $goblin = "monster_goblin_20";
        $goblinHex = "hex_7_9";
        $this->game->getMonster($goblin)->moveTo($goblinHex, "");

        // Use both action markers so only attack is available.
        $this->game->tokens->moveToken("marker_" . $color . "_1", "aslot_" . $color . "_empty_1");
        $this->game->tokens->moveToken("marker_" . $color . "_2", "aslot_" . $color . "_empty_2");

        // Roll: 2 runes (3) + 1 hit (5)
        $this->seedRand([3, 3, 5]);
        $this->respond("actionAttack");

        // Cape's onRoll hook should have placed 2 green crystals on it.
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", $cape);
        $this->assertCount(2, $crystals, "Home Sewn Cape should have 2 mana from 2 runes rolled");
    }

    // --- Black Arrows (card_equip_1_20) ---

    public function testBlackArrowsOnEnterSeeds3Arrows(): void {
        $blackArrows = "card_equip_1_20";
        $color = $this->getActivePlayerColor();

        // Card starts in supply — no yellow crystals on it
        $this->assertEquals(0, $this->countTokens("crystal_yellow", $blackArrows));

        // Gain equipment via Op_gainEquip — seeds deck so Black Arrows is on top, then run the op
        $this->seedDeck("deck_equip_$color", [$blackArrows]);
        $op = $this->game->machine->instantiateOperation("gainEquip", $color);
        $op->resolve();

        // Card should now be on tableau with 3 yellow crystals (arrows)
        $this->assertEquals("tableau_$color", $this->tokenLocation($blackArrows));
        $this->assertEquals(3, $this->countTokens("crystal_yellow", $blackArrows));
    }

    public function testBlackArrowsSpendArrowAdds3Damage(): void {
        $blackArrows = "card_equip_1_20";
        $color = $this->getActivePlayerColor();
        $goblin = "monster_goblin_20";
        $heroHex = "hex_5_9";
        $goblinHex = "hex_5_8";

        // Gain equipment via Op_gainEquip — onEnter seeds 3 arrows
        $this->seedDeck("deck_equip_$color", [$blackArrows]);
        $op = $this->game->machine->instantiateOperation("gainEquip", $color);
        $op->resolve();
        $this->assertEquals(3, $this->countTokens("crystal_yellow", $blackArrows));

        // Place goblin adjacent to heroHex
        $this->game->getMonster($goblin)->moveTo($goblinHex, "");

        // Action 1: Move hero from Grimheim to heroHex (adjacent to goblin)
        $this->respond($heroHex);

        // Action 2: Attack the goblin
        $this->respond($goblinHex);

        // Now in free-action phase after attack — Black Arrows should be offered
        $this->assertValidTarget($blackArrows, "Black Arrows should be usable after attack");

        // Count dice on display_battle before using arrows
        $diceBefore = $this->countTokens("die_attack", "display_battle");

        // Use Black Arrows — spends 1 arrow, adds 3 damage dice
        $this->respond($blackArrows);
        $this->confirmCardEffect();

        // Verify: 1 arrow spent (2 remaining), 3 damage dice added
        $this->assertEquals(2, $this->countTokens("crystal_yellow", $blackArrows));
        $diceAfter = $this->countTokens("die_attack", "display_battle");
        $this->assertEquals($diceBefore + 3, $diceAfter, "Black Arrows should add 3 damage dice");
    }

    public function testBlackArrowsNotOfferedWhenNoArrows(): void {
        $blackArrows = "card_equip_1_20";
        $color = $this->getActivePlayerColor();
        $goblin = "monster_goblin_20";
        $heroHex = "hex_5_9";
        $goblinHex = "hex_5_8";

        // Place Black Arrows on tableau with 0 arrows
        $this->game->tokens->moveToken($blackArrows, "tableau_$color");

        // Place goblin adjacent to heroHex
        $this->game->getMonster($goblin)->moveTo($goblinHex, "");

        // Action 1: Move hero to heroHex
        $this->respond($heroHex);

        // Action 2: Attack goblin
        $this->respond($goblinHex);

        // Black Arrows should NOT be offered — no arrows to spend
        $this->assertNotValidTarget($blackArrows, "Black Arrows should not be usable with 0 arrows");
    }

    // --- Trollbane (card_equip_1_22) ---

    public function testTrollbaneOfferedWhenAttackingTrollkin(): void {
        $trollbane = "card_equip_1_22";
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($trollbane, "tableau_$color");

        // Place a goblin (trollkin) adjacent
        $goblin = "monster_goblin_20";
        $goblinHex = "hex_7_9";
        $this->game->getMonster($goblin)->moveTo($goblinHex, "");

        // Hero at hex_8_9 (default), adjacent to goblin
        $this->seedRand([5, 5, 5]);
        $this->respond($goblinHex);

        // Hierarchical dispatch: Trigger::ActionAttack chains through Roll, so
        // Bjorn Hero I (on=Roll) and Trollbane (on=ActionAttack) share one
        // useCard prompt. Trollbane should be a valid target directly.
        $this->assertOperation("useCard");
        $this->assertValidTarget($trollbane, "Trollbane should be offered when attacking trollkin");
    }

    // --- Quiver (card_equip_1_18) ---
    // r=costDamage:addDamage, on=TActionAttack, durability=3, strength=1.
    // Passive +1 strength; during an attack, spend 1 durability (red crystal on card)
    // → add 1 hit die to the attack.

    public function testQuiverAddsDamageDuringAttackAtDurabilityCost(): void {
        $quiver = "card_equip_1_18";
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($quiver, "tableau_$color");

        // Place Bjorn out of Grimheim, brute (health=3) at range 2
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $brute = "monster_brute_1";
        $this->game->getMonster($brute)->moveTo("hex_5_9", "");

        // Seed all miss dice so base attack damage = 0; Quiver adds 1 guaranteed hit.
        // Strength = Bjorn I(2) + First Bow(1) + Quiver(1) = 4 → 4 dice rolled.
        $this->seedRand([1, 1, 1, 1]);
        $this->respond("hex_5_9");

        // TActionAttack + TRoll chain share one useCard prompt that lists both
        // Quiver and Bjorn Hero I as options — pick Quiver directly.
        $this->assertOperation("useCard");
        $this->assertValidTarget($quiver);
        $this->respond($quiver);
        $this->confirmCardEffect();
        $this->skipIfOp("useCard"); // dismiss any further trigger prompts

        // 1 durability spent (1 red crystal on the card)
        $this->assertEquals(1, $this->countDamage($quiver));
        // Brute takes 1 damage (base=0 + addDamage 1)
        $this->assertEquals(1, $this->countDamage($brute));
        $this->assertEquals("hex_5_9", $this->tokenLocation($brute));
    }

    // --- Bone Bane Bow (card_equip_1_16) ---
    // r=counter(countRunes):dealDamage(adj_attack), on=TActionAttack, strength=3, range=2.
    // Main weapon. On attack: count runes rolled → deal that much damage to a
    // monster adjacent to the attack target hex.

    public function testBoneBaneBowDealsRuneCountDamageToAdjacentMonster(): void {
        $bow = "card_equip_1_16";
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($bow, "tableau_$color");
        // Remove First Bow so Bone Bane Bow is the sole main weapon.
        $this->game->tokens->moveToken("card_equip_1_15", "limbo");

        // Bjorn at hex_7_9, primary target at hex_5_9 (range 2),
        // secondary (adjacent to primary) at hex_4_9.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $primary = "monster_brute_1"; // health=3, survives 1 damage
        $secondary = "monster_brute_2"; // health=3, survives 2 damage
        $this->game->getMonster($primary)->moveTo("hex_5_9", "");
        $this->game->getMonster($secondary)->moveTo("hex_4_9", "");

        // Seed 2 runes + 1 hit. Strength = Bjorn I(2) + Bone Bane Bow(3) = 5 dice.
        // 2 runes (3), 1 hit (5), 2 miss (1) → primary takes 1 damage, 2 runes count.
        $this->seedRand([3, 3, 5, 1, 1]);
        $this->respond("hex_5_9");

        // TActionAttack useCard prompt — pick Bone Bane Bow.
        $this->assertOperation("useCard");
        $this->assertValidTarget($bow);
        $this->respond($bow);
        $this->confirmCardEffect();

        // Primary took 1 damage from attack roll; secondary took 2 (rune count) from bow.
        $this->assertEquals(1, $this->countDamage($primary));
        $this->assertEquals(2, $this->countDamage($secondary));
    }

    // --- Home Sewn Cape (card_equip_1_24) spend branches ---
    // r=(spendUse:2spendMana:1move)/(on(TResolveHits):3spendMana:2preventDamage), on=custom.

    public function testHomeSewnCapeSpendManaToMove(): void {
        $cape = "card_equip_1_24";
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($cape, "tableau_$color", 0);
        // Seed 2 mana on the cape (required to spend).
        $this->game->effect_moveCrystals($this->heroId, "green", 2, $cape, ["message" => ""]);

        // Place Bjorn outside Grimheim so move has somewhere to go.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");

        $this->assertValidTarget($cape);
        $this->respond($cape);

        // Two branches: choice_0 = 2spendMana:1move, choice_1 = 3spendMana:2preventDamage.
        // Only choice_0 is valid (2 mana available, no pending dealDamage for prevent).
        $this->assertValidTarget("choice_0");
        $this->assertNotValidTarget("choice_1");
        $this->respond("choice_0");

        // 1move sub-op prompts for destination hex.
        $this->respond("hex_6_9");

        $this->assertEquals(0, $this->countTokens("crystal_green", $cape));
        $this->assertEquals("hex_6_9", $this->tokenLocation($this->heroId));
    }

    public function testHomeSewnCapeSpendManaToPreventDamage(): void {
        $cape = "card_equip_1_24";
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($cape, "tableau_$color");
        // Seed 3 mana on the cape (required for the prevent branch).
        $this->game->effect_moveCrystals($this->heroId, "green", 3, $cape, ["message" => ""]);

        // Place Bjorn adjacent to a goblin that will attack him on the monster turn.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $goblin = "monster_goblin_20";
        $this->game->getMonster($goblin)->moveTo("hex_7_8", "");
        $this->seedRand([5]); // goblin str=1, one hit

        // Burn two real actions → end of player turn → monster turn → goblin attacks Bjorn.
        $this->respond("actionPractice");
        $this->respond("actionFocus");

        $this->skipOp("turn"); // end turn → monster turn
        $this->skipOp("drawEvent");

        // Monster attack should now have a pending dealDamage → cape prevent branch is valid.

        $this->assertOperation("useCard");
        $this->assertValidTarget($cape);
        $this->respond($cape);

        $this->respond("choice_1");

        $this->assertEquals(0, $this->countTokens("crystal_green", $cape));
        $this->assertEquals(0, $this->countDamage($this->heroId));
    }

    public function testTrollbaneNotOfferedAgainstNonTrollkin(): void {
        $trollbane = "card_equip_1_22";
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($trollbane, "tableau_$color");

        // Place a sprite (firehorde, not trollkin) adjacent
        $sprite = "monster_sprite_1";
        $spriteHex = "hex_7_9";
        $this->game->getMonster($sprite)->moveTo($spriteHex, "");

        $this->seedRand([5, 5, 5]);
        $this->respond("actionAttack");
        $this->respond($spriteHex);

        $args = $this->getOpArgs();
        if (($args["type"] ?? "") === "useCard" && in_array("card_hero_1_1", $args["target"] ?? [])) {
            $this->skip();
            $args = $this->getOpArgs();
        }

        // Trollbane should NOT be offered — filter rejects non-trollkin
        if (($args["type"] ?? "") === "useCard") {
            $this->assertNotValidTarget($trollbane, "Trollbane should not be usable against firehorde");
        }
    }
}
