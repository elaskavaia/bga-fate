<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;

/**
 * Tests for Op_useCard — the unified base for useAbility, useEquipment, playEvent.
 * Tests shared behavior: candidate discovery, trigger filtering, preset targets,
 * resolve queueing, void detection.
 */
final class Op_useCardTest extends AbstractOpTestCase {
    /** card_ability_1_7 = Stitching I (hero 1, r=spendUse:(heal(adj)/repairCard)) */
    private string $abilityCard = "card_ability_1_7";
    /** card_equip_1_19 = Leather Purse (hero 1, durability 3, r=costDamage:2heal(adj)) */
    private string $equipCard = "card_equip_1_19";
    /** card_event_1_27 = Rest (r=2heal(self)) */
    private string $eventCard = "card_event_1_27";

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // Add damage so heal-based cards have valid targets
        $this->game->effect_moveCrystals("hero_1", "red", 3, "hero_1");
    }

    public function testNoCardsReturnsEmpty(): void {
        $this->assertNoValidTargets();
    }

    public function testIsVoidWithNoCards(): void {
        $this->assertTrue($this->op->isVoid());
    }

    public function testAbilityCardIsValidTarget(): void {
        $this->game->tokens->moveToken($this->abilityCard, $this->getPlayersTableau());
        $this->assertValidTarget($this->abilityCard);
    }

    public function testEquipCardIsValidTarget(): void {
        $this->game->tokens->moveToken($this->equipCard, $this->getPlayersTableau());
        $this->assertValidTarget($this->equipCard);
    }

    public function testEventCardInHandIsValidTarget(): void {
        $this->game->tokens->moveToken($this->eventCard, "hand_" . $this->owner);
        $this->assertValidTarget($this->eventCard);
    }

    public function testPassiveCardSkipped(): void {
        // Eagle Eye I has r=passive (empty effective r)
        $this->game->tokens->moveToken("card_ability_1_9", $this->getPlayersTableau());
        $this->assertNotValidTarget("card_ability_1_9");
    }

    public function testEmptyRCardSkipped(): void {
        // Bjorn's First Bow has r=""
        $this->game->tokens->moveToken("card_equip_1_15", $this->getPlayersTableau());
        $this->assertNotValidTarget("card_equip_1_15");
    }

    public function testNotVoidWithUsableCard(): void {
        $this->game->tokens->moveToken($this->abilityCard, $this->getPlayersTableau());
        $this->assertFalse($this->op->isVoid());
    }

    public function testResolveQueuesRExpression(): void {
        $this->game->tokens->moveToken($this->abilityCard, $this->getPlayersTableau());
        $this->call_resolve($this->abilityCard);
        $pending = $this->game->machine->getTopOperations(PCOLOR);
        $this->assertNotEmpty($pending);
    }

    public function testEffectVoidReturnsError(): void {
        // Stitching r=1heal(adj) — remove all hero damage so heal is void
        $this->game->effect_moveCrystals("hero_1", "red", -3, "hero_1");
        $this->game->tokens->moveToken($this->abilityCard, $this->getPlayersTableau());
        $this->assertNotValidTarget($this->abilityCard);
    }

    public function testPresetTargetReturnsDirectly(): void {
        $this->createOp("useCard", ["target" => $this->abilityCard]);
        $this->assertValidTargetCount(1);
        $this->assertValidTarget($this->abilityCard);
    }

    public function testCardWithoutTriggerCannotBeUsedTwice(): void {
        $this->game->tokens->moveToken($this->abilityCard, $this->getPlayersTableau());
        $this->assertValidTarget($this->abilityCard);
        $this->call_resolve($this->abilityCard);
        // Card::useCard queues r wrapped in confirm=true paygain — confirm it so spendUse runs
        $top = $this->game->machine->createTopOperationFromDbForOwner($this->owner);
        $this->assertNotNull($top);
        $top->action_resolve([Operation::ARG_TARGET => "1"]);
        $this->game->machine->dispatchAll();
        $this->createOp();
        $this->assertNotValidTarget($this->abilityCard);
    }

    public function testMixedCardTypesAllOffered(): void {
        $this->game->tokens->moveToken($this->abilityCard, $this->getPlayersTableau());
        $this->game->tokens->moveToken($this->equipCard, $this->getPlayersTableau());
        $this->game->tokens->moveToken($this->eventCard, "hand_" . $this->owner);
        $this->assertValidTarget($this->abilityCard);
        $this->assertValidTarget($this->equipCard);
        $this->assertValidTarget($this->eventCard);
    }
}
