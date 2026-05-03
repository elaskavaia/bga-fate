<?php

declare(strict_types=1);

/**
 * Tests for Op_spawn — pulls N monsters of a given type from supply_monster
 * and places them on free hexes adjacent to the acting hero.
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

    public function testSpawnsOneMonsterOnAdjacentHex(): void {
        $supplyBefore = count($this->game->tokens->getTokensOfTypeInLocation("monster_brute", "supply_monster"));
        $this->createOp("spawn(brute)");
        $this->op->resolve();
        $this->assertEquals(1, $this->countAdjacentMonsters("brute"));
        $supplyAfter = count($this->game->tokens->getTokensOfTypeInLocation("monster_brute", "supply_monster"));
        $this->assertEquals($supplyBefore - 1, $supplyAfter);
    }

    public function testSpawnsMultipleOnDistinctHexes(): void {
        $this->createOp("3spawn(goblin)");
        $this->op->resolve();
        $this->assertEquals(3, $this->countAdjacentMonsters("goblin"));
    }

    public function testStopsWhenAdjRingIsFull(): void {
        // Fill 5 of the 6 adj hexes with already-spawned goblins
        $adj = $this->game->hexMap->getAdjacentHexes($this->heroHex);
        for ($i = 0; $i < 5; $i++) {
            $this->game->getMonster("monster_goblin_" . ($i + 1))->moveTo($adj[$i], "");
        }
        // Try to spawn 3 brutes — only 1 free hex
        $this->createOp("3spawn(brute)");
        $this->op->resolve();
        $this->assertEquals(1, $this->countAdjacentMonsters("brute"));
    }

    public function testStopsWhenSupplyExhausted(): void {
        // Move all but one brute to a far hex so they're not in supply
        $brutes = array_keys($this->game->tokens->getTokensOfTypeInLocation("monster_brute", "supply_monster"));
        $brutesAvailable = 1;
        foreach (array_slice($brutes, $brutesAvailable) as $tokenId) {
            $this->game->tokens->moveToken($tokenId, "limbo");
        }
        $this->createOp("3spawn(brute)");
        $this->op->resolve();
        $this->assertEquals($brutesAvailable, $this->countAdjacentMonsters("brute"));
    }

    public function testNoOpWhenSupplyEmpty(): void {
        foreach (array_keys($this->game->tokens->getTokensOfTypeInLocation("monster_brute", "supply_monster")) as $tokenId) {
            $this->game->tokens->moveToken($tokenId, "limbo");
        }
        $this->createOp("spawn(brute)");
        $this->op->resolve();
        $this->assertEquals(0, $this->countAdjacentMonsters("brute"));
    }

    public function testNoOpWhenAllAdjHexesOccupied(): void {
        $adj = $this->game->hexMap->getAdjacentHexes($this->heroHex);
        foreach ($adj as $i => $hex) {
            $this->game->getMonster("monster_goblin_" . ($i + 1))->moveTo($hex, "");
        }
        $this->createOp("spawn(brute)");
        $this->op->resolve();
        $this->assertEquals(0, $this->countAdjacentMonsters("brute"));
    }
}
