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

    // -------------------------------------------------------------------------
    // ?gainMana — optional form used when the gain may have no valid target
    // (e.g. Alva Hero I/II). Behaviour:
    //   - 0 targets → void-but-skippable, auto() takes the skip path
    //   - 1 target  → auto-resolves on that target without prompting
    //   - 2+ targets → real choice, user must pick
    // -------------------------------------------------------------------------

    public function testOptionalGainManaWithNoTargetsAutoSkips(): void {
        // Drain the only mana card so the tableau has zero mana-target cards.
        $this->game->tokens->moveToken("card_ability_1_3", "limbo");
        $op = $this->createOp("?gainMana");
        $this->assertTrue($op->noValidTargets(), "no mana cards → no targets");
        $this->assertTrue($op->canSkip(), "? prefix makes the op optional");
        $this->assertFalse($op->isVoid(), "optional + no targets is not void (skip path)");
        $this->assertTrue($op->canResolveAutomatically(), "should auto-skip");
        // auto() should not throw and should leave no side effect
        $manaBefore = $this->getMana("card_ability_1_3");
        $op->auto();
        $this->assertEquals($manaBefore, $this->getMana("card_ability_1_3"));
    }

    public function testOptionalGainManaWithOneTargetAutoResolves(): void {
        // Sure Shot I (card_ability_1_3, mana=1) is the only card with a mana field on Bjorn's
        // starting tableau → the sole valid target → ?gainMana auto-picks it.
        $op = $this->createOp("?gainMana");
        $this->assertEquals(1, count($op->getArgsTarget()));
        $this->assertTrue($op->canResolveAutomatically(), "single target should auto-resolve even with skip flag");
        $op->auto();
        $this->assertEquals(1, $this->getMana("card_ability_1_3"));
    }

    public function testOptionalGainManaWithMultipleTargetsAsksUser(): void {
        // Add Sure Shot II (also has mana) to the tableau → 2 candidates, real choice.
        $this->game->tokens->moveToken("card_ability_1_4", $this->getPlayersTableau());
        $op = $this->createOp("?gainMana");
        $this->assertEquals(2, count($op->getArgsTarget()));
        $this->assertFalse(
            $op->canResolveAutomatically(),
            "with multiple valid targets the user must pick — auto-resolve must be disabled"
        );
    }
}
