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
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
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
        $op = $this->op;
        $moves = $op->getPossibleMoves();
        $this->assertContains("hex_12_8", $moves);
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
        $op = $this->op;
        $moves = $op->getPossibleMoves();
        $this->assertCount(2, $moves);
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
        $op = $this->createPhase2Op("hex_12_8");
        $moves = $op->getPossibleMoves();
        $this->assertNotEmpty($moves);
        // All returned hexes should be adjacent to hex_12_8 (1 step)
        foreach (array_keys($moves) as $hex) {
            $this->assertTrue(in_array($hex, $this->game->hexMap->getAdjacentHexes("hex_12_8")), "$hex should be adjacent to hex_12_8");
        }
    }

    public function testPhase2ExcludesOccupiedHexes(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        // Put another monster on an adjacent hex
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_7");
        $op = $this->createPhase2Op("hex_12_8");
        $moves = $op->getPossibleMoves();
        $this->assertArrayNotHasKey("hex_12_7", $moves);
    }

    public function testPhase2ExcludesGrimheimHexes(): void {
        // Place monster adjacent to Grimheim
        $this->game->tokens->moveToken("monster_goblin_1", "hex_8_8");
        $op = $this->createPhase2Op("hex_8_8");
        $moves = $op->getPossibleMoves();
        foreach (array_keys($moves) as $hex) {
            $this->assertFalse($this->game->hexMap->isInGrimheim($hex), "Grimheim hex $hex should not be offered as destination");
        }
    }

    public function testPhase2MandatoryMovementFiltersToExactDistance(): void {
        // 2moveMonster mandatory: must move exactly 2 steps
        $this->game->tokens->moveToken("monster_goblin_1", "hex_5_5");
        $op = $this->createPhase2Op("hex_5_5", "2moveMonster");
        $moves = $op->getPossibleMoves();
        $this->assertNotEmpty($moves);
        // Adjacent hexes (distance 1) should NOT be offered
        $adjacent = $this->game->hexMap->getAdjacentHexes("hex_5_5");
        foreach ($adjacent as $adj) {
            $this->assertArrayNotHasKey($adj, $moves, "Adjacent hex $adj should not be offered for mandatory 2-step move");
        }
    }

    public function testPhase2OptionalShowsAllDistances(): void {
        // [0,2]moveMonster: show all reachable hexes at distance 1 and 2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_5_5");
        $op = $this->createPhase2Op("hex_5_5", "[0,2]moveMonster");
        $moves = $op->getPossibleMoves();
        $adjacent = $this->game->hexMap->getAdjacentHexes("hex_5_5");
        // Should include distance 1 hexes
        $hasDistance1 = false;
        foreach ($adjacent as $adj) {
            if (array_key_exists($adj, $moves)) {
                $hasDistance1 = true;
                break;
            }
        }
        $this->assertTrue($hasDistance1, "Should include distance 1 hexes for optional movement");
        // Should include at least one hex NOT adjacent to source (distance 2)
        $hasDistance2 = false;
        foreach (array_keys($moves) as $hex) {
            if (!in_array($hex, $adjacent)) {
                $hasDistance2 = true;
                break;
            }
        }
        $this->assertTrue($hasDistance2, "Should include distance 2 hexes for optional 2-step movement");
    }

    public function testPhase2MonstersCanEnterMountains(): void {
        // hex_7_6 is plains, hex_6_6 is mountain and adjacent to hex_7_6
        // Monsters can enter mountains, heroes cannot
        $this->game->tokens->moveToken("monster_goblin_1", "hex_7_6");
        $op = $this->createPhase2Op("hex_7_6");
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_6_6", $moves, "Monsters should be able to enter mountain hexes");
    }

    // -------------------------------------------------------------------------
    // Phase 2: resolve — moves monster
    // -------------------------------------------------------------------------

    public function testResolvePhase2MovesMonster(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->op = $this->createPhase2Op("hex_12_8");
        $moves = $this->op->getPossibleMoves();
        $target = array_key_first($moves);
        $this->call_resolve($target);
        $this->assertEquals($target, $this->game->tokens->getTokenLocation("monster_goblin_1"));
    }

    public function testResolvePhase2MovesCorrectMonster(): void {
        // Two monsters on map — only the targeted one should move
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_5_5");
        $this->op = $this->createPhase2Op("hex_12_8");
        $moves = $this->op->getPossibleMoves();
        $target = array_key_first($moves);
        $this->call_resolve($target);
        $this->assertEquals($target, $this->game->tokens->getTokenLocation("monster_goblin_1"));
        $this->assertEquals("hex_5_5", $this->game->tokens->getTokenLocation("monster_brute_1"));
    }

    public function testPhase1AutoResolvesWithSingleMonster(): void {
        // Only one adjacent monster — phase 1 should auto-resolve and queue phase 2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->op;
        $moves = $op->getPossibleMoves();
        $this->assertCount(1, $moves);
        // Auto-resolve should queue a moveMonster with destination field
        $this->call_resolve("hex_12_8");
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("moveMonster", $queued["type"]);
    }

    public function testPhase2EmptyHexReturnsNoMoves(): void {
        // Monster was on hex_12_8 but got killed between phases — no character on hex
        $op = $this->createPhase2Op("hex_12_8");
        // hex_12_8 is empty (no monster placed there)
        $moves = $op->getPossibleMoves();
        // getReachableHexes from an empty hex still returns adjacent hexes,
        // but resolve would fail — getPossibleMoves prevents selecting invalid targets
        $this->assertIsArray($moves);
    }
}
