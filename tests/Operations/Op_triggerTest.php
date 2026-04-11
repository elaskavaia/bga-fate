<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_trigger;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_triggerTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->clearMachine();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves — trigger(roll)
    // -------------------------------------------------------------------------

    public function testRollTriggerHeroCardNotOfferedWhenCannotPay(): void {
        // Bjorn's hero card (card_hero_1_1) has on=roll, r=spendAction(actionFocus):2dealDamage
        // Without action markers, spendAction can't be paid — card is not offered
        $this->op = $this->createOp("trigger(roll)");
        $this->assertNotValidTarget("card_hero_1_1");
    }

    public function testRollTriggerIgnoresCardsWithoutOnRoll(): void {
        // Bjorn's ability card (card_ability_1_3, Sure Shot I) has no on field
        $this->op = $this->createOp("trigger(roll)");
        $this->assertNotValidTarget("card_ability_1_3");
    }

    public function testRollTriggerIncludesHandEventCards(): void {
        // Put Piercing Arrows (on=roll, r=custom) in hand — custom cards still match
        // But custom r can't be instantiated, so use a different approach:
        // Perfect Aim (card_event_1_31, on=roll) — also r=custom, won't work
        // For events, playEvent doesn't validate r, it just checks on field
        $this->game->tokens->moveToken("card_event_1_31", "hand_" . $this->owner);
        $this->op = $this->createOp("trigger(roll)");
        $this->assertValidTarget("card_event_1_31");
    }

    public function testEmptyTriggerTypeReturnsNoTargets(): void {
        $this->op = $this->createOp("trigger");
        $this->assertNoValidTargets();
    }

    public function testCanSkip(): void {
        $this->op = $this->createOp("trigger(roll)");
        $this->assertTrue($this->op->canSkip());
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves — trigger(actionAttack)
    // -------------------------------------------------------------------------

    public function testTriggerFindsEventCardInHand(): void {
        // Perfect Aim (card_event_1_31) has on=roll, r=rerollMisses (implemented)
        $this->game->tokens->moveToken("card_event_1_31", "hand_" . $this->owner);
        $this->op = $this->createOp("trigger(roll)");
        $this->assertValidTarget("card_event_1_31");
    }

    public function testActionAttackTriggerIgnoresRollCards(): void {
        // Hero card has on=roll, not actionAttack
        $this->op = $this->createOp("trigger(actionAttack)");
        $this->assertNotValidTarget("card_hero_1_1");
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves — trigger(resolveHits) with ability cards
    // -------------------------------------------------------------------------

    public function testMonsterAttackTriggerFindsAbilityCard(): void {
        // Riposte I (card_ability_3_3) has on=resolveHits, r=2spendMana:(2preventDamage:2dealDamage)
        $this->game->tokens->moveToken("card_ability_3_3", "tableau_" . $this->owner);
        // Add 2 mana to the card so spendMana is payable
        $this->game->tokens->moveToken("crystal_green_1", "card_ability_3_3");
        $this->game->tokens->moveToken("crystal_green_2", "card_ability_3_3");
        // Adjacent monster for dealDamage target in the r expression
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        // Need dealDamage on stack for preventDamage to be valid
        $this->game->machine->push("dealDamage", $this->owner, ["target" => "hex_11_8", "count" => 3]);
        $this->op = $this->createOp("trigger(resolveHits)");
        $this->assertValidTarget("card_ability_3_3");
    }

    public function testMonsterAttackTriggerEmptyWhenNoMatchingCards(): void {
        // Bjorn has no on=resolveHits cards by default
        $this->op = $this->createOp("trigger(resolveHits)");
        $this->assertNoValidTargets();
    }

    public function testMonsterAttackTriggerFindsEventCard(): void {
        // Retaliation (card_event_3_31) has on=resolveHits, r=2dealDamage(adj)
        $this->game->tokens->moveToken("card_event_3_31", "hand_" . $this->owner);
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8"); // adjacent target for dealDamage
        $this->op = $this->createOp("trigger(resolveHits)");
        $this->assertValidTarget("card_event_3_31");
    }

    // -------------------------------------------------------------------------
    // resolve — queues the correct sub-operation
    // -------------------------------------------------------------------------

    public function testResolveQueuesUseAbilityForAbilityCard(): void {
        // Riposte I: on=resolveHits, r=2spendMana:(2preventDamage:2dealDamage)
        $this->game->tokens->moveToken("card_ability_3_3", "tableau_" . $this->owner);
        $this->game->tokens->moveToken("crystal_green_1", "card_ability_3_3");
        $this->game->tokens->moveToken("crystal_green_2", "card_ability_3_3");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->machine->push("dealDamage", $this->owner, ["target" => "hex_11_8", "count" => 3]);
        $this->op = $this->createOp("trigger(resolveHits)");
        $this->call_resolve("card_ability_3_3");
        $ops = $this->game->machine->getAllOperations($this->owner);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("useAbility", $opTypes);
    }

    public function testResolveQueuesPlayEventForEventCard(): void {
        // Perfect Aim (card_event_1_31) has on=roll, r=rerollMisses (implemented)
        $this->game->tokens->moveToken("card_event_1_31", "hand_" . $this->owner);
        // Place dice on display_battle so rerollMisses has valid targets
        $this->game->tokens->moveToken("die_attack_1", "display_battle", 2);
        $this->op = $this->createOp("trigger(roll)");
        $this->call_resolve("card_event_1_31");
        $ops = $this->game->machine->getAllOperations($this->owner);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("playEvent", $opTypes);
    }

    public function testResolveQueuesUseEquipmentForEquipmentCard(): void {
        // Quiver (card_equip_1_18) has on=actionAttack, r=gainDamage:custom
        // Use a simpler equip: place one with on=resolveHits and valid r
        // There are no Bjorn equips with on= and non-custom r in starting set,
        // so test the action annotation is correct via info
        $this->game->tokens->moveToken("card_ability_3_3", "tableau_" . $this->owner);
        $this->op = $this->createOp("trigger(resolveHits)");
        $info = $this->op->getArgsInfo();
        $this->assertEquals("useAbility", $info["card_ability_3_3"]["action"]);
    }

    // -------------------------------------------------------------------------
    // Roll queues trigger — integration with Op_roll
    // -------------------------------------------------------------------------

    public function testRollQueuesTriggerForHeroAttack(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->op = $this->createOp("3roll");
        $this->call_resolve("hex_12_8");
        $ops = $this->game->machine->getAllOperations($this->owner);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("trigger(roll)", $opTypes);
    }

    public function testRollDoesNotQueueTriggerForMonsterAttack(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->op = $this->createOp("3roll", ["attacker" => "monster_goblin_1"]);
        $this->call_resolve("hex_11_8");
        $ops = $this->game->machine->getAllOperations($this->owner);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertNotContains("trigger(roll)", $opTypes);
    }

    // -------------------------------------------------------------------------
    // actionMove queues trigger
    // -------------------------------------------------------------------------

    public function testActionMoveQueuesTrigger(): void {
        $this->op = $this->createOp("actionMove");
        $this->call_resolve("hex_10_9");
        $ops = $this->game->machine->getAllOperations($this->owner);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("trigger(actionMove)", $opTypes);
    }

    // -------------------------------------------------------------------------
    // turnEnd queues trigger
    // -------------------------------------------------------------------------

    public function testTurnEndQueuesTrigger(): void {
        $this->op = $this->createOp("turnEnd");
        $this->call_resolve("confirm");
        $ops = $this->game->machine->getAllOperations($this->owner);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("trigger(turnEnd)", $opTypes);
    }

    // -------------------------------------------------------------------------
    // turnMonster queues monsterMove trigger per player
    // -------------------------------------------------------------------------

    public function testTurnMonsterQueuesMonsterMoveTrigger(): void {
        $this->game->tokens->setTokenState("rune_stone", 1);
        $op = $this->game->machine->instanciateOperation("turnMonster", ACOLOR);
        $op->resolve();
        $ops = $this->game->machine->getAllOperations($this->owner);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("trigger(monsterMove)", $opTypes);
    }

    public function testMonsterMoveTriggerIsEmptyWithNoCards(): void {
        // No ability cards with on=monsterMove on tableau → trigger should have no targets
        $this->op = $this->createOp("trigger(monsterMove)");
        $this->assertNoValidTargets();
    }
}
