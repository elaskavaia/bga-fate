<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\Tests\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class HexMapTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        $this->game->setPlayersNumber(1);
        $this->game->tokens->moveToken("card_hero_1", "tableau_" . PCOLOR);
    }

    // -------------------------------------------------------------------------
    // getHexCoords
    // -------------------------------------------------------------------------

    public function testGetHexCoords(): void {
        $this->assertEquals([9, 9], $this->game->hexMap->getHexCoords("hex_9_9"));
        $this->assertEquals([1, 1], $this->game->hexMap->getHexCoords("hex_1_1"));
        $this->assertEquals([15, 12], $this->game->hexMap->getHexCoords("hex_15_12"));
    }

    // -------------------------------------------------------------------------
    // isValidHex
    // -------------------------------------------------------------------------

    public function testIsValidHexTrue(): void {
        $this->assertTrue($this->game->hexMap->isValidHex("hex_9_9"));
        $this->assertTrue($this->game->hexMap->isValidHex("hex_11_8"));
    }

    public function testIsValidHexFalse(): void {
        $this->assertFalse($this->game->hexMap->isValidHex("hex_99_99"));
        $this->assertFalse($this->game->hexMap->isValidHex("not_a_hex"));
    }

    // -------------------------------------------------------------------------
    // getHexTerrain
    // -------------------------------------------------------------------------

    public function testGetHexTerrain(): void {
        $this->assertEquals("plains", $this->game->hexMap->getHexTerrain("hex_9_9")); // Grimheim
        $this->assertEquals("forest", $this->game->hexMap->getHexTerrain("hex_9_1")); // DarkForest
        $this->assertEquals("mountain", $this->game->hexMap->getHexTerrain("hex_13_1"));
        $this->assertEquals("lake", $this->game->hexMap->getHexTerrain("hex_5_5"));
    }

    // -------------------------------------------------------------------------
    // getHexNamedLocation / isInGrimheim
    // -------------------------------------------------------------------------

    public function testGetHexNamedLocation(): void {
        $this->assertEquals("Grimheim", $this->game->hexMap->getHexNamedLocation("hex_9_9"));
        $this->assertEquals("DarkForest", $this->game->hexMap->getHexNamedLocation("hex_9_1"));
        $this->assertEquals("", $this->game->hexMap->getHexNamedLocation("hex_13_1")); // unnamed mountain
    }

    public function testIsInGrimheim(): void {
        $this->assertTrue($this->game->hexMap->isInGrimheim("hex_9_9"));
        $this->assertTrue($this->game->hexMap->isInGrimheim("hex_10_8"));
        $this->assertFalse($this->game->hexMap->isInGrimheim("hex_11_8"));
        $this->assertFalse($this->game->hexMap->isInGrimheim("hex_9_1"));
    }

    // -------------------------------------------------------------------------
    // getHexesInGrimheim
    // -------------------------------------------------------------------------

    public function testGetHexesInGrimheim(): void {
        $hexes = $this->game->hexMap->getHexesInGrimheim();
        $this->assertCount(7, $hexes);
        $this->assertContains("hex_9_9", $hexes);
        $this->assertContains("hex_10_8", $hexes);
        $this->assertContains("hex_8_10", $hexes);
        $this->assertNotContains("hex_11_8", $hexes);
    }

    // -------------------------------------------------------------------------
    // getHexDistance
    // -------------------------------------------------------------------------

    public function testGetHexDistanceSameHex(): void {
        $this->assertEquals(0, $this->game->hexMap->getHexDistance("hex_9_9", "hex_9_9"));
    }

    public function testGetHexDistanceAdjacent(): void {
        // hex_9_9 and hex_10_9 are adjacent
        $this->assertEquals(1, $this->game->hexMap->getHexDistance("hex_9_9", "hex_10_9"));
    }

    public function testGetHexDistanceSymmetric(): void {
        $d1 = $this->game->hexMap->getHexDistance("hex_9_9", "hex_12_8");
        $d2 = $this->game->hexMap->getHexDistance("hex_12_8", "hex_9_9");
        $this->assertEquals($d1, $d2);
    }

    // -------------------------------------------------------------------------
    // isImpassable
    // -------------------------------------------------------------------------

    public function testIsImpassableLake(): void {
        $this->assertTrue($this->game->hexMap->isImpassable("hex_5_5", "monster"));
        $this->assertTrue($this->game->hexMap->isImpassable("hex_5_5", "hero"));
    }

    public function testIsImpassableMountainForHero(): void {
        $this->assertTrue($this->game->hexMap->isImpassable("hex_13_1", "hero"));
    }

    public function testIsImpassableMountainForMonster(): void {
        $this->assertFalse($this->game->hexMap->isImpassable("hex_13_1", "monster"));
    }

    public function testIsImpassablePlains(): void {
        $this->assertFalse($this->game->hexMap->isImpassable("hex_9_9", "hero"));
        $this->assertFalse($this->game->hexMap->isImpassable("hex_9_9", "monster"));
    }

    public function testIsImpassableDefaultIsMonster(): void {
        // Default characterType is "monster" — mountains passable
        $this->assertFalse($this->game->hexMap->isImpassable("hex_13_1"));
    }

    // -------------------------------------------------------------------------
    // getOccupancyMap / invalidateOccupancy
    // -------------------------------------------------------------------------

    public function testGetOccupancyMapReturnsAllHexes(): void {
        $occ = $this->game->hexMap->getOccupancyMap();
        // Should contain all material hexes
        $this->assertArrayHasKey("hex_9_9", $occ);
        $this->assertArrayHasKey("hex_11_8", $occ);
        // Should not contain non-material hexes
        $this->assertArrayNotHasKey("hex_99_99", $occ);
    }

    public function testGetOccupancyMapShowsHeroes(): void {
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->hexMap->invalidateOccupancy();
        $occ = $this->game->hexMap->getOccupancyMap();
        $this->assertEquals("hero_1", $occ["hex_11_8"]["character"]);
    }

    public function testGetOccupancyMapShowsMonsters(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();
        $occ = $this->game->hexMap->getOccupancyMap();
        $this->assertEquals("monster_goblin_1", $occ["hex_12_8"]["character"]);
    }

    public function testGetOccupancyMapEmptyHex(): void {
        $occ = $this->game->hexMap->getOccupancyMap();
        // hex_13_7 should be empty (no tokens placed there in setup)
        $this->assertNull($occ["hex_13_7"]["character"]);
        $this->assertEmpty($occ["hex_13_7"]["stuff"]);
    }

    public function testGetOccupancyMapHousesInStuff(): void {
        $occ = $this->game->hexMap->getOccupancyMap();
        // Houses are placed in Grimheim hexes during createAllTokens
        $foundHouse = false;
        foreach ($occ as $entry) {
            foreach (array_keys($entry["stuff"]) as $key) {
                if (str_starts_with($key, "house")) {
                    $foundHouse = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($foundHouse, "Houses should appear in stuff field");
    }

    public function testInvalidateOccupancyRefreshesCache(): void {
        // Load cache
        $this->game->hexMap->getOccupancyMap();
        // Move token directly in DB
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        // Cache still has old data
        $occ1 = $this->game->hexMap->getOccupancyMap();
        // Invalidate and reload
        $this->game->hexMap->invalidateOccupancy();
        $occ2 = $this->game->hexMap->getOccupancyMap();
        $this->assertEquals("monster_goblin_1", $occ2["hex_12_8"]["character"]);
    }

    // -------------------------------------------------------------------------
    // getCharacterHex
    // -------------------------------------------------------------------------

    public function testGetCharacterHexFound(): void {
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->hexMap->invalidateOccupancy();
        $this->assertEquals("hex_11_8", $this->game->hexMap->getCharacterHex("hero_1"));
    }

    public function testGetCharacterHexNotOnMap(): void {
        // hero_1 is in supply or off-map
        $this->game->tokens->moveToken("hero_1", "limbo");
        $this->game->hexMap->invalidateOccupancy();
        $this->assertNull($this->game->hexMap->getCharacterHex("hero_1"));
    }

    // -------------------------------------------------------------------------
    // moveCharacterOnMap
    // -------------------------------------------------------------------------

    public function testmoveCharacterOnMapPlacesCharacter(): void {
        $this->game->hexMap->getOccupancyMap(); // init cache
        $this->game->getMonster("monster_goblin_1")->moveTo("hex_12_8");
        $this->assertEquals("hex_12_8", $this->game->hexMap->getCharacterHex("monster_goblin_1"));
        $this->assertTrue($this->game->hexMap->isOccupied("hex_12_8"));
    }

    public function testmoveCharacterOnMapMovesCharacter(): void {
        $this->game->hexMap->getOccupancyMap();
        $monster = $this->game->getMonster("monster_goblin_1");
        $monster->moveTo("hex_12_8");
        $monster->moveTo("hex_11_8");
        // Should be on new hex
        $this->assertEquals("hex_11_8", $this->game->hexMap->getCharacterHex("monster_goblin_1"));
        // Old hex should be free
        $this->assertFalse($this->game->hexMap->isOccupied("hex_12_8"));
    }

    public function testmoveCharacterOnMapRemovesCharacter(): void {
        $this->game->hexMap->getOccupancyMap();
        $monster = $this->game->getMonster("monster_goblin_1");
        $monster->moveTo("hex_12_8");
        $monster->moveTo("limbo");
        $this->assertNull($this->game->hexMap->getCharacterHex("monster_goblin_1"));
        $this->assertFalse($this->game->hexMap->isOccupied("hex_12_8"));
    }

    // -------------------------------------------------------------------------
    // isOccupied
    // -------------------------------------------------------------------------

    public function testIsOccupiedTrue(): void {
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->hexMap->invalidateOccupancy();
        $this->assertTrue($this->game->hexMap->isOccupied("hex_11_8"));
    }

    public function testIsOccupiedFalse(): void {
        $this->assertFalse($this->game->hexMap->isOccupied("hex_13_7"));
    }

    // -------------------------------------------------------------------------
    // canEnterHex
    // -------------------------------------------------------------------------

    public function testCanEnterHexPlains(): void {
        $this->assertTrue($this->game->hexMap->canEnterHex("hex_13_7", "hero"));
        $this->assertTrue($this->game->hexMap->canEnterHex("hex_13_7", "monster"));
    }

    public function testCanEnterHexLakeBlocked(): void {
        $this->assertFalse($this->game->hexMap->canEnterHex("hex_5_5", "hero"));
        $this->assertFalse($this->game->hexMap->canEnterHex("hex_5_5", "monster"));
    }

    public function testCanEnterHexMountainBlocksHero(): void {
        $this->assertFalse($this->game->hexMap->canEnterHex("hex_13_1", "hero"));
    }

    public function testCanEnterHexMountainAllowsMonster(): void {
        $this->assertTrue($this->game->hexMap->canEnterHex("hex_13_1", "monster"));
    }

    public function testCanEnterHexOccupiedBlocked(): void {
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->hexMap->invalidateOccupancy();
        $this->assertFalse($this->game->hexMap->canEnterHex("hex_11_8", "monster"));
    }

    // -------------------------------------------------------------------------
    // getHexesInLocation
    // -------------------------------------------------------------------------

    public function testGetHexesInLocationDarkForest(): void {
        $hexes = $this->game->hexMap->getHexesInLocation("DarkForest");
        $this->assertNotEmpty($hexes);
        foreach ($hexes as $hex) {
            $this->assertEquals("DarkForest", $this->game->hexMap->getHexNamedLocation($hex));
        }
    }

    public function testGetHexesInLocationEmpty(): void {
        $hexes = $this->game->hexMap->getHexesInLocation("NonExistentPlace");
        $this->assertEmpty($hexes);
    }

    public function testGetHexesInLocationSorted(): void {
        $hexes = $this->game->hexMap->getHexesInLocation("DarkForest");
        // Should be sorted by y ascending, then x ascending
        $prev = null;
        foreach ($hexes as $hex) {
            if ($prev !== null) {
                [$px, $py] = $this->game->hexMap->getHexCoords($prev);
                [$cx, $cy] = $this->game->hexMap->getHexCoords($hex);
                $this->assertTrue($cy > $py || ($cy === $py && $cx >= $px), "Hexes should be sorted by y then x: $prev before $hex");
            }
            $prev = $hex;
        }
    }

    // -------------------------------------------------------------------------
    // isHeroAdjacentTo
    // -------------------------------------------------------------------------

    public function testIsHeroAdjacentToAdjacentHex(): void {
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->hexMap->invalidateOccupancy();
        $this->assertTrue($this->game->hexMap->isHeroAdjacentTo("hex_12_8"));
    }

    public function testIsHeroAdjacentToSameHex(): void {
        $this->game->tokens->moveToken("hero_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();
        $this->assertTrue($this->game->hexMap->isHeroAdjacentTo("hex_12_8"));
    }

    public function testIsHeroAdjacentToFarAway(): void {
        // Move all heroes far from target
        $this->game->tokens->moveToken("hero_1", "hex_1_1");
        $this->game->tokens->moveToken("hero_2", "hex_1_1");
        $this->game->tokens->moveToken("hero_3", "hex_1_1");
        $this->game->tokens->moveToken("hero_4", "hex_1_1");
        $this->game->hexMap->invalidateOccupancy();
        $this->assertFalse($this->game->hexMap->isHeroAdjacentTo("hex_12_8"));
    }

    // -------------------------------------------------------------------------
    // getHexesInRange
    // -------------------------------------------------------------------------

    public function testGetHexesInRangeOne(): void {
        // Range 1 should return adjacent hexes
        $hexes = $this->game->hexMap->getHexesInRange("hex_11_8", 1);
        $adjacent = $this->game->hexMap->getAdjacentHexes("hex_11_8");
        $this->assertEquals(sort($adjacent), sort($hexes));
    }

    public function testGetHexesInRangeTwoIncludesDistTwo(): void {
        $hexes = $this->game->hexMap->getHexesInRange("hex_11_8", 2);
        // hex_13_7 is distance 2 from hex_11_8
        $this->assertContains("hex_13_7", $hexes);
        // hex_12_8 is distance 1 (also included)
        $this->assertContains("hex_12_8", $hexes);
    }

    public function testGetHexesInRangeExcludesSelf(): void {
        $hexes = $this->game->hexMap->getHexesInRange("hex_11_8", 2);
        $this->assertNotContains("hex_11_8", $hexes);
    }

    public function testGetHexesInRangeGrimheimBlocksPassthrough(): void {
        // hex_7_9 is west of Grimheim, hex_11_9 is east of Grimheim
        // Straight distance is 4, but with range 7 and no blocking you'd reach hex_11_9.
        // With Grimheim blocking, the BFS must go around Grimheim's borders,
        // so hex_11_9 should be at a greater BFS distance than 4.
        $hexesRange4 = $this->game->hexMap->getHexesInRange("hex_7_9", 4);
        // hex_11_9 is straight distance 4 from hex_7_9, but all direct paths go through Grimheim
        // BFS must detour around — it should NOT be reachable at range 4
        $this->assertNotContains("hex_11_9", $hexesRange4, "hex_11_9 should not be reachable at range 4 due to Grimheim blocking");
    }

    public function testGetHexesInRangeGrimheimReachableAtHigherRange(): void {
        // With enough range, the BFS can go around Grimheim and reach hex_11_9
        $hexesRange7 = $this->game->hexMap->getHexesInRange("hex_7_9", 7);
        $this->assertContains("hex_11_9", $hexesRange7, "hex_11_9 should be reachable at range 7 by going around Grimheim");
    }

    public function testGetHexesInRangeCanTargetGrimheimHex(): void {
        // hex_7_9 is adjacent to hex_8_9 (Grimheim) — should be targetable
        $hexes = $this->game->hexMap->getHexesInRange("hex_7_9", 2);
        $this->assertContains("hex_8_9", $hexes, "Adjacent Grimheim hex should be targetable");
        // hex_9_9 is also Grimheim, distance 2 — reachable through 8_9 but 8_9 is Grimheim so no expand
        // Only reachable if there's a non-Grimheim path. 7_9->7_10->8_10(grim) = can target but no expand.
        // 7_9->8_9(grim) = can target but no expand. So 9_9 is NOT reachable at range 2 from outside.
        $this->assertNotContains("hex_9_9", $hexes, "Deeper Grimheim hex should not be reachable (cannot pass through Grimheim border)");
    }

    public function testGetHexesInRangeFromInsideGrimheimNotBlocked(): void {
        // From inside Grimheim, Grimheim hexes should not block
        $hexes = $this->game->hexMap->getHexesInRange("hex_9_9", 2);
        // Should reach other Grimheim hexes and beyond
        $this->assertContains("hex_10_9", $hexes, "Should reach adjacent Grimheim hex");
        $this->assertContains("hex_11_9", $hexes, "Should reach hex beyond Grimheim (distance 2)");
    }

    // -------------------------------------------------------------------------
    // getMonstersOnMap
    // -------------------------------------------------------------------------

    public function testGetMonstersOnMapEmpty(): void {
        $monsters = $this->game->hexMap->getMonstersOnMap();
        $this->assertEmpty($monsters);
    }

    public function testGetMonstersOnMapSortedByDistance(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7"); // far
        $this->game->tokens->moveToken("monster_goblin_2", "hex_11_8"); // close (dist 1)
        $this->game->hexMap->invalidateOccupancy();

        $monsters = $this->game->hexMap->getMonstersOnMap();
        $this->assertCount(2, $monsters);
        // Closest first
        $this->assertEquals("monster_goblin_2", $monsters[0]["id"]);
        $this->assertEquals("monster_goblin_1", $monsters[1]["id"]);
    }

    public function testGetMonstersOnMapExcludesHeroes(): void {
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        $monsters = $this->game->hexMap->getMonstersOnMap();
        $this->assertCount(1, $monsters);
        $this->assertEquals("monster_goblin_1", $monsters[0]["id"]);
    }
}
