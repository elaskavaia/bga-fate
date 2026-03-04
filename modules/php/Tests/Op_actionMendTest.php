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

    private function getDamage(): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1"));
    }

    public function testMendRemovesTwoDamage(): void {
        $this->addDamage(4);
        $this->assertEquals(4, $this->getDamage());

        $op = $this->createOp();
        $op->action_resolve([]);

        $this->assertEquals(2, $this->getDamage());
    }

    public function testMendRemovesFiveInGrimheim(): void {
        // Move hero to Grimheim
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
        $this->addDamage(5);
        $this->assertEquals(5, $this->getDamage());

        $op = $this->createOp();
        $op->action_resolve([]);

        $this->assertEquals(0, $this->getDamage());
    }

    public function testMendCapsAtCurrentDamage(): void {
        // Hero has only 1 damage, mend should remove only 1
        $this->addDamage(1);
        $this->assertEquals(1, $this->getDamage());

        $op = $this->createOp();
        $op->action_resolve([]);

        $this->assertEquals(0, $this->getDamage());
    }

    public function testMendNotAvailableWithZeroDamage(): void {
        $this->assertEquals(0, $this->getDamage());

        $op = $this->createOp();
        $this->assertEquals(Material::ERR_PREREQ, $op->getErrorCode());
    }

    public function testMendAvailableWithDamage(): void {
        $this->addDamage(2);

        $op = $this->createOp();
        $this->assertEquals(Material::RET_OK, $op->getErrorCode());
    }
}
