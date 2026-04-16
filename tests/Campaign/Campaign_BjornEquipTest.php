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
