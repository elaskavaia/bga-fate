<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\Operations\Op_turnMonster;
use Bga\Games\Fate\Tests\GameUT;
use PHPUnit\Framework\TestCase;

final class MonsterMovementTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createTokens();
        $this->game->setPlayersNumber(1);
        // Assign hero 1 to player
        $this->game->tokens->db->moveToken("card_hero_1", "tableau_" . PCOLOR);
        // Move all heroes far away so they don't interfere with monster tests
        $this->game->tokens->db->moveToken("hero_1", "hex_1_1");
        $this->game->tokens->db->moveToken("hero_2", "hex_1_2");
        $this->game->tokens->db->moveToken("hero_3", "hex_2_1");
        $this->game->tokens->db->moveToken("hero_4", "hex_2_2");
    }

    private function createTurnMonsterOp(): Op_turnMonster {
        /** @var Op_turnMonster */
        $op = $this->game->machine->instanciateOperation("turnMonster", ACOLOR);
        return $op;
    }

    // -------------------------------------------------------------------------
    // getDistanceMapToGrimheim
    // -------------------------------------------------------------------------

    public function testDistanceMapGrimheimIsZero(): void {
        $distMap = $this->game->hexMap->getDistanceMapToGrimheim();
        // All Grimheim hexes should be at distance 0
        foreach ($this->game->hexMap->getHexesInGrimheim() as $gHex) {
            $this->assertEquals(0, $distMap[$gHex], "Grimheim hex $gHex should be at distance 0");
        }
    }

    public function testDistanceMapAdjacentToGrimheim(): void {
        $distMap = $this->game->hexMap->getDistanceMapToGrimheim();
        // hex_11_8 is adjacent to hex_10_8 (Grimheim)
        $this->assertEquals(1, $distMap["hex_11_8"]);
        // hex_7_9 is adjacent to hex_8_9 (Grimheim)
        $this->assertEquals(1, $distMap["hex_7_9"]);
    }

    public function testDistanceMapFarHex(): void {
        $distMap = $this->game->hexMap->getDistanceMapToGrimheim();
        // Distance should increase as we move away from Grimheim
        $this->assertGreaterThan(1, $distMap["hex_12_8"] ?? PHP_INT_MAX);
        // Far corner hexes should have high distance
        $this->assertGreaterThan(5, $distMap["hex_1_1"] ?? PHP_INT_MAX);
    }

    public function testDistanceMapLakesBlocked(): void {
        $distMap = $this->game->hexMap->getDistanceMapToGrimheim();
        // Lake hexes should not be in the distance map
        foreach (array_keys($this->game->material->getTokensWithPrefix("hex")) as $hexId) {
            if ($this->game->hexMap->getHexTerrain($hexId) === "lake") {
                $this->assertArrayNotHasKey($hexId, $distMap, "Lake hex $hexId should not be in distance map");
            }
        }
    }

    public function testDistanceMapMountainsPassable(): void {
        $distMap = $this->game->hexMap->getDistanceMapToGrimheim();
        // Mountains should be passable for the Grimheim BFS (monster pathfinding)
        $foundMountain = false;
        foreach (array_keys($this->game->material->getTokensWithPrefix("hex")) as $hexId) {
            if ($this->game->hexMap->getHexTerrain($hexId) === "mountain") {
                if (isset($distMap[$hexId])) {
                    $foundMountain = true;
                    break;
                }
            }
        }
        $this->assertTrue($foundMountain, "At least one mountain hex should be in the distance map");
    }

    // -------------------------------------------------------------------------
    // getMonsterNextHex
    // -------------------------------------------------------------------------

    public function testMonsterNextHexMovesTowardGrimheim(): void {
        $distMap = $this->game->hexMap->getDistanceMapToGrimheim();
        // Place a conceptual monster 3 hexes from Grimheim
        // hex_12_8 is 2 away from Grimheim (adjacent to hex_11_8 which is 1 away)
        $nextHex = $this->game->hexMap->getMonsterNextHex("hex_12_8");
        $this->assertNotNull($nextHex);
        // The next hex should be closer to Grimheim
        $this->assertLessThan($distMap["hex_12_8"], $distMap[$nextHex]);
    }

    public function testMonsterNextHexFromAdjacentToGrimheim(): void {
        // hex_11_8 is distance 1 from Grimheim (adjacent to hex_10_8)
        $nextHex = $this->game->hexMap->getMonsterNextHex("hex_11_8");
        $this->assertNotNull($nextHex);
        // Next hex should be a Grimheim hex
        $this->assertTrue($this->game->hexMap->isInGrimheim($nextHex), "Monster adjacent to Grimheim should move into Grimheim");
    }

    public function testMonsterNextHexFromGrimheimIsNull(): void {
        // Monster already in Grimheim — no next hex
        $nextHex = $this->game->hexMap->getMonsterNextHex("hex_9_9");
        $this->assertNull($nextHex);
    }

    // -------------------------------------------------------------------------
    // getMonstersOnMap - sorted by distance
    // -------------------------------------------------------------------------

    public function testMonstersOnMapSortedByDistance(): void {
        // Place monsters at different distances from Grimheim
        $this->game->tokens->db->moveToken("monster_goblin_1", "hex_11_8"); // dist 1
        $this->game->tokens->db->moveToken("monster_goblin_2", "hex_13_7"); // dist 3
        $this->game->tokens->db->moveToken("monster_goblin_3", "hex_12_8"); // dist 2

        $monsters = $this->game->hexMap->getMonstersOnMap();
        $this->assertCount(3, $monsters);
        // Should be sorted: closest first
        $this->assertEquals("monster_goblin_1", $monsters[0]["id"]);
        $this->assertEquals("monster_goblin_3", $monsters[1]["id"]);
        $this->assertEquals("monster_goblin_2", $monsters[2]["id"]);
    }

    // -------------------------------------------------------------------------
    // isHeroAdjacentTo
    // -------------------------------------------------------------------------

    public function testIsHeroAdjacentToTrue(): void {
        $this->game->tokens->db->moveToken("hero_1", "hex_11_8");
        // hex_12_8 is adjacent to hex_11_8
        $this->assertTrue($this->game->hexMap->isHeroAdjacentTo("hex_12_8"));
    }

    public function testIsHeroAdjacentToFalse(): void {
        $this->game->tokens->db->moveToken("hero_1", "hex_1_1"); // far away
        $this->assertFalse($this->game->hexMap->isHeroAdjacentTo("hex_12_8"));
    }

    public function testIsHeroOnSameHex(): void {
        $this->game->tokens->db->moveToken("hero_1", "hex_12_8");
        // Hero on the same hex counts as adjacent
        $this->assertTrue($this->game->hexMap->isHeroAdjacentTo("hex_12_8"));
    }

    // -------------------------------------------------------------------------
    // Monster movement during turnMonster
    // -------------------------------------------------------------------------

    public function testMonsterMovesTowardGrimheim(): void {
        // Use brute (move=1) to test single-step movement
        $this->game->tokens->db->moveToken("monster_brute_1", "hex_12_8");
        $distBefore = $this->game->hexMap->getDistanceMapToGrimheim()["hex_12_8"];

        $this->game->tokens->db->setTokenState("rune_stone", 1); // shield step, no reinforcements
        $op = $this->createTurnMonsterOp();
        $op->resolve();

        $newLoc = $this->game->tokens->db->getTokenLocation("monster_brute_1");
        $distAfter = $this->game->hexMap->getDistanceMapToGrimheim()[$newLoc] ?? PHP_INT_MAX;
        $this->assertLessThan($distBefore, $distAfter, "Monster should have moved closer to Grimheim");
    }

    public function testMonsterDoesNotMoveWhenAdjacentToHero(): void {
        $this->game->tokens->db->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->db->moveToken("hero_1", "hex_11_8"); // adjacent

        $this->game->tokens->db->setTokenState("rune_stone", 1);
        $op = $this->createTurnMonsterOp();
        $op->resolve();

        $loc = $this->game->tokens->db->getTokenLocation("monster_goblin_1");
        $this->assertEquals("hex_12_8", $loc, "Monster should not move when adjacent to hero");
    }

    public function testMonsterDoesNotMoveIntoOccupiedHex(): void {
        // Two brutes (move=1), the closer one blocks the path
        $this->game->tokens->db->moveToken("monster_brute_1", "hex_11_8"); // dist 1
        $this->game->tokens->db->moveToken("monster_brute_2", "hex_12_8"); // dist 2, wants to move to hex_11_8

        $this->game->tokens->db->setTokenState("rune_stone", 1);
        $op = $this->createTurnMonsterOp();
        $op->resolve();

        // brute_1 should have entered Grimheim (removed)
        $loc1 = $this->game->tokens->db->getTokenLocation("monster_brute_1");
        $this->assertEquals("supply_monster", $loc1, "Closest monster should enter Grimheim");

        // brute_2 should have moved to hex_11_8 (now vacated)
        $loc2 = $this->game->tokens->db->getTokenLocation("monster_brute_2");
        $this->assertEquals("hex_11_8", $loc2, "Second monster should move into vacated hex");
    }

    public function testMonsterEnteringGrimheimDestroysHouse(): void {
        $this->game->tokens->db->moveToken("monster_goblin_1", "hex_11_8"); // dist 1 from Grimheim

        // Count houses before
        $housesBefore = $this->game->tokens->getTokensOfTypeInLocation("house", "hex%");
        $countBefore = count($housesBefore);

        $this->game->tokens->db->setTokenState("rune_stone", 1);
        $op = $this->createTurnMonsterOp();
        $op->resolve();

        // Monster should be removed
        $loc = $this->game->tokens->db->getTokenLocation("monster_goblin_1");
        $this->assertEquals("supply_monster", $loc);

        // One fewer house
        $housesAfter = $this->game->tokens->getTokensOfTypeInLocation("house", "hex%");
        $this->assertEquals($countBefore - 1, count($housesAfter), "One house should have been destroyed");
    }

    public function testFreyjasWellDestroyedLast(): void {
        // Move all houses except Freyja's Well to limbo
        for ($i = 1; $i <= 9; $i++) {
            $this->game->tokens->db->moveToken("house_$i", "limbo");
        }
        // Only house_0 (Freyja's Well) remains
        $houses = $this->game->tokens->getTokensOfTypeInLocation("house", "hex%");
        $this->assertCount(1, $houses);
        $this->assertArrayHasKey("house_0", $houses);

        // Monster enters Grimheim
        $this->game->tokens->db->moveToken("monster_goblin_1", "hex_11_8");

        $this->game->tokens->db->setTokenState("rune_stone", 1);
        $op = $this->createTurnMonsterOp();
        $op->resolve();

        // Freyja's Well should now be destroyed
        $wellLoc = $this->game->tokens->db->getTokenLocation("house_0");
        $this->assertEquals("limbo", $wellLoc, "Freyja's Well should be destroyed when it's the last house");
    }

    public function testLossConditionWhenWellDestroyed(): void {
        // Destroy all houses including Freyja's Well
        for ($i = 0; $i <= 9; $i++) {
            $this->game->tokens->db->moveToken("house_$i", "limbo");
        }

        // Game should end and heroes should lose
        $this->assertTrue($this->game->isEndOfGame(), "Game should end when Freyja's Well is destroyed");
        $this->assertFalse($this->game->isHeroesWin(), "Heroes should lose when Freyja's Well is gone");
    }

    public function testLegendEnteringGrimheimDestroys3Houses(): void {
        $this->game->tokens->db->moveToken("monster_legend_grendel", "hex_11_8"); // dist 1 from Grimheim

        // Count houses before
        $housesBefore = $this->game->tokens->getTokensOfTypeInLocation("house", "hex%");
        $countBefore = count($housesBefore);
        $this->assertGreaterThanOrEqual(3, $countBefore, "Need at least 3 houses for this test");

        $this->game->tokens->db->setTokenState("rune_stone", 1);
        $op = $this->createTurnMonsterOp();
        $op->resolve();

        // Legend should be removed
        $loc = $this->game->tokens->db->getTokenLocation("monster_legend_grendel");
        $this->assertEquals("supply_monster", $loc);

        // Three fewer houses
        $housesAfter = $this->game->tokens->getTokensOfTypeInLocation("house", "hex%");
        $this->assertEquals($countBefore - 3, count($housesAfter), "Legend should destroy 3 houses");
    }

    public function testChargeOnSkullTurnAddsOneStep(): void {
        // Brute (move=1) at distance 3 from Grimheim — on a normal turn moves 1, on skull turn moves 2
        $this->game->tokens->db->moveToken("monster_brute_1", "hex_13_7"); // dist 3
        $distMap = $this->game->hexMap->getDistanceMapToGrimheim();
        $distBefore = $distMap["hex_13_7"];

        // Set rune stone to step 8, so advanceTimeTrack moves it to step 9 (tm_red_skull)
        $this->game->tokens->db->setTokenState("rune_stone", 8);
        $op = $this->createTurnMonsterOp();
        $op->resolve();

        $newLoc = $this->game->tokens->db->getTokenLocation("monster_brute_1");
        $distAfter = $distMap[$newLoc] ?? PHP_INT_MAX;
        // Should have moved 2 steps (1 base + 1 charge) instead of 1
        $this->assertEquals($distBefore - 2, $distAfter, "Monster should move 2 steps on charge turn (1 base + 1 charge)");
    }

    public function testNoChargeOnShieldTurn(): void {
        // Brute at distance 3 — on a shield turn moves only 1 step
        $this->game->tokens->db->moveToken("monster_brute_1", "hex_13_7"); // dist 3
        $distMap = $this->game->hexMap->getDistanceMapToGrimheim();
        $distBefore = $distMap["hex_13_7"];

        // Step 1 → step 2 = tm_yellow_shield (no charge)
        $this->game->tokens->db->setTokenState("rune_stone", 1);
        $op = $this->createTurnMonsterOp();
        $op->resolve();

        $newLoc = $this->game->tokens->db->getTokenLocation("monster_brute_1");
        $distAfter = $distMap[$newLoc] ?? PHP_INT_MAX;
        $this->assertEquals($distBefore - 1, $distAfter, "Monster should move only 1 step on shield turn");
    }

    public function testMonsterMovementOrderClosestFirst(): void {
        // Place monsters at different distances
        $this->game->tokens->db->moveToken("monster_goblin_1", "hex_13_7"); // far
        $this->game->tokens->db->moveToken("monster_goblin_2", "hex_11_8"); // close (dist 1)

        $distMap = $this->game->hexMap->getDistanceMapToGrimheim();
        $g1Before = $distMap["hex_13_7"];
        $g2Before = $distMap["hex_11_8"];

        $this->game->tokens->db->setTokenState("rune_stone", 1);
        $op = $this->createTurnMonsterOp();
        $op->resolve();

        // goblin_2 (closer) should have entered Grimheim
        $loc2 = $this->game->tokens->db->getTokenLocation("monster_goblin_2");
        $this->assertEquals("supply_monster", $loc2, "Closer monster should enter Grimheim first");

        // goblin_1 (farther) should have moved closer
        $newLoc1 = $this->game->tokens->db->getTokenLocation("monster_goblin_1");
        $this->assertNotEquals("hex_13_7", $newLoc1, "Far monster should have moved");
        $this->assertLessThan($g1Before, $distMap[$newLoc1] ?? PHP_INT_MAX, "Far monster should be closer to Grimheim");
    }
}
