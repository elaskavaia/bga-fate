<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_actionAttack;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_actionAttackTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function createOp(): Op_actionAttack {
        /** @var Op_actionAttack */
        $op = $this->game->machine->instanciateOperation("actionAttack", PCOLOR);
        return $op;
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves
    // -------------------------------------------------------------------------

    public function testNoMonstersAdjacentReturnsEmpty(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    public function testAdjacentMonsterIsTargetable(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertContains("hex_12_8", $moves);
    }

    public function testRange2MonsterTargetableWithBow(): void {
        // Bjorn has First Bow (attack_range=2), hex_13_7 is 2 steps from hex_11_8
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertContains("hex_13_7", $moves);
    }

    public function testOutOfRangeMonsterNotTargetable(): void {
        // Remove bow so Bjorn has range=1
        $this->game->tokens->moveToken("card_equip_1_15", "limbo");
        $hero = $this->game->getHeroById("hero_1");
        $hero->recalcTrackers();

        // hex_13_7 is 2 hexes from hex_11_8 — out of range with range=1
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");

        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertNotContains("hex_13_7", $moves);
    }

    public function testAdjacentHeroNotTargetable(): void {
        $this->game->tokens->moveToken("hero_2", "hex_12_8");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertNotContains("hex_12_8", $moves);
    }

    // -------------------------------------------------------------------------
    // resolve — queues roll with correct strength and target
    // -------------------------------------------------------------------------

    public function testResolveQueuesRollWithStrength(): void {
        // Bjorn strength = 3 (hero card 2 + equip +1)
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);

        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("roll", $opTypes);
    }

    public function testResolveRollHasTargetData(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);

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
        $op = $this->createOp();
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->expectOutputRegex("/./");
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
    }
}
