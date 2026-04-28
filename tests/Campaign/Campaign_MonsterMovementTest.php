<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for monster movement during the monster turn.
 *
 * Validates the data-driven path rule: each non-Grimheim hex carries a `dir`
 * clock value (1/3/5/7/9/11) and monsters step in that direction. Road hexes
 * have `dir` pointing along the road toward Grimheim; off-road hexes have the
 * spawn-area's arrow direction.
 */
class Campaign_MonsterMovementTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1]); // Solo Bjorn

        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());
        // Park hero out of the way (forces actionPractice/actionFocus to be valid actions)
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");

        $this->seedDeck("deck_monster_yellow", [
            "card_monster_7",
            "card_monster_8",
            "card_monster_9",
            "card_monster_10",
        ]);
        $this->seedDeck("deck_event_" . $this->getActivePlayerColor(), [
            "card_event_1_27_1",
            "card_event_1_27_2",
        ]);
        $this->clearHand($this->getActivePlayerColor());
        $this->clearMonstersFromMap();
    }

    public function testMonsterOnRoadFollowsRoadTowardGrimheim(): void {
        // hex_12_2 is on the north road. Each road hex's `dir` points to the
        // next road hex toward Grimheim (5/SE here). Goblin move=2 → walks 2 hexes.
        $monster = "monster_goblin_20";
        $this->game->getMonster($monster)->moveTo("hex_12_2", "");

        $this->driveOneMonsterTurn();

        // hex_12_2 → hex_12_3 → hex_12_4 (both road hexes along the same line)
        $this->assertEquals("hex_12_4", $this->tokenLocation($monster));
    }

    public function testMonsterOnSpawnArrowFollowsArrow(): void {
        // hex_9_1 is in DarkForest (arrow=5/SE). Goblin move=2 → walks 2 hexes
        // along the SE axis: hex_9_1 → hex_9_2 → hex_9_3.
        $monster = "monster_goblin_20";
        $this->game->getMonster($monster)->moveTo("hex_9_1", "");

        $this->driveOneMonsterTurn();

        $this->assertEquals("hex_9_3", $this->tokenLocation($monster));
    }

    public function testMonsterAdjacentToGrimheimEntersGrimheim(): void {
        // Place a monster adjacent to Grimheim. Even when the hero is far away
        // and the monster is on a non-road hex, the Grimheim-adjacency rule
        // overrides dir so the monster makes the final step in.
        // hex_7_9 has hero — pick a different adjacency. Use hex_8_8 (adjacent to Grimheim hex_9_8).
        $this->game->tokens->moveToken($this->heroId, "hex_5_9"); // park hero far away
        $monster = "monster_goblin_20";
        $this->game->getMonster($monster)->moveTo("hex_8_8", "");

        $this->driveOneMonsterTurn();

        // Monster reached Grimheim → removed from map (location = supply_monster).
        $this->assertEquals("supply_monster", $this->tokenLocation($monster));
    }

    private function driveOneMonsterTurn(): void {
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skipOp("turn");
        $this->skipIfOp("upgrade");
        $this->skipIfOp("drawEvent");
    }
}
