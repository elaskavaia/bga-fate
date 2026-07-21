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

    public function testLegendSwapsWithBlockingRegularMonster(): void {
        // Hero next to the blocker prevents it from moving on its own — so the legend behind
        // it must trigger the swap to advance.
        $this->game->tokens->moveToken("hero_1", "hex_11_7"); // adj to hex_11_8 only
        $this->game->tokens->moveToken("monster_goblin_1", "hex_11_8"); // stuck blocker
        $this->game->tokens->moveToken("monster_legend_3_1", "hex_12_8"); // legend behind

        $this->call_resolve();

        $this->assertEquals(
            "hex_11_8",
            $this->game->tokens->getTokenLocation("monster_legend_3_1"),
            "Legend should advance into the blocker's hex"
        );
        $this->assertEquals(
            "hex_12_8",
            $this->game->tokens->getTokenLocation("monster_goblin_1"),
            "Blocking monster should be pushed back into the legend's vacated hex"
        );
    }

    public function testLegendDoesNotSwapWithAnotherLegend(): void {
        $this->game->tokens->moveToken("hero_1", "hex_11_7"); // adj to hex_11_8 only
        $this->game->tokens->moveToken("monster_legend_2_1", "hex_11_8"); // blocker legend
        $this->game->tokens->moveToken("monster_legend_3_1", "hex_12_8"); // legend trying to advance

        $this->call_resolve();

        $this->assertEquals(
            "hex_12_8",
            $this->game->tokens->getTokenLocation("monster_legend_3_1"),
            "Legend must not swap with another Legend — should stay put"
        );
        $this->assertEquals(
            "hex_11_8",
            $this->game->tokens->getTokenLocation("monster_legend_2_1"),
            "Front Legend should not be pushed by another Legend"
        );
    }

    public function testLegendDoesNotSwapWithBlockingHero(): void {
        // Park hero directly in the legend's path. Legends only swap with non-Legend
        // MONSTERS — a hero blocker stops them like any monster would.
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->tokens->moveToken("monster_legend_3_1", "hex_12_8");

        $this->call_resolve();

        $this->assertEquals(
            "hex_12_8",
            $this->game->tokens->getTokenLocation("monster_legend_3_1"),
            "Legend must not swap with a hero — should stay put"
        );
        $this->assertEquals(
            "hex_11_8",
            $this->game->tokens->getTokenLocation("hero_1"),
            "Hero should not be pushed by a Legend"
        );
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
    // Charge rule C: extra step to get into attack range (RULES.md §274)
    // Path: hex_15_7 -> hex_14_7 -> hex_13_7 -> hex_12_8 -> hex_11_8 (Grimheim-ward)
    // Hero at hex_11_8: distances hex_14_7=3, hex_13_7=2, hex_12_8=1.
    // -------------------------------------------------------------------------

    public function testRange1MonsterChargesToBecomeAdjacent(): void {
        // Control: a range-1 monster ends normal move 2 away and charges to adjacency.
        $this->game->tokens->moveToken("monster_imp_1", "hex_14_7");
        $this->game->tokens->moveToken("hero_1", "hex_11_8");

        $this->call_resolve();

        $loc = $this->game->tokens->getTokenLocation("monster_imp_1");
        $this->assertEquals("hex_12_8", $loc, "Range-1 monster should charge one extra step to reach the hero");
    }

    public function testFireHordeChargesIntoRange2(): void {
        // Fire Horde (range 2) ends normal move 3 away (out of range); charging to 2 lets it attack.
        $this->game->tokens->moveToken("monster_sprite_1", "hex_15_7");
        $this->game->tokens->moveToken("hero_1", "hex_11_8");

        $this->call_resolve();

        $loc = $this->game->tokens->getTokenLocation("monster_sprite_1");
        $this->assertEquals("hex_13_7", $loc, "Fire Horde should charge to get within its range-2 attack (distance 2)");
    }

    public function testFireHordeDoesNotChargeWhenAlreadyInRange2(): void {
        // Fire Horde (range 2) ends normal move 2 away, already able to attack -> must NOT charge closer.
        $this->game->tokens->moveToken("monster_sprite_1", "hex_14_7");
        $this->game->tokens->moveToken("hero_1", "hex_11_8");

        $this->call_resolve();

        $loc = $this->game->tokens->getTokenLocation("monster_sprite_1");
        $this->assertEquals("hex_13_7", $loc, "Fire Horde already within range 2 should not take an unnecessary charge step");
    }

    // -------------------------------------------------------------------------
    // Stun (stunmarker — Suppressive Fire)
    // -------------------------------------------------------------------------

    private function placeStunMarker(string $monsterId): void {
        $this->game->tokens->createTokenIfNot("stunmarker_c", $monsterId);
        $this->game->tokens->moveToken("stunmarker_c", $monsterId);
    }

    public function testStunnedMonsterDoesNotMove(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        // Place a stun marker on the monster
        $this->placeStunMarker("monster_goblin_1");

        $this->call_resolve();

        $loc = $this->game->tokens->getTokenLocation("monster_goblin_1");
        $this->assertEquals("hex_12_8", $loc, "Stunned monster should not move");
    }

    public function testStunMarkerStaysButBecomesSpentAfterMonsterTurn(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->placeStunMarker("monster_goblin_1");

        $this->call_resolve();

        // Marker stays on the monster (so the same supfire card can't re-target it next play),
        // but its state advances from 0 (active) to 1 (spent — no longer blocks movement).
        $active = $this->game->tokens->getTokensOfTypeInLocation("stunmarker", "monster_goblin_1", 0);
        $spent = $this->game->tokens->getTokensOfTypeInLocation("stunmarker", "monster_goblin_1", 1);
        $this->assertCount(0, $active, "Active stun marker should have been consumed");
        $this->assertCount(1, $spent, "Marker should remain on monster as spent (state=1) for re-target cooldown");
    }

    public function testSpentStunMarkerDoesNotBlockMovement(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        // Place a spent (state=1) stun marker — should not block movement
        $this->game->tokens->createTokenIfNot("stunmarker_c", "monster_goblin_1");
        $this->game->tokens->dbSetTokenLocation("stunmarker_c", "monster_goblin_1", 1, "");

        $this->call_resolve();

        $loc = $this->game->tokens->getTokenLocation("monster_goblin_1");
        $this->assertNotEquals("hex_12_8", $loc, "Monster with only spent stun markers should still move");
    }

    public function testStunnedMonsterDoesNotMoveOnChargeTurn(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->placeStunMarker("monster_goblin_1");

        $op = $this->createOp(null, ["charge" => true]);
        $op->resolve();

        $loc = $this->game->tokens->getTokenLocation("monster_goblin_1");
        $this->assertEquals("hex_12_8", $loc, "Stunned monster should not move even on charge turn");
    }

    public function testStunnedMonsterBlocksMonsterBehind(): void {
        // hex_13_7 has dir=7/SW → hex_12_8. With goblin_1 stunned on hex_12_8,
        // goblin_2's only path is blocked and it stays put (road-jam rule).
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_goblin_2", "hex_13_7");
        $this->placeStunMarker("monster_goblin_1");

        $this->call_resolve();

        $this->assertEquals("hex_12_8", $this->game->tokens->getTokenLocation("monster_goblin_1"));
        $this->assertEquals("hex_13_7", $this->game->tokens->getTokenLocation("monster_goblin_2"));
    }
}
