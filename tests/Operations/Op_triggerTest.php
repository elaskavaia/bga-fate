<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_trigger;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Op_trigger.
 *
 * Op_trigger is a pure dispatcher: walks tableau + hand cards of the active
 * player, instantiates a Card object for each, and calls onTrigger($triggerType).
 * Per-card matching/queueing logic lives in Card and CardGeneric (see
 * tests/Model/CardGenericTest.php). The tests here cover:
 *
 * 1. Fire sites — verify that operations like Op_roll, Op_actionMove, Op_turnEnd,
 *    Op_turnMonster correctly queue trigger(<type>) ops at the right moments.
 * 2. Dispatcher — verify Op_trigger walks both tableau and hand and calls
 *    onTrigger() for each card it visits.
 */
final class Op_triggerTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->clearMachine();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    // -------------------------------------------------------------------------
    // Dispatcher — Op_trigger walks tableau + hand and calls onTrigger per card
    // -------------------------------------------------------------------------

    /**
     * Tableau card with matching `on` field → CardGeneric default queues a useAbility op.
     * This proves Op_trigger::resolve() instantiated the card and called onTrigger() on it.
     */
    public function testDispatcherInstantiatesTableauCardAndQueuesUseAbility(): void {
        // Riposte I (card_ability_3_3) on=resolveHits, r=2spendMana:(2preventDamage:2dealDamage)
        // Set up so the card is actually playable, then verify CardGeneric queues useAbility.
        $this->game->tokens->moveToken("card_ability_3_3", $this->getPlayersTableau());
        $this->game->tokens->moveToken("crystal_green_1", "card_ability_3_3");
        $this->game->tokens->moveToken("crystal_green_2", "card_ability_3_3");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->machine->push("dealDamage", $this->owner, ["target" => "hex_11_8", "count" => 3]);
        $this->createOp("trigger(resolveHits)");
        $this->call_resolve();
        $opTypes = array_map(fn($o) => $o["type"], $this->game->machine->getAllOperations($this->owner));
        $this->assertContains("useAbility", $opTypes);
    }

    /**
     * Hand event card with matching `on` field → CardGeneric default queues a playEvent op.
     * Covers the hand-walking branch of the dispatcher.
     */
    public function testDispatcherWalksHandAndQueuesPlayEvent(): void {
        // Perfect Aim (card_event_1_31) has on=roll, lives in hand.
        $this->game->tokens->moveToken("card_event_1_31", "hand_" . $this->owner);
        $this->createOp("trigger(roll)");
        $this->call_resolve();
        $opTypes = array_map(fn($o) => $o["type"], $this->game->machine->getAllOperations($this->owner));
        $this->assertContains("playEvent", $opTypes);
    }

    /**
     * Trigger type that no card on the tableau reacts to → no action queued.
     * Proves the matching logic in CardGeneric works for the negative case.
     */
    public function testDispatcherSkipsNonMatchingCards(): void {
        // Riposte I has on=resolveHits; no Bjorn starting card has on=monsterKilled.
        $this->game->tokens->moveToken("card_ability_3_3", $this->getPlayersTableau());
        $this->createOp("trigger(monsterKilled)");
        $this->call_resolve();
        $opTypes = array_map(fn($o) => $o["type"], $this->game->machine->getAllOperations($this->owner));
        $this->assertNotContains("useAbility", $opTypes);
        $this->assertNotContains("playEvent", $opTypes);
    }

    // -------------------------------------------------------------------------
    // Roll queues trigger — integration with Op_roll
    // -------------------------------------------------------------------------

    public function testRollQueuesTriggerForHeroAttack(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->createOp("3roll");
        $this->call_resolve("hex_12_8");
        $ops = $this->game->machine->getAllOperations($this->owner);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("trigger(roll)", $opTypes);
    }

    public function testRollDoesNotQueueTriggerForMonsterAttack(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->createOp("3roll", ["attacker" => "monster_goblin_1"]);
        $this->call_resolve("hex_11_8");
        $ops = $this->game->machine->getAllOperations($this->owner);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertNotContains("trigger(roll)", $opTypes);
    }

    // -------------------------------------------------------------------------
    // actionMove queues trigger
    // -------------------------------------------------------------------------

    public function testActionMoveQueuesTrigger(): void {
        $this->createOp("actionMove");
        $this->call_resolve("hex_10_9");
        $ops = $this->game->machine->getAllOperations($this->owner);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("trigger(actionMove)", $opTypes);
    }

    // -------------------------------------------------------------------------
    // turnEnd queues trigger
    // -------------------------------------------------------------------------

    public function testTurnEndQueuesTrigger(): void {
        $this->createOp("turnEnd");
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
}
