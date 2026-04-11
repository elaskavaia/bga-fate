<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Operations\Op_useEquipment;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_useEquipmentTest extends AbstractOpTestCase {
    /** card_equip_1_19 = Leather Purse (hero 1, durability 3, r=gainDamage:2heal(adj)) */
    private string $cardId = "card_equip_1_19";

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("card_hero_1_1", $this->getPlayersTableau());
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // Add damage so heal(adj) is not void
        $this->game->effect_moveCrystals("hero_1", "red", 3, "hero_1");
    }

    public function testNoEquipmentReturnsEmpty(): void {
        $op = $this->op;
        $this->assertNoValidTargets();
    }

    public function testIsVoidWithNoEquipment(): void {
        $op = $this->op;
        $this->assertTrue($op->isVoid());
    }

    public function testLeatherPurseIsValidTarget(): void {
        $this->game->tokens->moveToken($this->cardId, $this->getPlayersTableau());
        $this->assertValidTarget($this->cardId);
    }

    public function testEmptyRCardSkipped(): void {
        // Bjorn's First Bow has r=""
        $this->game->tokens->moveToken("card_equip_1_15", $this->getPlayersTableau());
        $this->assertNotValidTarget("card_equip_1_15");
    }

    public function testNotVoidWithUsableCard(): void {
        $this->game->tokens->moveToken($this->cardId, $this->getPlayersTableau());
        $op = $this->op;
        $this->assertFalse($op->isVoid());
    }

    public function testMaxDurabilityReturnsError(): void {
        $this->game->tokens->moveToken($this->cardId, $this->getPlayersTableau());
        // Leather Purse has durability 3, fill it up
        $this->game->effect_moveCrystals("hero_1", "red", 3, $this->cardId);
        $this->assertNotValidTarget($this->cardId);
    }

    public function testResolveQueuesRExpression(): void {
        $this->game->tokens->moveToken($this->cardId, $this->getPlayersTableau());
        $op = $this->op;
        $this->call_resolve($this->cardId);
        // gainDamage:2heal(adj) should be queued — check machine has pending ops
        $pending = $this->game->machine->getTopOperations(PCOLOR);
        $this->assertNotEmpty($pending);
    }

    public function testEffectVoidReturnsError(): void {
        // Leather Purse r=gainDamage:2heal(adj) — remove all hero damage so heal is void
        $this->game->effect_moveCrystals("hero_1", "red", -3, "hero_1");
        $this->game->tokens->moveToken($this->cardId, $this->getPlayersTableau());
        $this->assertNotValidTarget($this->cardId);
    }

    public function testMultipleCardsOffered(): void {
        $this->game->tokens->moveToken($this->cardId, $this->getPlayersTableau()); // Leather Purse
        $this->game->tokens->moveToken("card_equip_1_17", $this->getPlayersTableau()); // Throwing Axes — r=gainDamage:3roll(adj), needs adjacent monster
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->assertValidTarget($this->cardId);
        $this->assertValidTarget("card_equip_1_17");
    }
}
