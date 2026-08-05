<?php

declare(strict_types=1);

use Bga\Games\Fate\Model\Trigger;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Tests for Op_useCard — the unified base for useAbility, useEquipment, playEvent.
 * Tests shared behavior: candidate discovery, trigger filtering, preset targets,
 * resolve queueing, void detection.
 */
final class Op_useCardTest extends AbstractOpTestCase {
    /** card_ability_1_7 = Stitching I (hero 1, r=spendUse:(heal(adj)/repairCard)) */
    private string $abilityCard = "card_ability_1_7";
    /** card_equip_1_19 = Leather Purse (hero 1, durability 3, r=spendDurab:2heal(adj)) */
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

    // -------------------------------------------------------------------------
    // Card::promptUseCard merging (BGA #235866 investigation)
    // -------------------------------------------------------------------------

    /** Queue a pending useCard prompt directly, then fire a later trigger through Card::promptUseCard. */
    private function promptOverPending(array $data, Trigger $event): array {
        $this->game->tokens->moveToken($this->abilityCard, $this->getPlayersTableau());
        $this->game->machine->queue("useCard", $this->owner, $data);

        $frame = $this->game->machine->instantiateOperation("nop", $this->owner, []);
        $this->game->instantiateCard($this->abilityCard, $frame)->promptUseCard($event);

        return array_map(
            fn($op) => ["on" => $op->getDataField("on", []), "excluded" => $op->getDataField("excluded", [])],
            $this->game->machine->findOperationOps($this->owner, "useCard")
        );
    }

    /**
     * Op_useCard::resolve re-queues itself with an `excluded` list after a non-Manual play, so a
     * pending useCard carrying `excluded` is the tail of a prompt the player already answered.
     * A later trigger must get its own prompt - merged into the tail it would be offered under
     * the stale event and Op_or would grey out the branch gated on the new one.
     */
    public function testAnsweredPromptDoesNotSwallowALaterTrigger(): void {
        $ops = $this->promptOverPending(
            ["l_confirm" => true, "on" => ["TActionAttack"], "excluded" => [$this->abilityCard]],
            Trigger::MonsterKilled
        );

        $this->assertCount(2, $ops, "the kill must get its own prompt");
        $this->assertEquals(["on" => ["TMonsterKilled"], "excluded" => []], $ops[0], "the new moment is offered first");
        $this->assertContains(["on" => ["TActionAttack"], "excluded" => [$this->abilityCard]], $ops, "answered prompt untouched");
    }

    /**
     * The merge itself must stay: two triggers firing before the player answers anything
     * produce ONE prompt, not two.
     */
    public function testUnansweredPromptStillMergesASecondTrigger(): void {
        $ops = $this->promptOverPending(["l_confirm" => true, "on" => ["TActionAttack"]], Trigger::MonsterKilled);

        $this->assertCount(1, $ops, "both triggers share one prompt");
        $this->assertEquals(["TActionAttack", "TMonsterKilled"], $ops[0]["on"]);
    }
}
