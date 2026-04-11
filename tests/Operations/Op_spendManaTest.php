<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

final class Op_spendManaTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // setupGameTables already seeds 1 mana on card_ability_1_3; top up to 3 total.
        $this->game->effect_moveCrystals("hero_1", "green", 2, "card_ability_1_3");
    }

    private function getMana(string $cardId): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", $cardId));
    }

    public function testCardWithEnoughManaIsValid(): void {
        $this->op = $this->createOp(null, ["card" => "card_ability_1_3"]);
        $moves = $this->op->getPossibleMoves();
        $this->assertArrayHasKey("card_ability_1_3", $moves);
        $this->assertEquals(Material::RET_OK, $moves["card_ability_1_3"]["q"]);
    }

    public function testCardWithInsufficientManaIsNotApplicable(): void {
        $this->op = $this->createOp("4spendMana", ["card" => "card_ability_1_3"]);
        $moves = $this->op->getPossibleMoves();
        $this->assertArrayHasKey("card_ability_1_3", $moves);
        $this->assertEquals(Material::ERR_NOT_APPLICABLE, $moves["card_ability_1_3"]["q"]);
    }

    public function testNoCardReturnsEmpty(): void {
        $this->assertNoValidTargets();
    }

    public function testResolveRemoves1Mana(): void {
        $this->assertEquals(3, $this->getMana("card_ability_1_3"));
        $this->op = $this->createOp(null, ["card" => "card_ability_1_3"]);
        $this->call_resolve("card_ability_1_3");
        $this->assertEquals(2, $this->getMana("card_ability_1_3"));
    }

    public function testResolveRemoves3Mana(): void {
        $this->op = $this->createOp("3spendMana", ["card" => "card_ability_1_3"]);
        $this->call_resolve("card_ability_1_3");
        $this->assertEquals(0, $this->getMana("card_ability_1_3"));
    }

    public function testResolveReturnsToSupply(): void {
        $supplyBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", "supply_crystal_green"));
        $this->op = $this->createOp("2spendMana", ["card" => "card_ability_1_3"]);
        $this->call_resolve("card_ability_1_3");
        $supplyAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", "supply_crystal_green"));
        $this->assertEquals($supplyBefore + 2, $supplyAfter);
    }

    public function testInsufficientManaThrows(): void {
        $this->op = $this->createOp("4spendMana", ["card" => "card_ability_1_3"]);
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->call_resolve("card_ability_1_3");
    }

    // -------------------------------------------------------------------------
    // condition: grimheim
    // -------------------------------------------------------------------------

    public function testGrimheimConditionPassesInGrimheim(): void {
        $this->game->tokens->moveToken("hero_1", "hex_9_9"); // Grimheim
        $this->game->hexMap->invalidateOccupancy();
        $this->op = $this->createOp("2spendMana(grimheim)", ["card" => "card_ability_1_3"]);
        $this->assertEquals(0, $this->op->getErrorCode());
    }

    public function testGrimheimConditionFailsOutsideGrimheim(): void {
        // hero_1 is on hex_11_8 (not Grimheim)
        $this->op = $this->createOp("2spendMana(grimheim)", ["card" => "card_ability_1_3"]);
        $this->assertNotEquals(0, $this->op->getErrorCode());
    }

    public function testGrimheimConditionResolves(): void {
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
        $this->game->hexMap->invalidateOccupancy();
        $this->op = $this->createOp("2spendMana(grimheim)", ["card" => "card_ability_1_3"]);
        $this->call_resolve("card_ability_1_3");
        $this->assertEquals(1, $this->getMana("card_ability_1_3"));
    }

    public function testNoConditionAlwaysValid(): void {
        $this->op = $this->createOp("2spendMana", ["card" => "card_ability_1_3"]);
        $this->assertEquals(0, $this->op->getErrorCode());
    }
}
