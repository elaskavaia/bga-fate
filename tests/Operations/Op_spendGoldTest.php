<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;

final class Op_spendGoldTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        // Seed 3 yellow crystals ("arrows") on the context card.
        $this->game->effect_moveCrystals("hero_1", "yellow", 3, "card_ability_1_3");
    }

    private function getGold(string $cardId): int {
        return $this->countYellowCrystals($cardId);
    }

    public function testCardWithEnoughGoldIsValid(): void {
        $this->op = $this->createOp(null, ["card" => "card_ability_1_3"]);
        $this->assertValidTarget("card_ability_1_3");
    }

    public function testCardWithInsufficientGoldIsNotApplicable(): void {
        $this->op = $this->createOp("4spendGold", ["card" => "card_ability_1_3"]);
        $this->assertTargetError("card_ability_1_3", Material::ERR_NOT_APPLICABLE);
    }

    public function testNoCardReturnsEmpty(): void {
        $this->assertNoValidTargets();
    }

    public function testResolveRemoves1Gold(): void {
        $this->assertEquals(3, $this->getGold("card_ability_1_3"));
        $this->op = $this->createOp(null, ["card" => "card_ability_1_3"]);
        $this->call_resolve("card_ability_1_3");
        $this->assertEquals(2, $this->getGold("card_ability_1_3"));
    }

    public function testResolveRemoves3Gold(): void {
        $this->op = $this->createOp("3spendGold", ["card" => "card_ability_1_3"]);
        $this->call_resolve("card_ability_1_3");
        $this->assertEquals(0, $this->getGold("card_ability_1_3"));
    }

    public function testResolveReturnsToSupply(): void {
        $supplyBefore = $this->countYellowCrystals("supply_crystal_yellow");
        $this->op = $this->createOp("2spendGold", ["card" => "card_ability_1_3"]);
        $this->call_resolve("card_ability_1_3");
        $supplyAfter = $this->countYellowCrystals("supply_crystal_yellow");
        $this->assertEquals($supplyBefore + 2, $supplyAfter);
    }

    public function testInsufficientGoldThrows(): void {
        $this->op = $this->createOp("4spendGold", ["card" => "card_ability_1_3"]);
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->call_resolve("card_ability_1_3");
    }
}
