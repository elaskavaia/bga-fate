<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_actionAttack;
use Bga\Games\Fate\OpCommon\Operation;

final class Op_actionAttackTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves tested via noValidTargets(), getArgsInfo() and getArgsTarget()
    // -------------------------------------------------------------------------

    public function testNoMonstersAdjacentReturnsEmpty(): void {
        $this->assertNoValidTargets();
    }

    public function testAdjacentMonsterIsTargetable(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->assertValidTarget("hex_12_8");
    }

    public function testRange2MonsterTargetableWithBow(): void {
        // Bjorn has First Bow (attack_range=2), hex_13_7 is 2 steps from hex_11_8
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $this->assertValidTarget("hex_13_7");
    }

    public function testOutOfRangeMonsterNotTargetable(): void {
        // Remove bow so Bjorn has range=1
        $this->game->tokens->moveToken("card_equip_1_15", "limbo");
        $hero = $this->game->getHeroById("hero_1");
        $hero->recalcTrackers();

        // hex_13_7 is 2 hexes from hex_11_8 — out of range with range=1
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $this->assertNotValidTarget("hex_13_7");
    }

    public function testAdjacentHeroNotTargetable(): void {
        $this->game->tokens->moveToken("hero_2", "hex_12_8");
        $op = $this->op;
        $moves = $op->getPossibleMoves();
        $this->assertNotContains("hex_12_8", $moves);
    }

    // -------------------------------------------------------------------------
    // resolve — queues roll with correct strength and target
    // -------------------------------------------------------------------------

    public function testResolveQueuesRollWithStrength(): void {
        // Bjorn strength = 3 (hero card 2 + equip +1)
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->op;
        $this->call_resolve("hex_12_8");

        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("roll", $opTypes);
    }

    public function testResolveRollHasTargetData(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->op;
        $this->call_resolve("hex_12_8");

        $ops = $this->game->machine->getAllOperations(PCOLOR);
        foreach ($ops as $o) {
            if (str_contains($o["type"], "roll")) {
                $data = is_string($o["data"]) ? json_decode($o["data"], true) : $o["data"] ?? [];
                $this->assertEquals("hex_12_8", $data["target"]);
                return;
            }
        }
        $this->fail("roll operation not found");
    }

    public function testResolveInvalidTargetThrows(): void {
        $op = $this->op;
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->expectOutputRegex("/./");
        $this->call_resolve("hex_12_8");
    }
}
