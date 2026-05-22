<?php

declare(strict_types=1);

/**
 * Tests for Op_monsterDieManeuver — Phase D5.
 * Iterates heroes in player order (A2 chain effects). Each adjacent-monster
 * group rotates one ring position around its anchor hero in the requested
 * direction. Blocked/off-map destinations cause no rotation.
 */
final class Op_monsterDieManeuverTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_12_7");
        $this->game->hexMap->invalidateOccupancy();
    }

    public function testClockwiseRotatesMonsterAroundHero(): void {
        // Place goblin at the West ring position of hero (q=11,r=7), i.e. axial offset
        // (-1,0). Screen-CW next position is NW = (0,-1) → hex_12_6.
        $this->game->tokens->moveToken("monster_goblin_1", "hex_11_7");
        $this->game->hexMap->invalidateOccupancy();

        $this->createOp("monsterDieManeuver(cw)");
        $this->call_resolve();

        $this->assertEquals("hex_12_6", $this->game->tokens->getTokenLocation("monster_goblin_1"));
    }

    public function testCounterClockwiseRotatesMonsterAroundHero(): void {
        // Same start (W). Screen-CCW step from W goes to SW = (-1,1) → hex_11_8.
        $this->game->tokens->moveToken("monster_goblin_1", "hex_11_7");
        $this->game->hexMap->invalidateOccupancy();

        $this->createOp("monsterDieManeuver(ccw)");
        $this->call_resolve();

        $this->assertEquals("hex_11_8", $this->game->tokens->getTokenLocation("monster_goblin_1"));
    }

    public function testMonsterNotAdjacentToHeroDoesNotMove(): void {
        // Two hexes away — no rotation anchor.
        $this->game->tokens->moveToken("monster_goblin_1", "hex_14_7");
        $this->game->hexMap->invalidateOccupancy();

        $this->createOp("monsterDieManeuver(cw)");
        $this->call_resolve();

        $this->assertEquals("hex_14_7", $this->game->tokens->getTokenLocation("monster_goblin_1"));
    }

    public function testHeroBlocksRotation(): void {
        // Goblin at W of hero_1, but the CW target (NW = hex_12_6) is occupied by another hero.
        // Heroes block rotation (FORUM #3) — monster stays at W.
        $this->game->tokens->moveToken("hero_1", "hex_12_7");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_11_7");
        // hero_2 is in the supply at test start (only 1 hero is "active" but the token exists);
        // drop a second hero on the destination hex to act as the blocker.
        $this->game->tokens->moveToken("hero_2", "hex_12_6");
        $this->game->hexMap->invalidateOccupancy();

        $this->createOp("monsterDieManeuver(cw)");
        $this->call_resolve();

        $this->assertEquals("hex_11_7", $this->game->tokens->getTokenLocation("monster_goblin_1"), "another hero on the destination blocks rotation");
    }

    public function testTwoMonstersDoNotBlockEachOtherInSameGroup(): void {
        // Two monsters around the same hero rotate in lock-step — neither blocks the other
        // (FORUM #2: "monsters in general wouldn't block each other, as they are moving all at once").
        // Hero at hex_12_7. Goblin1 at W (hex_11_7), Goblin2 at NW (hex_12_6).
        // CW rotation: W → NW (hex_12_6, where Goblin2 currently is) and NW → NE (hex_13_6).
        $this->game->tokens->moveToken("monster_goblin_1", "hex_11_7");
        $this->game->tokens->moveToken("monster_goblin_2", "hex_12_6");
        $this->game->hexMap->invalidateOccupancy();

        $this->createOp("monsterDieManeuver(cw)");
        $this->call_resolve();

        $this->assertEquals("hex_12_6", $this->game->tokens->getTokenLocation("monster_goblin_1"), "goblin_1 rotated W → NW");
        $this->assertEquals("hex_13_6", $this->game->tokens->getTokenLocation("monster_goblin_2"), "goblin_2 rotated NW → NE");
    }

    public function testRotationIntoGrimheimDestroysHouse(): void {
        // Park hero adjacent to Grimheim so a CW rotation lands the goblin in Grimheim.
        // Hero at hex_11_8 (adjacent to Grimheim hex_10_8). Goblin at NW of hero =
        // axial offset (-1,0) → hex_10_8 is actually Grimheim already; use a different
        // anchor: hero at hex_8_8 (adjacent to Grimheim hex_8_9 and hex_9_8).
        // Goblin at W of hero = hex_7_8 (plains). CW → NW = (0,-1) → hex_8_7 (plains).
        // That doesn't trigger Grimheim. Instead: place goblin at NE of hero (q+0, r-1)
        // = hex_8_7. CW → E = (1,-1) → hex_9_7. Hmm — also not Grimheim.
        // Simplest: hero at hex_9_7 (plains). Adjacent: hex_10_7, hex_8_7, hex_9_8 (Grimheim!),
        // hex_9_6, hex_10_6, hex_8_8. Place goblin at hex_10_7 (E offset = (1,0)). CW → SE =
        // (1,1)? No — cwDirs[idx for (1,0)] = 3, next CW = idx 4 = (0,1) → hex_9_8 (Grimheim!).
        $this->game->tokens->moveToken("hero_1", "hex_9_7");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_10_7");
        $this->game->hexMap->invalidateOccupancy();

        $housesBefore = count($this->game->tokens->getTokensOfTypeInLocation("house", "hex%"));

        $this->createOp("monsterDieManeuver(cw)");
        $this->call_resolve();

        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"), "goblin removed after entering Grimheim");
        $housesAfter = count($this->game->tokens->getTokensOfTypeInLocation("house", "hex%"));
        $this->assertEquals($housesBefore - 1, $housesAfter, "exactly one house destroyed by the maneuver-into-Grimheim");
    }
}
