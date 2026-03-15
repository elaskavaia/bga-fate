<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration test: Solo Bjorn campaign.
 * Scripts full game turns using the harness GameDriver in-process.
 */
class Campaign_BjornSoloTest extends CampaignBaseTest {
    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1]); // Solo Bjorn

        // Seed monster deck — need several simple cards (setup draws 1, each turn end draws 1)
        $this->seedDeck("deck_monster_yellow", [
            "card_monster_7",  // Fiery Projectiles (Highlands, J,J,E)
            "card_monster_8",  // Whirlwinds (Highlands, E,E,E,E,E)
            "card_monster_9",  // Trending Monsters (Highlands, J,E,E,S,S)
            "card_monster_10", // Burnt Offerings
        ]);
        // Seed event deck with non-custom cards (Rest x2) to avoid Op_custom errors
        $this->seedDeck("deck_event_6cd0f6", [
            "card_event_1_27_1", // Rest
            "card_event_1_27_2", // Rest
        ]);
    }

    // TODO: debug — goblin dies even with all-miss dice. Need to investigate damage pipeline.
    // public function testMoveAttackMendKill(): void {
    //     // Place a goblin 3 hexes from Grimheim, adjacent to hex_5_9
    //     $this->game->hexMap->moveCharacterOnMap("monster_goblin_20", "hex_5_8");
    //     // Turn 1: move to hex_5_9, attack goblin (miss), skip, monster attacks hero
    //     // Turn 2: mend, attack again (hit), goblin dies
    // }

    public function testFirstTurnMoveAndPrepare(): void {
        // First action: Move — click a hex directly (delegate target from turn op)
        $this->respond("hex_7_8");

        $this->assertEquals("hex_7_8", $this->tokenLocation("hero_1"));

        // Second action: Prepare — draws Rest (seeded on top)
        $this->respond("actionPrepare");
        $this->respond("confirm"); // confirm the draw

        // Rest card should now be in hand
        $this->assertEquals("hand_6cd0f6", $this->tokenLocation("card_event_1_27_1"));

        // End turn (skip free actions)
        $this->skip();

        // Monster turn runs automatically, then back to player turn
        $state = $this->getStateArgs();
        $this->assertEquals("PlayerTurn", $state["name"]);
    }
}
