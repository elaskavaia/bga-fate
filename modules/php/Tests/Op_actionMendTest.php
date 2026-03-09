<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_actionMend;
use Bga\Games\Fate\Tests\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_actionMendTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        // Assign hero 1 (Bjorn) to PCOLOR
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function createOp(): Op_actionMend {
        /** @var Op_actionMend */
        $op = $this->game->machine->instanciateOperation("actionMend", PCOLOR);
        return $op;
    }

    private function addDamage(int $amount): void {
        $this->game->effect_moveCrystals("hero_1", "red", $amount, "hero_1", ["message" => ""]);
    }

    private function getQueuedOp(): ?array {
        $ops = $this->game->machine->getTopOperations(PCOLOR);
        return $ops ? reset($ops) : null;
    }

    public function testMendQueuesHeal2(): void {
        $this->addDamage(4);
        $op = $this->createOp();
        $op->resolve();
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("2heal", $queued["type"]);
    }

    public function testMendQueuesHeal5InGrimheim(): void {
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
        $this->addDamage(5);
        $op = $this->createOp();
        $op->resolve();
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("5heal", $queued["type"]);
    }

    public function testMendNotAvailableWithZeroDamage(): void {
        $op = $this->createOp();
        $this->assertEquals(Material::ERR_NOT_APPLICABLE, $op->getErrorCode());
    }

    public function testMendAvailableWithDamage(): void {
        $this->addDamage(2);
        $op = $this->createOp();
        $this->assertEquals(Material::RET_OK, $op->getErrorCode());
    }
}
