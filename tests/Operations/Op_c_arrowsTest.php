<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_c_arrows;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_c_arrowsTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->clearMachine(); // drop leftover reinforcement/turnStart so dispatchAll() only runs the queued applyDamage
        // Place hero on plains hex_12_2, adjacent to forest hexes (hex_11_1, hex_12_1, hex_11_2)
        // and plains hex_13_2. Bjorn has attack range 2.
        $this->game->tokens->moveToken("hero_1", "hex_12_2");
    }

    private function getDamage(string $monsterId): int {
        return $this->countRedCrystals($monsterId);
    }

    // -------------------------------------------------------------------------
    // target selection
    // -------------------------------------------------------------------------

    public function testNoMonstersReturnsEmpty(): void {
        $this->assertNoValidTargets();
    }

    public function testMonsterInRangeIsTarget(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_1");
        $this->assertValidTarget("hex_12_1");
    }

    public function testMonsterOutOfRangeNotTarget(): void {
        // hex_12_5 is far from hex_12_2 — outside Bjorn's attack range of 2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_5");
        $this->assertNoValidTargets();
    }

    public function testUsesAttackRangeNotAdjacent(): void {
        // hex_14_2 is 2 hexes from hex_12_2 — within Bjorn's attack range 2 but not adjacent
        $this->game->tokens->moveToken("monster_goblin_1", "hex_14_2");
        $this->assertValidTarget("hex_14_2");
    }

    // -------------------------------------------------------------------------
    // resolve — damage amount based on terrain
    // -------------------------------------------------------------------------

    public function testDeal1DamageOnPlains(): void {
        // hex_13_2 is plains
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_2");
        $op = $this->op;
        $this->call_resolve("hex_13_2");
        $this->dispatchAll();
        $this->assertEquals(1, $this->getDamage("monster_goblin_1"));
    }

    public function testDeal2DamageInForest(): void {
        // hex_12_1 is forest
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_1");
        $op = $this->op;
        $this->call_resolve("hex_12_1");
        $this->dispatchAll();
        // Goblin health=2, 2 damage kills it
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));
    }

    public function testDeal2DamageInForestNonLethal(): void {
        // hex_12_1 is forest, brute has health=3, 2 damage shouldn't kill
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_1");
        $op = $this->op;
        $this->call_resolve("hex_12_1");
        $this->dispatchAll();
        $this->assertEquals(2, $this->getDamage("monster_brute_1"));
        $this->assertEquals("hex_12_1", $this->game->tokens->getTokenLocation("monster_brute_1"));
    }

    public function testDeal1DamageOnPlainsDoesNotKillGoblin(): void {
        // Goblin health=2, 1 damage on plains shouldn't kill
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_2");
        $op = $this->op;
        $this->call_resolve("hex_13_2");
        $this->dispatchAll();
        $this->assertEquals(1, $this->getDamage("monster_goblin_1"));
        $this->assertEquals("hex_13_2", $this->game->tokens->getTokenLocation("monster_goblin_1"));
    }
}
