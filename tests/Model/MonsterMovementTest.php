<?php

declare(strict_types=1);

use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class MonsterMovementTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        $this->game->setPlayersNumber(1);
        // Assign hero 1 to player
        $this->game->tokens->moveToken("card_hero_1", "tableau_" . PCOLOR);
        // Move all heroes far away so they don't interfere with monster tests
        $this->game->tokens->moveToken("hero_1", "hex_1_1");
        $this->game->tokens->moveToken("hero_2", "hex_1_2");
        $this->game->tokens->moveToken("hero_3", "hex_2_1");
        $this->game->tokens->moveToken("hero_4", "hex_2_2");
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
        $this->game->tokens->moveToken("monster_goblin_1", "hex_11_8"); // dist 1
        $this->game->tokens->moveToken("monster_goblin_2", "hex_13_7"); // dist 3
        $this->game->tokens->moveToken("monster_goblin_3", "hex_12_8"); // dist 2

        $monsters = $this->game->hexMap->getMonstersOnMap();
        $this->assertCount(3, $monsters);
        // Should be sorted: closest first
        $this->assertEquals("monster_goblin_1", $monsters[0]["key"]);
        $this->assertEquals("monster_goblin_3", $monsters[1]["key"]);
        $this->assertEquals("monster_goblin_2", $monsters[2]["key"]);
    }

    // -------------------------------------------------------------------------
    // isHeroAdjacentTo
    // -------------------------------------------------------------------------

    public function testIsHeroAdjacentToTrue(): void {
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // hex_12_8 is adjacent to hex_11_8
        $this->assertTrue($this->game->hexMap->isHeroAdjacentTo("hex_12_8"));
    }

    public function testIsHeroAdjacentToFalse(): void {
        $this->game->tokens->moveToken("hero_1", "hex_1_1"); // far away
        $this->assertFalse($this->game->hexMap->isHeroAdjacentTo("hex_12_8"));
    }

    public function testIsHeroOnSameHex(): void {
        $this->game->tokens->moveToken("hero_1", "hex_12_8");
        // Hero on the same hex counts as adjacent
        $this->assertTrue($this->game->hexMap->isHeroAdjacentTo("hex_12_8"));
    }
}
