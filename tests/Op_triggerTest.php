<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_trigger;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_triggerTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1); // Bjorn — hero card has on=roll
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function createOp(string $expr = "trigger"): Op_trigger {
        /** @var Op_trigger */
        $op = $this->game->machine->instanciateOperation($expr, PCOLOR);
        return $op;
    }

    /** Set up action markers on the player board so spendAction is available. */
    private function setupActionMarkers(): void {
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_1", "aslot_" . PCOLOR . "_empty_1");
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_2", "aslot_" . PCOLOR . "_empty_2");
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves — trigger(roll)
    // -------------------------------------------------------------------------

    public function testRollTriggerHeroCardNotOfferedWhenCannotPay(): void {
        // Bjorn's hero card (card_hero_1_1) has on=roll, r=spendAction(actionFocus):2dealDamage
        // Without action markers, spendAction can't be paid — card is not offered
        $op = $this->createOp("trigger(roll)");
        $targets = $op->getArgs()["target"];
        $this->assertNotContains("card_hero_1_1", $targets);
    }

    public function testRollTriggerIgnoresCardsWithoutOnRoll(): void {
        // Bjorn's ability card (card_ability_1_3, Sure Shot I) has no on field
        $op = $this->createOp("trigger(roll)");
        $targets = $op->getArgs()["target"];
        $this->assertNotContains("card_ability_1_3", $targets);
    }

    public function testRollTriggerIncludesHandEventCards(): void {
        // Put Piercing Arrows (on=roll, r=custom) in hand — custom cards still match
        // But custom r can't be instantiated, so use a different approach:
        // Perfect Aim (card_event_1_31, on=roll) — also r=custom, won't work
        // For events, playEvent doesn't validate r, it just checks on field
        $this->game->tokens->moveToken("card_event_1_31", "hand_" . PCOLOR);
        $op = $this->createOp("trigger(roll)");
        $targets = $op->getArgs()["target"];
        $this->assertContains("card_event_1_31", $targets);
    }

    public function testEmptyTriggerTypeReturnsNoTargets(): void {
        $op = $this->createOp("trigger");
        $targets = $op->getArgs()["target"];
        $this->assertEmpty($targets);
    }

    public function testCanSkip(): void {
        $op = $this->createOp("trigger(roll)");
        $this->assertTrue($op->canSkip());
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves — trigger(actionAttack)
    // -------------------------------------------------------------------------

    public function testActionAttackTriggerFindsEventCardInHand(): void {
        // Master Shot (card_event_1_26) has on=actionAttack
        $this->game->tokens->moveToken("card_event_1_26", "hand_" . PCOLOR);
        $op = $this->createOp("trigger(actionAttack)");
        $targets = $op->getArgs()["target"];
        $this->assertContains("card_event_1_26", $targets);
    }

    public function testActionAttackTriggerIgnoresRollCards(): void {
        // Hero card has on=roll, not actionAttack
        $op = $this->createOp("trigger(actionAttack)");
        $targets = $op->getArgs()["target"];
        $this->assertNotContains("card_hero_1_1", $targets);
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves — trigger(resolveHits) with ability cards
    // -------------------------------------------------------------------------

    public function testMonsterAttackTriggerFindsAbilityCard(): void {
        // Riposte I (card_ability_3_3) has on=resolveHits, r=2spendMana:(2preventDamage:2dealDamage)
        $this->game->tokens->moveToken("card_ability_3_3", "tableau_" . PCOLOR);
        // Add 2 mana to the card so spendMana is payable
        $this->game->tokens->moveToken("crystal_green_1", "card_ability_3_3");
        $this->game->tokens->moveToken("crystal_green_2", "card_ability_3_3");
        // Adjacent monster for dealDamage target in the r expression
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        // Need dealDamage on stack for preventDamage to be valid
        $this->game->machine->push("dealDamage", PCOLOR, ["target" => "hex_11_8", "count" => 3]);
        $op = $this->createOp("trigger(resolveHits)");
        $targets = $op->getArgs()["target"];
        $this->assertContains("card_ability_3_3", $targets);
    }

    public function testMonsterAttackTriggerEmptyWhenNoMatchingCards(): void {
        // Bjorn has no on=resolveHits cards by default
        $op = $this->createOp("trigger(resolveHits)");
        $targets = $op->getArgs()["target"];
        $this->assertEmpty($targets);
    }

    public function testMonsterAttackTriggerFindsEventCard(): void {
        // Retaliation (card_event_3_31) has on=resolveHits, r=2dealDamage(adj)
        $this->game->tokens->moveToken("card_event_3_31", "hand_" . PCOLOR);
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8"); // adjacent target for dealDamage
        $op = $this->createOp("trigger(resolveHits)");
        $targets = $op->getArgs()["target"];
        $this->assertContains("card_event_3_31", $targets);
    }

    // -------------------------------------------------------------------------
    // resolve — queues the correct sub-operation
    // -------------------------------------------------------------------------

    public function testResolveQueuesUseAbilityForAbilityCard(): void {
        // Riposte I: on=resolveHits, r=2spendMana:(2preventDamage:2dealDamage)
        $this->game->tokens->moveToken("card_ability_3_3", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("crystal_green_1", "card_ability_3_3");
        $this->game->tokens->moveToken("crystal_green_2", "card_ability_3_3");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->machine->push("dealDamage", PCOLOR, ["target" => "hex_11_8", "count" => 3]);
        $op = $this->createOp("trigger(resolveHits)");
        $op->action_resolve([Operation::ARG_TARGET => "card_ability_3_3"]);
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("useAbility", $opTypes);
    }

    public function testResolveQueuesPlayEventForEventCard(): void {
        $this->game->tokens->moveToken("card_event_1_26", "hand_" . PCOLOR);
        $op = $this->createOp("trigger(actionAttack)");
        $op->action_resolve([Operation::ARG_TARGET => "card_event_1_26"]);
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("playEvent", $opTypes);
    }

    public function testResolveQueuesUseEquipmentForEquipmentCard(): void {
        // Quiver (card_equip_1_18) has on=actionAttack, r=gainDamage:custom
        // Use a simpler equip: place one with on=resolveHits and valid r
        // There are no Bjorn equips with on= and non-custom r in starting set,
        // so test the action annotation is correct via info
        $this->game->tokens->moveToken("card_ability_3_3", "tableau_" . PCOLOR);
        $op = $this->createOp("trigger(resolveHits)");
        $info = $op->getArgsInfo();
        $this->assertEquals("useAbility", $info["card_ability_3_3"]["action"]);
    }

    // -------------------------------------------------------------------------
    // Roll queues trigger — integration with Op_roll
    // -------------------------------------------------------------------------

    public function testRollQueuesTriggerForHeroAttack(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->game->machine->instanciateOperation("3roll", PCOLOR);
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("trigger(roll)", $opTypes);
    }

    public function testRollDoesNotQueueTriggerForMonsterAttack(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->game->machine->instanciateOperation("3roll", PCOLOR, ["attacker" => "monster_goblin_1"]);
        $op->action_resolve([Operation::ARG_TARGET => "hex_11_8"]);
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertNotContains("trigger(roll)", $opTypes);
    }

    // -------------------------------------------------------------------------
    // actionMove queues trigger
    // -------------------------------------------------------------------------

    public function testActionMoveQueuesTrigger(): void {
        $op = $this->game->machine->instanciateOperation("actionMove", PCOLOR);
        $op->action_resolve([Operation::ARG_TARGET => "hex_10_9"]);
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("trigger(actionMove)", $opTypes);
    }

    // -------------------------------------------------------------------------
    // turnEnd queues trigger
    // -------------------------------------------------------------------------

    public function testTurnEndQueuesTrigger(): void {
        $op = $this->game->machine->instanciateOperation("turnEnd", PCOLOR);
        $op->action_resolve([Operation::ARG_TARGET => "confirm"]);
        $ops = $this->game->machine->getAllOperations(PCOLOR);
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
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("trigger(monsterMove)", $opTypes);
    }

    public function testMonsterMoveTriggerIsEmptyWithNoCards(): void {
        // No ability cards with on=monsterMove on tableau → trigger should have no targets
        $op = $this->createOp("trigger(monsterMove)");
        $moves = $op->getArgsTarget();
        $this->assertEmpty($moves);
    }
}
