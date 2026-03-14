<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Operations\Op_spendMana;
use Bga\Games\Fate\Tests\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_spendManaTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        // Assign hero 1 (Bjorn) to PCOLOR
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_ability_1_3", "tableau_" . PCOLOR); // Sure Shot I
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // Add 3 mana to Sure Shot I
        $this->game->effect_moveCrystals("hero_1", "green", 3, "card_ability_1_3");
    }

    private function createOp(string $expr = "spendMana", array $data = []): Op_spendMana {
        if (!isset($data["card"])) {
            $data["card"] = "card_ability_1_3";
        }
        /** @var Op_spendMana */
        $op = $this->game->machine->instanciateOperation($expr, PCOLOR, $data);
        return $op;
    }

    private function getMana(string $cardId): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", $cardId));
    }

    public function testCardWithEnoughManaIsValid(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("card_ability_1_3", $moves);
        $this->assertEquals(Material::RET_OK, $moves["card_ability_1_3"]["q"]);
    }

    public function testCardWithInsufficientManaIsNotApplicable(): void {
        $op = $this->createOp("4spendMana");
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("card_ability_1_3", $moves);
        $this->assertEquals(Material::ERR_NOT_APPLICABLE, $moves["card_ability_1_3"]["q"]);
    }

    public function testNoCardReturnsEmpty(): void {
        $op = $this->game->machine->instanciateOperation("spendMana", PCOLOR, []);
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    public function testResolveRemoves1Mana(): void {
        $this->assertEquals(3, $this->getMana("card_ability_1_3"));
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);
        $this->assertEquals(2, $this->getMana("card_ability_1_3"));
    }

    public function testResolveRemoves3Mana(): void {
        $op = $this->createOp("3spendMana");
        $op->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);
        $this->assertEquals(0, $this->getMana("card_ability_1_3"));
    }

    public function testResolveReturnsToSupply(): void {
        $supplyBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", "supply_crystal_green"));
        $op = $this->createOp("2spendMana");
        $op->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);
        $supplyAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", "supply_crystal_green"));
        $this->assertEquals($supplyBefore + 2, $supplyAfter);
    }

    public function testInsufficientManaThrows(): void {
        $op = $this->createOp("4spendMana");
        $this->expectException(\Bga\GameFramework\UserException::class);
        $op->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);
    }
}
