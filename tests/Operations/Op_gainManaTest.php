<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

final class Op_gainManaTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // setupGameTables seeds 1 mana on the starting ability; drain it so tests start from 0
        foreach (array_keys($this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_ability_1_3")) as $key) {
            $this->game->tokens->moveToken($key, "supply_crystal_green");
        }
    }

    private function getMana(string $cardId): int {
        return $this->countGreenCrystals($cardId);
    }

    public function testOnlyManaCardsAreTargets(): void {
        // Sure Shot I has mana field — should be a target
        $this->assertValidTarget("card_ability_1_3");
        // First Bow has no mana field — should not be a target
        $this->assertNotValidTarget("card_equip_1_15");
        // Hero card has no mana field — should not be a target
        $this->assertNotValidTarget("card_hero_1_1");
    }

    public function testResolveAdds1Mana(): void {
        $op = $this->op;
        $this->call_resolve("card_ability_1_3");
        $this->assertEquals(1, $this->getMana("card_ability_1_3"));
    }

    public function testResolveAdds2Mana(): void {
        $this->createOp("2gainMana");
        $this->call_resolve("card_ability_1_3");
        $this->assertEquals(2, $this->getMana("card_ability_1_3"));
    }

    public function testResolveTakesFromSupply(): void {
        $supplyBefore = $this->countGreenCrystals("supply_crystal_green");
        $this->createOp("2gainMana");
        $this->call_resolve("card_ability_1_3");
        $supplyAfter = $this->countGreenCrystals("supply_crystal_green");
        $this->assertEquals($supplyBefore - 2, $supplyAfter);
    }

    public function testPresetTargetReturnsOnlyThatTarget(): void {
        $this->createOp("2gainMana", ["target" => "card_ability_1_3"]);
        $this->assertValidTargetCount(1);
        $this->assertValidTarget("card_ability_1_3");
    }

    public function testManaStacksOnCard(): void {
        $op = $this->op;
        $this->call_resolve("card_ability_1_3");
        $op2 = $this->createOp("2gainMana");
        $op2->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);
        $this->assertEquals(3, $this->getMana("card_ability_1_3"));
    }
}
