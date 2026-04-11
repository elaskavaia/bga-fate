<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_monsterMoveAll;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_monsterMoveAllTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        // Assign hero 1 to player
        $this->game->tokens->moveToken("card_hero_1", $this->getPlayersTableau());
        // Move all heroes far away so they don't interfere with monster tests
        $this->game->tokens->moveToken("hero_1", "hex_1_1");
        $this->game->tokens->moveToken("hero_2", "hex_1_2");
        $this->game->tokens->moveToken("hero_3", "hex_2_1");
        $this->game->tokens->moveToken("hero_4", "hex_2_2");
    }

    // -------------------------------------------------------------------------
    // Basic movement
    // -------------------------------------------------------------------------

    public function testMonsterMovesTowardGrimheim(): void {
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $distBefore = $this->game->hexMap->getDistanceMapToGrimheim()["hex_12_8"];

        $this->call_resolve();
        $newLoc = $this->game->tokens->getTokenLocation("monster_brute_1");
        $distAfter = $this->game->hexMap->getDistanceMapToGrimheim()[$newLoc] ?? PHP_INT_MAX;
        $this->assertLessThan($distBefore, $distAfter, "Monster should have moved closer to Grimheim");
    }

    public function testMonsterDoesNotMoveWhenAdjacentToHero(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("hero_1", "hex_11_8"); // adjacent

        $this->call_resolve();
        $loc = $this->game->tokens->getTokenLocation("monster_goblin_1");
        $this->assertEquals("hex_12_8", $loc, "Monster should not move when adjacent to hero");
    }

    public function testMonsterDoesNotMoveIntoOccupiedHex(): void {
        $this->game->tokens->moveToken("monster_brute_1", "hex_11_8"); // dist 1
        $this->game->tokens->moveToken("monster_brute_2", "hex_12_8"); // dist 2, wants to move to hex_11_8

        $this->call_resolve();

        // brute_1 should have entered Grimheim (removed)
        $loc1 = $this->game->tokens->getTokenLocation("monster_brute_1");
        $this->assertEquals("supply_monster", $loc1, "Closest monster should enter Grimheim");

        // brute_2 should have moved to hex_11_8 (now vacated)
        $loc2 = $this->game->tokens->getTokenLocation("monster_brute_2");
        $this->assertEquals("hex_11_8", $loc2, "Second monster should move into vacated hex");
    }

    public function testMonsterMovementOrderClosestFirst(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7"); // far
        $this->game->tokens->moveToken("monster_goblin_2", "hex_11_8"); // close (dist 1)

        $distMap = $this->game->hexMap->getDistanceMapToGrimheim();
        $g1Before = $distMap["hex_13_7"];

        $this->call_resolve();

        // goblin_2 (closer) should have entered Grimheim
        $loc2 = $this->game->tokens->getTokenLocation("monster_goblin_2");
        $this->assertEquals("supply_monster", $loc2, "Closer monster should enter Grimheim first");

        // goblin_1 (farther) should have moved closer
        $newLoc1 = $this->game->tokens->getTokenLocation("monster_goblin_1");
        $this->assertNotEquals("hex_13_7", $newLoc1, "Far monster should have moved");
        $this->assertLessThan($g1Before, $distMap[$newLoc1] ?? PHP_INT_MAX, "Far monster should be closer to Grimheim");
    }

    // -------------------------------------------------------------------------
    // Grimheim entry
    // -------------------------------------------------------------------------

    public function testMonsterEnteringGrimheimDestroysHouse(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_11_8"); // dist 1 from Grimheim

        $housesBefore = $this->game->tokens->getTokensOfTypeInLocation("house", "hex%");
        $countBefore = count($housesBefore);
        $this->call_resolve();

        $loc = $this->game->tokens->getTokenLocation("monster_goblin_1");
        $this->assertEquals("supply_monster", $loc);

        $housesAfter = $this->game->tokens->getTokensOfTypeInLocation("house", "hex%");
        $this->assertEquals($countBefore - 1, count($housesAfter), "One house should have been destroyed");
    }

    public function testFreyjasWellDestroyedLast(): void {
        for ($i = 1; $i <= 9; $i++) {
            $this->game->tokens->moveToken("house_$i", "limbo");
        }
        $houses = $this->game->tokens->getTokensOfTypeInLocation("house", "hex%");
        $this->assertCount(1, $houses);
        $this->assertArrayHasKey("house_0", $houses);

        $this->game->tokens->moveToken("monster_goblin_1", "hex_11_8");

        $this->call_resolve();

        $wellLoc = $this->game->tokens->getTokenLocation("house_0");
        $this->assertEquals("limbo", $wellLoc, "Freyja's Well should be destroyed when it's the last house");
    }

    public function testLegendEnteringGrimheimDestroys3Houses(): void {
        $this->game->tokens->moveToken("monster_legend_3_1", "hex_11_8");

        $housesBefore = $this->game->tokens->getTokensOfTypeInLocation("house", "hex%");
        $countBefore = count($housesBefore);
        $this->assertGreaterThanOrEqual(3, $countBefore, "Need at least 3 houses for this test");

        $this->call_resolve();

        $loc = $this->game->tokens->getTokenLocation("monster_legend_3_1");
        $this->assertEquals("supply_monster", $loc);

        $housesAfter = $this->game->tokens->getTokensOfTypeInLocation("house", "hex%");
        $this->assertEquals($countBefore - 3, count($housesAfter), "Legend should destroy 3 houses");
    }

    public function testGameEndsImmediatelyWhenLastHouseDestroyed(): void {
        for ($i = 1; $i <= 9; $i++) {
            $this->game->tokens->moveToken("house_$i", "limbo");
        }

        $this->game->tokens->moveToken("monster_goblin_1", "hex_11_8"); // dist 1 — enters Grimheim
        $this->game->tokens->moveToken("monster_goblin_2", "hex_13_7"); // dist 3 — should stay put

        $this->call_resolve();

        $this->assertEquals("limbo", $this->game->tokens->getTokenLocation("house_0"), "Well should be destroyed");
        $this->assertTrue($this->game->isEndOfGame(), "Game should have ended");

        $this->assertEquals(
            "hex_13_7",
            $this->game->tokens->getTokenLocation("monster_goblin_2"),
            "Remaining monsters should not move after game ends"
        );
    }

    // -------------------------------------------------------------------------
    // Charge
    // -------------------------------------------------------------------------

    public function testChargeAddsOneStep(): void {
        $this->game->tokens->moveToken("monster_brute_1", "hex_13_7"); // dist 3
        $distMap = $this->game->hexMap->getDistanceMapToGrimheim();
        $distBefore = $distMap["hex_13_7"];

        $op = $this->createOp(null, ["charge" => true]);
        $op->resolve();

        $newLoc = $this->game->tokens->getTokenLocation("monster_brute_1");
        $distAfter = $distMap[$newLoc] ?? PHP_INT_MAX;
        $this->assertEquals($distBefore - 2, $distAfter, "Monster should move 2 steps on charge turn (1 base + 1 charge)");
    }

    public function testNoChargeOnNormalTurn(): void {
        $this->game->tokens->moveToken("monster_brute_1", "hex_13_7"); // dist 3
        $distMap = $this->game->hexMap->getDistanceMapToGrimheim();
        $distBefore = $distMap["hex_13_7"];

        $op = $this->createOp(null, ["charge" => false]);
        $op->resolve();

        $newLoc = $this->game->tokens->getTokenLocation("monster_brute_1");
        $distAfter = $distMap[$newLoc] ?? PHP_INT_MAX;
        $this->assertEquals($distBefore - 1, $distAfter, "Monster should move only 1 step on normal turn");
    }

    // -------------------------------------------------------------------------
    // Stun (green crystal — Suppressive Fire)
    // -------------------------------------------------------------------------

    public function testStunnedMonsterDoesNotMove(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        // Place a green crystal on the monster (stunned)
        $this->game->effect_moveCrystals("hero_1", "green", 1, "monster_goblin_1", ["message" => ""]);

        $this->call_resolve();

        $loc = $this->game->tokens->getTokenLocation("monster_goblin_1");
        $this->assertEquals("hex_12_8", $loc, "Stunned monster should not move");
    }

    public function testStunCrystalStaysAfterSkippingMovement(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->effect_moveCrystals("hero_1", "green", 1, "monster_goblin_1", ["message" => ""]);

        $this->call_resolve();
        $crystalsAfter = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "monster_goblin_1");
        $this->assertCount(1, $crystalsAfter, "Green crystal should stay on monster (removed by next c_supfire trigger)");
    }

    public function testStunnedMonsterDoesNotMoveOnChargeTurn(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->effect_moveCrystals("hero_1", "green", 1, "monster_goblin_1", ["message" => ""]);

        $op = $this->createOp(null, ["charge" => true]);
        $op->resolve();

        $loc = $this->game->tokens->getTokenLocation("monster_goblin_1");
        $this->assertEquals("hex_12_8", $loc, "Stunned monster should not move even on charge turn");
    }

    public function testOnlyStunnedMonsterIsSkipped(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8"); // stunned
        $this->game->tokens->moveToken("monster_goblin_2", "hex_13_7"); // not stunned
        $this->game->effect_moveCrystals("hero_1", "green", 1, "monster_goblin_1", ["message" => ""]);

        $distMap = $this->game->hexMap->getDistanceMapToGrimheim();
        $g2Before = $distMap["hex_13_7"];

        $this->call_resolve();

        // Stunned monster stays
        $this->assertEquals("hex_12_8", $this->game->tokens->getTokenLocation("monster_goblin_1"));

        // Non-stunned monster moves
        $newLoc2 = $this->game->tokens->getTokenLocation("monster_goblin_2");
        $this->assertLessThan($g2Before, $distMap[$newLoc2] ?? PHP_INT_MAX, "Non-stunned monster should move");
    }
}
