<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_moveHero;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_moveHeroTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
        // Assign hero 1 (Bjorn) to PCOLOR
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function createOp(string $expr = "1moveHero"): Op_moveHero {
        /** @var Op_moveHero */
        $op = $this->game->machine->instanciateOperation($expr, PCOLOR);
        return $op;
    }

    private function getHeroHex(): string {
        return $this->game->tokens->getTokenLocation("hero_1");
    }

    public function testMoveHero1ReachesAdjacent(): void {
        $op = $this->createOp("1moveHero");
        $moves = $op->getPossibleMoves();
        // Should have some reachable hexes (adjacent non-blocked)
        $this->assertNotEmpty($moves);
        // hex_12_8 is adjacent plains, should be reachable
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    public function testMoveHero1DoesNotReachDistance2(): void {
        $op = $this->createOp("1moveHero");
        $moves = $op->getPossibleMoves();
        // hex_13_7 is distance 2 from hex_11_8
        $this->assertArrayNotHasKey("hex_13_7", $moves);
    }

    public function testMoveHero2MandatoryOnlyDistance2(): void {
        // 2moveHero is mandatory: must move exactly 2 steps
        $op = $this->createOp("2moveHero");
        $moves = $op->getPossibleMoves();
        $this->assertNotEmpty($moves);
        // hex_12_8 is adjacent (distance 1) — should NOT be offered for mandatory 2-step move
        $this->assertArrayNotHasKey("hex_12_8", $moves);
        // hex_13_7 is distance 2 — should be offered
        $this->assertArrayHasKey("hex_13_7", $moves);
    }

    public function testMoveHeroOptionalShowsAllDistances(): void {
        // [0,2]moveHero is optional: show all reachable hexes
        $op = $this->createOp("[0,2]moveHero");
        $moves = $op->getPossibleMoves();
        // Should include both distance 1 and distance 2
        $this->assertArrayHasKey("hex_12_8", $moves); // distance 1
        $this->assertArrayHasKey("hex_13_7", $moves); // distance 2
    }

    public function testResolveMovesHero(): void {
        $op = $this->createOp("1moveHero");
        $moves = $op->getPossibleMoves();
        $target = array_key_first($moves);
        $op->action_resolve(["target" => $target]);
        $this->assertEquals($target, $this->getHeroHex());
    }

    public function testLocationOnlyFiltersToNamedLocationHexes(): void {
        // Hero starts at hex_11_8 (plains, no loc). Grimheim hexes (10_8, 10_9, 9_9, etc.) are
        // within 2 steps. Non-location plains like 12_8, 11_7 are also reachable but must be excluded.
        $op = $this->createOp("[0,2]moveHero(locationOnly)");
        $moves = $op->getPossibleMoves();

        $this->assertNotEmpty($moves, "Should offer at least some Grimheim hexes within 2 steps of hex_11_8");
        foreach (array_keys($moves) as $hexId) {
            $loc = $this->game->hexMap->getHexNamedLocation($hexId);
            $this->assertNotEquals("", $loc, "Hex $hexId should belong to a named location but has none");
        }
        // Spot-check: a known Grimheim hex within reach is offered
        $this->assertArrayHasKey("hex_10_8", $moves);
        // Spot-check: a known non-location adjacent hex is NOT offered
        $this->assertArrayNotHasKey("hex_12_8", $moves);
    }

    public function testLocationOnlyOptionalAllowsSkip(): void {
        // [0,N]moveHero is optional — the player must be able to decline to move even when
        // the locationOnly filter is in effect. Staying put is expressed via canSkip(), not
        // by including the current hex in getPossibleMoves().
        $this->game->tokens->moveToken("hero_1", "hex_10_9");
        $op = $this->createOp("[0,2]moveHero(locationOnly)");
        $this->assertTrue($op->canSkip(), "Optional move with locationOnly must be skippable");
    }

    public function testLocationOnlyEmptyWhenNoReachableLocation(): void {
        // hex_13_6 is a plains hex with no named location. Within 2 steps nothing else has a loc
        // either (surrounding hexes are plains/mountain without loc — see map_material.csv).
        $this->game->tokens->moveToken("hero_1", "hex_13_6");
        $op = $this->createOp("[0,2]moveHero(locationOnly)");
        $moves = $op->getPossibleMoves();

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
        $this->assertEmpty($moves, "No location hexes reachable → moves should be empty");
    }

    public function testMoveHeroWithoutParamReturnsAllReachable(): void {
        // Regression check: without locationOnly, 2moveHero returns both location and non-location hexes.
        $op = $this->createOp("[0,2]moveHero");
        $moves = $op->getPossibleMoves();

        $this->assertArrayHasKey("hex_12_8", $moves, "Non-location hex should be offered without the param");
        $this->assertArrayHasKey("hex_10_8", $moves, "Grimheim hex should still be offered without the param");
    }

    public function testResolveToGrimheimUsesHomeHex(): void {
        // Move hero adjacent to Grimheim
        $this->game->tokens->moveToken("hero_1", "hex_8_8");
        $op = $this->createOp("1moveHero");
        $moves = $op->getPossibleMoves();
        // Find a Grimheim hex in moves
        $grimheimHex = null;
        foreach (array_keys($moves) as $hex) {
            if ($this->game->hexMap->isInGrimheim($hex)) {
                $grimheimHex = $hex;
                break;
            }
        }
        $this->assertNotNull($grimheimHex, "Should be able to reach Grimheim from hex_8_8");
        $op->action_resolve(["target" => $grimheimHex]);
        // Hero should be at their home hex in Grimheim, not the clicked hex
        $heroHex = $this->getHeroHex();
        $this->assertTrue($this->game->hexMap->isInGrimheim($heroHex));
    }
}
