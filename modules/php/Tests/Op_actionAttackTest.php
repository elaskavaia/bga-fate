<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\Operations\Op_actionAttack;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Tests\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_actionAttackTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        // Assign hero 1 (Bjorn) to PCOLOR: strength 2, starting ability (Sure Shot, no +str), starting equip (+1)
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_ability_1_3", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_equip_1_15", "tableau_" . PCOLOR);
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
        // Embla (hero_3) has Flimsy Blade (no attack_range), so range=1
        $this->game->tokens->moveToken("card_hero_3_1", "tableau_" . BCOLOR);
        $this->game->tokens->moveToken("card_equip_3_15", "tableau_" . BCOLOR);
        $this->game->tokens->moveToken("hero_3", "hex_12_5");

        // hex_13_7 is more than 1 hex from hex_12_5 — out of range
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");

        /** @var Op_actionAttack */
        $op = $this->game->machine->instanciateOperation("actionAttack", BCOLOR);
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
        $this->assertContains("3roll", $opTypes);
    }

    public function testResolveRollHasTargetData(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);

        $ops = $this->game->machine->getAllOperations(PCOLOR);
        foreach ($ops as $o) {
            if (str_contains($o["type"], "roll")) {
                $data = is_string($o["data"]) ? json_decode($o["data"], true) : ($o["data"] ?? []);
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
