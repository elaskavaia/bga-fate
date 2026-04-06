<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class OperationTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->tokens->moveToken("card_equip_1_21", "tableau_" . PCOLOR);
    }

    private function addDamage(string $tokenId, int $amount): void {
        $this->game->effect_moveCrystals($tokenId, "red", $amount, $tokenId, ["message" => ""]);
    }

    // -------------------------------------------------------------------------
    // getPossibleMovesDelegate — single operation
    // -------------------------------------------------------------------------

    public function testDelegateSingleWithTargets(): void {
        $this->addDamage("hero_1", 3);
        $op = $this->game->machine->instanciateOperation("actionMend", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_11_8", $moves);
        $this->assertEquals(Material::RET_OK, $moves["hex_11_8"]["q"]);
        $this->assertEquals("2heal", $moves["hex_11_8"]["delegate"]);
    }

    public function testDelegateSingleNoTargetsHasError(): void {
        // No damage → heal targets present but not applicable
        $op = $this->game->machine->instanciateOperation("actionMend", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_11_8", $moves);
        $this->assertNotEquals(Material::RET_OK, $moves["hex_11_8"]["q"]);
        $this->assertEquals("2heal", $moves["hex_11_8"]["delegate"]);
    }

    // -------------------------------------------------------------------------
    // getPossibleMovesDelegate — multiple operations
    // -------------------------------------------------------------------------

    public function testDelegateMultiMergesTargets(): void {
        // In Grimheim: both heal and repairCard delegates
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
        $this->addDamage("hero_1", 2);
        $this->addDamage("card_equip_1_21", 1);
        $op = $this->game->machine->instanciateOperation("actionMend", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_9_9", $moves);
        $this->assertArrayHasKey("card_equip_1_21", $moves);
        $this->assertEquals("5heal", $moves["hex_9_9"]["delegate"]);
        $this->assertEquals("5repairCard", $moves["card_equip_1_21"]["delegate"]);
    }

    public function testDelegateMultiPartialTargets(): void {
        // In Grimheim: hero has damage, equipment does not
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
        $this->addDamage("hero_1", 2);
        $op = $this->game->machine->instanciateOperation("actionMend", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_9_9", $moves);
        // Equipment has no damage → still present but not applicable
        $this->assertArrayHasKey("card_equip_1_21", $moves);
        $this->assertNotEquals(Material::RET_OK, $moves["card_equip_1_21"]["q"]);
    }

    public function testDelegateMultiNoTargetsAllHaveErrors(): void {
        // In Grimheim: no damage anywhere → targets present but all non-applicable
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
        $op = $this->game->machine->instanciateOperation("actionMend", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_9_9", $moves);
        $this->assertNotEquals(Material::RET_OK, $moves["hex_9_9"]["q"]);
        $this->assertArrayHasKey("card_equip_1_21", $moves);
        $this->assertNotEquals(Material::RET_OK, $moves["card_equip_1_21"]["q"]);
    }
}
