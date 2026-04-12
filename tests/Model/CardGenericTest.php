<?php

declare(strict_types=1);

use Bga\Games\Fate\Model\CardGeneric;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CardGeneric — the default Card subclass used when no bespoke
 * class exists for a card. Its job is to read the card's `on` field from
 * Material and, when a trigger of that type fires, queue the appropriate
 * voluntary action op (useEquipment / useAbility / playEvent) to prompt
 * the player.
 *
 * These tests construct a CardGeneric directly with a minimal parent op,
 * call onTrigger($triggerName), and assert what was queued.
 */
final class CardGenericTest extends TestCase {
    private GameUT $game;
    private string $owner;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
        $this->game->clearHand();
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());
        $this->game->clearMachine();
        // Place hero on a hex so move/range checks work for queued op validation.
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    /** Build a CardGeneric for $cardId, parented to a fresh trigger op of $triggerName. */
    private function makeCard(string $cardId, string $triggerName = ""): CardGeneric {
        $opType = $triggerName ? "trigger($triggerName)" : "nop";
        $parentOp = $this->game->machine->instanciateOperation($opType, $this->owner);
        return new CardGeneric($this->game, $cardId, $parentOp);
    }

    private function queuedOpTypes(): array {
        return array_map(fn($o) => $o["type"], $this->game->machine->getAllOperations($this->owner));
    }

    // -------------------------------------------------------------------------
    // canTrigger — checks whether card reacts to a given trigger type
    // -------------------------------------------------------------------------

    public function testCanTriggerReturnsTrueWhenOnFieldMatches(): void {
        // Riposte I has on=resolveHits
        $this->game->tokens->moveToken("card_ability_3_3", "tableau_$this->owner");
        $card = $this->makeCard("card_ability_3_3", "resolveHits");
        $this->assertTrue($card->canTrigger("resolveHits"));
    }

    public function testCanTriggerReturnsFalseWhenOnFieldDoesNotMatch(): void {
        $this->game->tokens->moveToken("card_ability_3_3", "tableau_$this->owner");
        $card = $this->makeCard("card_ability_3_3", "roll");
        $this->assertFalse($card->canTrigger("roll"));
    }

    public function testCanTriggerReturnsFalseForCardWithNoOnField(): void {
        // Sure Shot I (card_ability_1_3) has no `on` field
        $card = $this->makeCard("card_ability_1_3", "roll");
        $this->assertFalse($card->canTrigger("roll"));
    }

    // -------------------------------------------------------------------------
    // canBePlayed — checks whether card is actually playable (trigger match + cost payable + r non-empty)
    // -------------------------------------------------------------------------

    public function testCanBePlayedReturnsTrueWhenPlayable(): void {
        // Riposte I on=resolveHits, r=2spendMana:(2preventDamage:2dealDamage)
        $this->game->tokens->moveToken("card_ability_3_3", "tableau_$this->owner");
        $this->game->tokens->moveToken("crystal_green_1", "card_ability_3_3");
        $this->game->tokens->moveToken("crystal_green_2", "card_ability_3_3");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->machine->push("dealDamage", $this->owner, ["target" => "hex_11_8", "count" => 3]);

        $card = $this->makeCard("card_ability_3_3", "resolveHits");
        $this->assertTrue($card->canBePlayed("resolveHits"));
    }

    public function testCanBePlayedReturnsFalseWhenTriggerDoesNotMatch(): void {
        $this->game->tokens->moveToken("card_ability_3_3", "tableau_$this->owner");
        $card = $this->makeCard("card_ability_3_3", "roll");
        $this->assertFalse($card->canBePlayed("roll"));
    }

    public function testCanBePlayedReturnsFalseWhenCannotPayCost(): void {
        // Bjorn's hero card on=roll, r=spendAction(actionFocus):2dealDamage — no action available
        $card = $this->makeCard("card_hero_1_1", "roll");
        $this->assertFalse($card->canBePlayed("roll"));
    }

    public function testCanBePlayedReturnsFalseWhenAlreadyUsedThisTurn(): void {
        // Sure Shot I (card_ability_1_3) has no `on` field → once-per-turn, state=1 means used
        $this->game->tokens->setTokenState("card_ability_1_3", 1);
        $card = $this->makeCard("card_ability_1_3", "");
        $this->assertFalse($card->canBePlayed(""));
    }

    public function testCanBePlayedReturnsFalseWhenRFieldEmpty(): void {
        // Bjorn's First Bow (card_equip_1_15) has no `r` field — passive stats only
        $card = $this->makeCard("card_equip_1_15", "roll");
        $this->assertFalse($card->canBePlayed("roll"));
    }

    public function testCanBePlayedPopulatesErrorInfo(): void {
        $this->game->tokens->moveToken("card_ability_3_3", "tableau_$this->owner");
        $card = $this->makeCard("card_ability_3_3", "roll");
        $errorRes = [];
        $result = $card->canBePlayed("roll", $errorRes);
        $this->assertFalse($result);
        $this->assertNotEquals(0, $errorRes["q"] ?? 0);
    }

    // -------------------------------------------------------------------------
    // Matching `on` field — voluntary action gets queued
    // -------------------------------------------------------------------------

    public function testAbilityCardWithMatchingOnQueuesUseAbility(): void {
        // Riposte I (card_ability_3_3) on=resolveHits, r=2spendMana:(2preventDamage:2dealDamage)
        $this->game->tokens->moveToken("card_ability_3_3", "tableau_$this->owner");
        $this->game->tokens->moveToken("crystal_green_1", "card_ability_3_3");
        $this->game->tokens->moveToken("crystal_green_2", "card_ability_3_3");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->machine->push("dealDamage", $this->owner, ["target" => "hex_11_8", "count" => 3]);

        $card = $this->makeCard("card_ability_3_3", "resolveHits");
        $card->onTrigger("resolveHits");

        $this->assertContains("useAbility", $this->queuedOpTypes());
    }

    public function testEventCardWithMatchingOnQueuesPlayEvent(): void {
        // Perfect Aim (card_event_1_31) on=roll
        $this->game->tokens->moveToken("card_event_1_31", "hand_$this->owner");
        $this->game->tokens->moveToken("die_attack_1", "display_battle", 2);

        $card = $this->makeCard("card_event_1_31", "roll");
        $card->onTrigger("roll");

        $this->assertContains("playEvent", $this->queuedOpTypes());
    }

    // -------------------------------------------------------------------------
    // Non-matching `on` field — nothing gets queued
    // -------------------------------------------------------------------------

    public function testCardWithNonMatchingOnDoesNothing(): void {
        // Riposte I has on=resolveHits — should not react to trigger(roll).
        $this->game->tokens->moveToken("card_ability_3_3", "tableau_$this->owner");

        $card = $this->makeCard("card_ability_3_3", "roll");
        $card->onTrigger("roll");

        $opTypes = $this->queuedOpTypes();
        $this->assertNotContains("useAbility", $opTypes);
        $this->assertNotContains("playEvent", $opTypes);
    }

    public function testCardWithoutOnFieldDoesNothing(): void {
        // Sure Shot I (card_ability_1_3) has no `on` field — voluntary-anytime.
        // CardGeneric only reacts when `on` matches; for empty `on` it never fires.
        $card = $this->makeCard("card_ability_1_3", "roll");
        $card->onTrigger("roll");

        $this->assertNotContains("useAbility", $this->queuedOpTypes());
    }

    // -------------------------------------------------------------------------
    // Card cannot be paid → still skipped (delegates to action op's getArgsInfo)
    // -------------------------------------------------------------------------

    public function testHeroCardSkippedWhenCannotPay(): void {
        // Bjorn's hero card (card_hero_1_1) on=roll, r=spendAction(actionFocus):2dealDamage
        // Without an actionFocus marker, spendAction can't be paid; the card is not playable.
        $card = $this->makeCard("card_hero_1_1", "roll");
        $card->onTrigger("roll");

        $this->assertNotContains("useAbility", $this->queuedOpTypes());
    }
}
