<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_moveMonster;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_moveMonsterTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        // Assign hero 1 (Bjorn) to PCOLOR
        $this->game->tokens->moveToken("card_hero_1_1", $this->getPlayersTableau());
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function createPhase2Op(string $monsterHex, string $expr = "1moveMonster"): Op_moveMonster {
        /** @var Op_moveMonster */
        $op = $this->createOp($expr, ["target" => $monsterHex]);
        return $op;
    }

    // -------------------------------------------------------------------------
    // Phase 1: getPossibleMoves — select monster
    // -------------------------------------------------------------------------

    public function testNoMonstersAdjacentReturnsEmpty(): void {
        $op = $this->op;
        $this->assertNoValidTargets();
    }

    public function testAdjacentMonsterIsTargetable(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->assertValidTarget("hex_12_8");
    }

    public function testNonAdjacentMonsterNotTargetable(): void {
        // hex_13_7 is 2 hexes away from hex_11_8 — out of adj range
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $op = $this->op;
        $this->assertNoValidTargets();
    }

    public function testMultipleAdjacentMonsters(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_11_7");
        $this->assertValidTargetCount(2);
    }

    // -------------------------------------------------------------------------
    // Phase 1: resolve — queues phase 2
    // -------------------------------------------------------------------------

    private function getQueuedOp(): ?array {
        $ops = $this->game->machine->getTopOperations(PCOLOR);
        return $ops ? reset($ops) : null;
    }

    public function testResolvePhase1QueuesMoveMonsterWithDestination(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->op;
        $this->call_resolve("hex_12_8");
        // Should have queued a moveMonster operation with destination field
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("moveMonster", $queued["type"]);
        $data = is_string($queued["data"]) ? json_decode($queued["data"], true) : $queued["data"] ?? [];
        $this->assertEquals("hex_12_8", $data["target"]);
    }

    // -------------------------------------------------------------------------
    // Phase 2: getPossibleMoves — select destination hex
    // -------------------------------------------------------------------------

    public function testPhase2ReturnsReachableHexes(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->op = $this->createPhase2Op("hex_12_8");
        $targets = $this->op->getArgsTarget();
        $this->assertNotEmpty($targets);
        // All returned hexes should be adjacent to hex_12_8 (1 step)
        $adjacent = $this->game->hexMap->getAdjacentHexes("hex_12_8");
        foreach ($targets as $hex) {
            $this->assertTrue(in_array($hex, $adjacent), "$hex should be adjacent to hex_12_8");
        }
    }

    public function testPhase2ExcludesOccupiedHexes(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        // Put another monster on an adjacent hex
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_7");
        $this->op = $this->createPhase2Op("hex_12_8");
        $this->assertNotValidTarget("hex_12_7");
    }

    public function testPhase2ExcludesGrimheimHexes(): void {
        // Place monster adjacent to Grimheim
        $this->game->tokens->moveToken("monster_goblin_1", "hex_8_8");
        $this->op = $this->createPhase2Op("hex_8_8");
        foreach ($this->op->getArgsTarget() as $hex) {
            $this->assertFalse($this->game->hexMap->isInGrimheim($hex), "Grimheim hex $hex should not be offered as destination");
        }
    }

    public function testPhase2MandatoryMovementFiltersToExactDistance(): void {
        // 2moveMonster mandatory: must move exactly 2 steps
        $this->game->tokens->moveToken("monster_goblin_1", "hex_5_5");
        $this->op = $this->createPhase2Op("hex_5_5", "2moveMonster");
        $this->assertNotEmpty($this->op->getArgsTarget());
        // Adjacent hexes (distance 1) should NOT be offered
        foreach ($this->game->hexMap->getAdjacentHexes("hex_5_5") as $adj) {
            $this->assertNotValidTarget($adj, "Adjacent hex $adj should not be offered for mandatory 2-step move");
        }
    }

    public function testPhase2OptionalShowsAllDistances(): void {
        // [0,2]moveMonster: show all reachable hexes at distance 1 and 2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_5_5");
        $this->op = $this->createPhase2Op("hex_5_5", "[0,2]moveMonster");
        $targets = $this->op->getArgsTarget();
        $adjacent = $this->game->hexMap->getAdjacentHexes("hex_5_5");
        $hasDistance1 = count(array_intersect($targets, $adjacent)) > 0;
        $hasDistance2 = count(array_diff($targets, $adjacent)) > 0;
        $this->assertTrue($hasDistance1, "Should include distance 1 hexes for optional movement");
        $this->assertTrue($hasDistance2, "Should include distance 2 hexes for optional 2-step movement");
    }

    public function testPhase2MonstersCanEnterMountains(): void {
        // hex_7_6 is plains, hex_6_6 is mountain and adjacent to hex_7_6
        // Monsters can enter mountains, heroes cannot
        $this->game->tokens->moveToken("monster_goblin_1", "hex_7_6");
        $this->op = $this->createPhase2Op("hex_7_6");
        $this->assertValidTarget("hex_6_6", "Monsters should be able to enter mountain hexes");
    }

    // -------------------------------------------------------------------------
    // Phase 2: resolve — moves monster
    // -------------------------------------------------------------------------

    public function testResolvePhase2MovesMonster(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->op = $this->createPhase2Op("hex_12_8");
        $target = "hex_13_8";
        $this->assertValidTarget($target);
        $this->call_resolve($target);
        $this->assertEquals($target, $this->game->tokens->getTokenLocation("monster_goblin_1"));
    }

    public function testResolvePhase2MovesCorrectMonster(): void {
        // Two monsters on map — only the targeted one should move
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_5_5");
        $this->op = $this->createPhase2Op("hex_12_8");
        $target = "hex_13_8";
        $this->assertValidTarget($target);
        $this->call_resolve($target);
        $this->assertEquals($target, $this->game->tokens->getTokenLocation("monster_goblin_1"));
        $this->assertEquals("hex_5_5", $this->game->tokens->getTokenLocation("monster_brute_1"));
    }

    public function testPhase1AutoResolvesWithSingleMonster(): void {
        // Only one adjacent monster — phase 1 should auto-resolve and queue phase 2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->assertValidTargetCount(1);
        // Auto-resolve should queue a moveMonster with destination field
        $this->call_resolve("hex_12_8");
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("moveMonster", $queued["type"]);
    }

    public function testPhase2EmptyHexReturnsNoMoves(): void {
        // Monster was on hex_12_8 but got killed between phases — no character on hex
        $this->op = $this->createPhase2Op("hex_12_8");
        // hex_12_8 is empty (no monster placed there)
        $this->assertNoValidTargets();
    }
}
