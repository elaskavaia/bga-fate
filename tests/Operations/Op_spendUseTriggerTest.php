<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;

/**
 * Tests for Op_spendUseAny - spendUse that also works in trigger context.
 */
final class Op_spendUseAnyTest extends AbstractOpTestCase {
    private string $cardId = "card_ability_1_3"; // Sure Shot I, on Bjorn's starting tableau

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    public function testValidInTriggerContext(): void {
        $this->createOp(null, ["card" => $this->cardId, "event" => "TActionAttack"]);
        $this->assertValidTarget($this->cardId);
    }

    public function testValidInManualContext(): void {
        $this->createOp(null, ["card" => $this->cardId, "event" => "TManual"]);
        $this->assertValidTarget($this->cardId);
    }

    public function testSharesUsedFlagWithSpendUse(): void {
        $this->createOp(null, ["card" => $this->cardId, "event" => "TActionAttack"]);
        $this->call_resolve($this->cardId);
        $this->createOp(null, ["card" => $this->cardId, "event" => "TActionAttack"]);
        $this->assertTargetError($this->cardId, Material::ERR_OCCUPIED);
        $this->createOp("spendUse", ["card" => $this->cardId]);
        $this->assertTargetError($this->cardId, Material::ERR_OCCUPIED);
    }
}
