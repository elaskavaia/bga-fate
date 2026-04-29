<?php

declare(strict_types=1);

use Bga\Games\Fate\Stubs\GameUT;

/**
 * Tests for Op_trigger.
 *
 * Op_trigger is a pure dispatcher: walks tableau + hand cards of the active
 * player, instantiates a Card object for each, and calls onTrigger($triggerName).
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
     * Tableau card with matching `on` field → CardGeneric default queues a useCard op.
     * This proves Op_trigger::resolve() instantiated the card and called onTrigger() on it.
     */
    public function testDispatcherInstantiatesTableauCardAndQueuesUseCard(): void {
        // Riposte I (card_ability_3_3) on=resolveHits, r=2spendMana:(2preventDamage:2dealDamage)
        // Set up so the card is actually playable, then verify CardGeneric queues useCard.
        $this->game->tokens->moveToken("card_ability_3_3", $this->getPlayersTableau());
        $this->game->tokens->moveToken("crystal_green_1", "card_ability_3_3");
        $this->game->tokens->moveToken("crystal_green_2", "card_ability_3_3");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->machine->push("dealDamage", $this->owner, ["target" => "hex_11_8", "count" => 3]);
        $this->createOp("trigger(TResolveHits)");
        $this->call_resolve();
        $opTypes = array_map(fn($o) => $o["type"], $this->game->machine->getAllOperations($this->owner));
        $this->assertContains("useCard", $opTypes);
    }

    /**
     * Hand event card with matching `on` field → CardGeneric default queues a useCard op.
     * Covers the hand-walking branch of the dispatcher.
     */
    public function testDispatcherWalksHandAndQueuesPlayEvent(): void {
        // Perfect Aim (card_event_1_31) has on=roll, lives in hand.
        $this->game->tokens->moveToken("card_event_1_31", "hand_" . $this->owner);
        $this->createOp("trigger(TRoll)");
        $this->call_resolve();
        $opTypes = array_map(fn($o) => $o["type"], $this->game->machine->getAllOperations($this->owner));
        $this->assertContains("useCard", $opTypes);
    }

    /**
     * Trigger type that no card on the tableau reacts to → no action queued.
     * Proves the matching logic in CardGeneric works for the negative case.
     */
    public function testDispatcherSkipsNonMatchingCards(): void {
        // Riposte I has on=resolveHits; no Bjorn starting card has on=monsterKilled.
        $this->game->tokens->moveToken("card_ability_3_3", $this->getPlayersTableau());
        $this->createOp("trigger(TMonsterKilled)");
        $this->call_resolve();
        $opTypes = array_map(fn($o) => $o["type"], $this->game->machine->getAllOperations($this->owner));
        $this->assertNotContains("useCard", $opTypes);
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
        $this->assertContains("trigger(TRoll)", $opTypes);
    }

    public function testRollDoesNotQueueTriggerForMonsterAttack(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->createOp("3roll", ["attacker" => "monster_goblin_1"]);
        $this->call_resolve("hex_11_8");
        $ops = $this->game->machine->getAllOperations($this->owner);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertNotContains("trigger(TRoll)", $opTypes);
    }

    // -------------------------------------------------------------------------
    // actionMove queues trigger
    // -------------------------------------------------------------------------

    public function testActionMoveQueuesTrigger(): void {
        // Op_actionMove delegates to Op_move; Op_move's resolve() emits
        // Trigger::ActionMove when getReason() == "Op_actionMove" (chains through
        // Move). Invoke the move op directly with that reason to verify.
        $this->createOp("move", ["reason" => "Op_actionMove"]);
        $this->call_resolve("hex_10_9");
        $this->dispatchOne();
        $ops = $this->game->machine->getAllOperations($this->owner);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("trigger(TActionMove)", $opTypes);
    }

    public function testPlainMoveQueuesMoveTrigger(): void {
        // Op_move without an Op_actionMove reason emits the plain Move trigger.
        $this->createOp("move", ["reason" => "card_event_1_34_1"]);
        $this->call_resolve("hex_10_9");
        $this->dispatchOne();
        $ops = $this->game->machine->getAllOperations($this->owner);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("trigger(TMove)", $opTypes);
        $this->assertNotContains("trigger(TActionMove)", $opTypes);
    }

    // -------------------------------------------------------------------------
    // turnEnd queues trigger
    // -------------------------------------------------------------------------

    public function testTurnEndQueuesTrigger(): void {
        $this->createOp("turnEnd");
        $this->call_resolve("confirm");
        $ops = $this->game->machine->getAllOperations($this->owner);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("trigger(TTurnEnd)", $opTypes);
    }

    // -------------------------------------------------------------------------
    // turnMonster queues monsterMove trigger per player
    // -------------------------------------------------------------------------

    public function testTurnMonsterQueuesMonsterMoveTrigger(): void {
        $this->game->tokens->setTokenState("rune_stone", 1);
        $op = $this->game->machine->instantiateOperation("turnMonster", ACOLOR);
        $op->resolve();
        $ops = $this->game->machine->getAllOperations($this->owner);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("trigger(TMonsterMove)", $opTypes);
    }

    // -------------------------------------------------------------------------
    // Deck-top quest dispatch — Card::onTriggerQuest reads quest_on / quest_r
    // from material and queues the chain when the trigger matches.
    // -------------------------------------------------------------------------

    /**
     * Belt of Youth (card_equip_2_22): quest_on=TStep,
     * quest_r=in(forest):gainTracker:counter('countTracker>=8'):gainEquip.
     * On a TStep with the hero on a forest hex, the chain runs and lands
     * 1 progress crystal on the deck-top card.
     */
    public function testDeckTopQuestDispatchAdvancesProgress(): void {
        // Switch to Alva (hero 2) — Belt of Youth lives in her deck.
        $this->game = new GameUT();
        $this->game->initWithHero(2);
        $this->game->clearHand();
        $this->game->clearMachine();
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());

        $belt = "card_equip_2_22";
        $deck = "deck_equip_" . $this->owner;
        $this->game->tokens->moveToken($belt, $deck, 9999); // top of deck
        $this->game->tokens->moveToken("hero_2", "hex_9_1"); // forest hex

        // Sanity: deck top is Belt of Youth, hex is forest, material has quest fields.
        $top = $this->game->tokens->getTokenOnTop($deck);
        $this->assertEquals($belt, $top["key"] ?? null, "Belt of Youth should be on top of deck_equip");
        $this->assertEquals("forest", $this->game->hexMap->getHexTerrain("hex_9_1"));
        $this->assertEquals("TStep", $this->game->material->getRulesFor($belt, "quest_on", ""));
        $this->assertNotEmpty($this->game->material->getRulesFor($belt, "quest_r", ""));

        $this->createOp("trigger(TStep)");
        $this->call_resolve();
        $this->game->machine->dispatchAll();

        $this->assertEquals(
            1,
            count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $belt)),
            "TStep on a forest hex should add 1 quest progress to Belt of Youth"
        );
    }

    /** Same setup but on a non-forest hex — gate voids the chain, no progress. */
    public function testDeckTopQuestDispatchVoidedByGate(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(2);
        $this->game->clearHand();
        $this->game->clearMachine();
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());

        $belt = "card_equip_2_22";
        $this->game->tokens->moveToken($belt, "deck_equip_" . $this->owner, 9999);
        $this->game->tokens->moveToken("hero_2", "hex_11_8"); // plains, not forest

        $this->createOp("trigger(TStep)");
        $this->call_resolve();
        $this->game->machine->dispatchAll();

        $this->assertEquals(
            0,
            count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $belt)),
            "TStep on a non-forest hex should not advance progress"
        );
    }

    /** A non-matching trigger (TMonsterKilled) should not fire Belt of Youth. */
    public function testDeckTopQuestIgnoresNonMatchingTrigger(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(2);
        $this->game->clearHand();
        $this->game->clearMachine();
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());

        $belt = "card_equip_2_22";
        $this->game->tokens->moveToken($belt, "deck_equip_" . $this->owner, 9999);
        $this->game->tokens->moveToken("hero_2", "hex_9_1"); // forest

        $this->createOp("trigger(TMonsterKilled)");
        $this->call_resolve();
        $this->game->machine->dispatchAll();

        $this->assertEquals(
            0,
            count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $belt)),
            "Non-matching trigger should not advance Belt of Youth"
        );
    }
}
