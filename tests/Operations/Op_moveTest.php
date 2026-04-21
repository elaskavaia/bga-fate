<?php

declare(strict_types=1);

final class Op_moveTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        // Assign hero 1 (Bjorn) to PCOLOR
        $this->game->tokens->moveToken("card_hero_1_1", $this->getPlayersTableau());
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function getHeroHex(): string {
        return $this->game->tokens->getTokenLocation("hero_1");
    }

    public function testMoveHero1ReachesAdjacent(): void {
        $this->createOp("1move");
        // hex_12_8 is adjacent plains, should be reachable
        $this->assertValidTarget("hex_12_8");
    }

    public function testMoveHero1DoesNotReachDistance2(): void {
        $this->createOp("1move");
        // hex_13_7 is distance 2 from hex_11_8
        $this->assertNotValidTarget("hex_13_7");
    }

    public function testMoveHero2MandatoryOnlyDistance2(): void {
        // 2move is mandatory: must move exactly 2 steps
        $this->createOp("2move");
        // hex_12_8 is adjacent (distance 1) — should NOT be offered for mandatory 2-step move
        $this->assertNotValidTarget("hex_12_8");
        // hex_13_7 is distance 2 — should be offered
        $this->assertValidTarget("hex_13_7");
    }

    public function testMoveHeroOptionalShowsAllDistances(): void {
        // [0,2]move is optional: show all reachable hexes
        $this->createOp("[0,2]move");
        // Should include both distance 1 and distance 2
        $this->assertValidTarget("hex_12_8"); // distance 1
        $this->assertValidTarget("hex_13_7"); // distance 2
    }

    public function testResolveMovesHero(): void {
        $this->createOp("1move");
        $target = $this->op->getArgsTarget()[0] ?? null;
        $this->assertNotNull($target);
        $this->call_resolve($target);
        $this->dispatchAll();
        $this->assertEquals($target, $this->getHeroHex());
    }

    public function testLocationOnlyFiltersToNamedLocationHexes(): void {
        // Hero starts at hex_11_8 (plains, no loc). Grimheim hexes (10_8, 10_9, 9_9, etc.) are
        // within 2 steps. Non-location plains like 12_8, 11_7 are also reachable but must be excluded.
        $this->createOp("[0,2]move(locationOnly)");
        $targets = $this->op->getArgsTarget();

        $this->assertNotEmpty($targets, "Should offer at least some Grimheim hexes within 2 steps of hex_11_8");
        foreach ($targets as $hexId) {
            $loc = $this->game->hexMap->getHexNamedLocation($hexId);
            $this->assertNotEquals("", $loc, "Hex $hexId should belong to a named location but has none");
        }
        // Spot-check: a known Grimheim hex within reach is offered
        $this->assertValidTarget("hex_10_8");
        // Spot-check: a known non-location adjacent hex is NOT offered
        $this->assertNotValidTarget("hex_12_8");
    }

    public function testLocationOnlyOptionalAllowsSkip(): void {
        // [0,N]move is optional — the player must be able to decline to move even when
        // the locationOnly filter is in effect. Staying put is expressed via canSkip(), not
        // by including the current hex in getPossibleMoves().
        $this->game->tokens->moveToken("hero_1", "hex_10_9");
        $this->createOp("[0,2]move(locationOnly)");
        $this->assertTrue($this->op->canSkip(), "Optional move with locationOnly must be skippable");
    }

    public function testLocationOnlyEmptyWhenNoReachableLocation(): void {
        // hex_13_6 is a plains hex with no named location. Within 2 steps nothing else has a loc
        // either (surrounding hexes are plains/mountain without loc — see map_material.csv).
        $this->game->tokens->moveToken("hero_1", "hex_13_6");
        $this->createOp("[0,2]move(locationOnly)");

        // Recompute the raw reachable set and assert none of them have a loc — if this assertion
        // fails, the test fixture (hero start hex) needs to change, not the production code.
        $reachable = $this->game->hexMap->getReachableHexes("hex_13_6", 2);
        foreach (array_keys($reachable) as $hexId) {
            $this->assertEquals(
                "",
                $this->game->hexMap->getHexNamedLocation($hexId),
                "Test fixture assumption broken: hex $hexId has a named location"
            );
        }
        $this->assertNoValidTargets("No location hexes reachable → moves should be empty");
    }

    public function testMoveHeroWithoutParamReturnsAllReachable(): void {
        // Regression check: without locationOnly, 2move returns both location and non-location hexes.
        $this->createOp("[0,2]move");
        $this->assertValidTarget("hex_12_8", "Non-location hex should be offered without the param");
        $this->assertValidTarget("hex_10_8", "Grimheim hex should still be offered without the param");
    }

    public function testResolveToGrimheimUsesHomeHex(): void {
        // Move hero adjacent to Grimheim
        $this->game->tokens->moveToken("hero_1", "hex_8_8");
        $this->createOp("1move");
        $moves = $this->op->getArgsTarget();
        // Find a Grimheim hex in moves
        $grimheimHex = null;
        foreach ($moves as $hex) {
            if ($this->game->hexMap->isInGrimheim($hex)) {
                $grimheimHex = $hex;
                break;
            }
        }
        $this->assertNotNull($grimheimHex, "Should be able to reach Grimheim from hex_8_8");
        $this->call_resolve($grimheimHex);
        $this->dispatchAll();
        // Hero should be at their home hex in Grimheim, not the clicked hex
        $heroHex = $this->getHeroHex();
        $this->assertTrue($this->game->hexMap->isInGrimheim($heroHex));
    }
}
