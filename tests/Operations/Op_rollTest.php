<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_roll;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_rollTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function getDamage(string $monsterId): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $monsterId));
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves
    // -------------------------------------------------------------------------

    public function testNoMonstersAdjacentReturnsEmpty(): void {
        $this->assertNoValidTargets();
    }

    public function testAdjacentMonsterIsTarget(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->assertValidTarget("hex_12_8");
    }

    public function testNonAdjacentMonsterNotTarget(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $this->op = $this->createOp("roll(adj)");
        $this->assertNoValidTargets();
    }

    public function testMultipleAdjacentMonsters(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_11_7");
        $op = $this->op;
        $moves = $op->getArgsTarget();
        $this->assertCount(2, $moves);
    }

    public function testInRangeUsesHeroAttackRange(): void {
        // Bjorn has First Bow (attack_range=2), hex_13_7 is 2 hexes away
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $this->op = $this->createOp("roll(inRange)");
        $this->assertValidTarget("hex_13_7");
    }

    public function testInRange3ReachesDistance3(): void {
        // hex_14_6 is 3 hexes from hex_11_8
        $this->game->tokens->moveToken("monster_goblin_1", "hex_14_6");
        $this->op = $this->createOp("roll(inRange3)");
        $this->assertValidTarget("hex_14_6");
    }

    public function testAdjDoesNotReachDistance2(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $this->op = $this->createOp("roll(adj)");
        $this->assertNoValidTargets();
    }

    public function testAdjacentHeroNotTarget(): void {
        $this->game->tokens->moveToken("hero_2", "hex_12_8");
        $this->assertNotValidTarget("hex_12_8");
    }

    // -------------------------------------------------------------------------
    // resolve — rolls dice and queues resolveHits
    // -------------------------------------------------------------------------

    public function testResolveRollsDice(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $this->op = $this->createOp("3roll");
        $this->call_resolve("hex_12_8");
        // 3 dice should be on display_battle
        $diceOnDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        $this->assertCount(3, $diceOnDisplay);
    }

    public function testResolveQueuesResolveHits(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $this->op = $this->createOp("3roll");
        $this->call_resolve("hex_12_8");
        // resolveHits should be queued in the machine
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("resolveHits", $opTypes);
    }

    public function testResolveDoesNotApplyDamageDirectly(): void {
        // Roll only puts dice on display — damage comes from resolveHits → dealDamage
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $this->op = $this->createOp("3roll");
        $this->call_resolve("hex_12_8");
        // No red crystals on the monster yet (resolveHits hasn't run)
        $this->assertEquals(0, $this->getDamage("monster_troll_1"));
    }

    public function testCountDeterminesDiceRolled(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $this->op = $this->createOp("5roll");
        $this->call_resolve("hex_12_8");
        $diceOnDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        $this->assertCount(5, $diceOnDisplay);
    }
}
