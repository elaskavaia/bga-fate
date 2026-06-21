<?php

declare(strict_types=1);

/**
 * Tests for Op_spawn - places N monsters of a given type from supply_monster
 * onto free hexes adjacent to the acting hero. Placement is player-chosen
 * (RULES.md "Ambush"); each placement re-queues the remainder.
 */
final class Op_spawnTest extends AbstractOpTestCase {
    private string $heroHex = "hex_11_8";

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", $this->heroHex);
        $this->clearAdjacentHexes();
    }

    /** Move any character off the hero's adjacent ring so spawn slots are predictable. */
    private function clearAdjacentHexes(): void {
        foreach ($this->game->hexMap->getAdjacentHexes($this->heroHex) as $hex) {
            $charId = $this->game->hexMap->getCharacterOnHex($hex);
            if ($charId !== null) {
                $this->game->getMonster($charId)->moveTo("supply_monster", "");
            }
        }
    }

    private function countAdjacentMonsters(string $type): int {
        $count = 0;
        foreach ($this->game->hexMap->getAdjacentHexes($this->heroHex) as $hex) {
            $charId = $this->game->hexMap->getCharacterOnHex($hex);
            if ($charId !== null && str_starts_with($charId, "monster_$type")) {
                $count++;
            }
        }
        return $count;
    }

    /** Drive a spawn op to completion, always choosing the first offered free hex. */
    private function resolveSpawnChain(string $opType): void {
        $op = $this->createOp($opType);
        while ($op !== null) {
            $targets = array_keys($op->getPossibleMoves());
            if (empty($targets)) {
                break; // supply exhausted or ring full - silent skip
            }
            $op->action_resolve(["target" => $targets[0]]);
            $op = $this->topSpawnOp();
        }
    }

    /** Next queued spawn op for the owner (the re-queued remainder), or null. */
    private function topSpawnOp() {
        foreach ($this->game->machine->getTopOperations($this->owner) as $row) {
            if (str_contains($row["type"] ?? "", "spawn(")) {
                return $this->game->machine->instantiateOperationFromDbRow($row);
            }
        }
        return null;
    }

    public function testSpawnsOneMonsterOnAdjacentHex(): void {
        $supplyBefore = count($this->game->tokens->getTokensOfTypeInLocation("monster_brute", "supply_monster"));
        $this->resolveSpawnChain("spawn(brute)");
        $this->assertEquals(1, $this->countAdjacentMonsters("brute"));
        $supplyAfter = count($this->game->tokens->getTokensOfTypeInLocation("monster_brute", "supply_monster"));
        $this->assertEquals($supplyBefore - 1, $supplyAfter);
    }

    public function testSpawnsMultipleOnDistinctHexes(): void {
        $this->resolveSpawnChain("3spawn(goblin)");
        $this->assertEquals(3, $this->countAdjacentMonsters("goblin"));
    }

    public function testStopsWhenAdjRingIsFull(): void {
        // Fill 5 of the 6 adj hexes with already-spawned goblins
        $adj = $this->game->hexMap->getAdjacentHexes($this->heroHex);
        for ($i = 0; $i < 5; $i++) {
            $this->game->getMonster("monster_goblin_" . ($i + 1))->moveTo($adj[$i], "");
        }
        // Try to spawn 3 brutes - only 1 free hex
        $this->resolveSpawnChain("3spawn(brute)");
        $this->assertEquals(1, $this->countAdjacentMonsters("brute"));
    }

    public function testStopsWhenSupplyExhausted(): void {
        // Move all but one brute to a far hex so they're not in supply
        $brutes = array_keys($this->game->tokens->getTokensOfTypeInLocation("monster_brute", "supply_monster"));
        $brutesAvailable = 1;
        foreach (array_slice($brutes, $brutesAvailable) as $tokenId) {
            $this->game->tokens->moveToken($tokenId, "limbo");
        }
        $this->resolveSpawnChain("3spawn(brute)");
        $this->assertEquals($brutesAvailable, $this->countAdjacentMonsters("brute"));
    }

    public function testNoOpWhenSupplyEmpty(): void {
        foreach (array_keys($this->game->tokens->getTokensOfTypeInLocation("monster_brute", "supply_monster")) as $tokenId) {
            $this->game->tokens->moveToken($tokenId, "limbo");
        }
        $this->resolveSpawnChain("spawn(brute)");
        $this->assertEquals(0, $this->countAdjacentMonsters("brute"));
    }

    public function testNoOpWhenAllAdjHexesOccupied(): void {
        $adj = $this->game->hexMap->getAdjacentHexes($this->heroHex);
        foreach ($adj as $i => $hex) {
            $this->game->getMonster("monster_goblin_" . ($i + 1))->moveTo($hex, "");
        }
        $this->resolveSpawnChain("spawn(brute)");
        $this->assertEquals(0, $this->countAdjacentMonsters("brute"));
    }

    public function testPlacementIsMandatoryWhenHexIsFree(): void {
        // With a free adjacent hex and supply available, the player cannot skip placement.
        $op = $this->createOp("spawn(brute)");
        $this->assertFalse($op->canSkip(), "spawn must be mandatory while a free adjacent hex exists");
    }

    public function testBlockedSpawnIsSkippable(): void {
        // Fully surround the hero - a mandatory spawn with no free hex must be skippable
        // (else the machine hangs on a no-target op).
        $adj = $this->game->hexMap->getAdjacentHexes($this->heroHex);
        foreach ($adj as $i => $hex) {
            $this->game->getMonster("monster_goblin_" . ($i + 1))->moveTo($hex, "");
        }
        $this->game->hexMap->invalidateOccupancy();
        $op = $this->createOp("spawn(brute)");
        $this->assertTrue($op->canSkip(), "a blocked spawn must auto-skip instead of hanging");
    }

    public function testConstrainedMultiSpawnDrainsViaMachine(): void {
        // Real machine path (not the hand-driven helper): a multi-spawn whose ring fills
        // mid-chain must auto-skip the remainder, not hang on a mandatory no-target op.
        $adj = $this->game->hexMap->getAdjacentHexes($this->heroHex);
        for ($i = 0; $i < 5; $i++) {
            $this->game->getMonster("monster_goblin_" . ($i + 1))->moveTo($adj[$i], "");
        }
        $this->game->hexMap->invalidateOccupancy();

        $this->game->machine->push("3spawn(brute)", $this->owner);
        $this->dispatchAll();

        $this->assertEquals(1, $this->countAdjacentMonsters("brute"), "only the single free hex gets a brute");
        $leftover = array_filter(
            $this->game->machine->getTopOperations($this->owner),
            fn($row) => str_contains($row["type"] ?? "", "spawn(")
        );
        $this->assertCount(0, $leftover, "remaining spawns must auto-skip, not hang the machine");
    }
}
