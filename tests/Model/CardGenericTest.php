<?php

declare(strict_types=1);

use Bga\Games\Fate\Model\CardGeneric;
use Bga\Games\Fate\Model\Trigger;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CardGeneric — the default Card subclass used when no bespoke
 * class exists for a card. Its job is to read the card's `on` field from
 * Material and, when a trigger of that type fires, queue the appropriate
 * voluntary action op (useCard) to prompt
 * the player.
 *
 * These tests construct a CardGeneric directly with a minimal parent op,
 * call onTrigger($triggerName), and assert what was queued.
 */
final class CardGenericTest extends AbstractCardTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->clearMachine();
        // Place hero on a hex so move/range checks work for queued op validation.
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    /** Build a CardGeneric for $cardId, parented to a fresh trigger op of $event (null = nop). */
    private function createCard(string $cardId, ?Trigger $event = null): CardGeneric {
        $opType = $event !== null ? "trigger({$event->value})" : "nop";
        $parentOp = $this->game->machine->instantiateOperation($opType, $this->owner);
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
        $card = $this->createCard("card_ability_3_3", Trigger::ResolveHits);
        $this->assertTrue($card->canTriggerEffectOn(Trigger::ResolveHits));
    }

    public function testCanTriggerReturnsFalseWhenOnFieldDoesNotMatch(): void {
        $this->game->tokens->moveToken("card_ability_3_3", "tableau_$this->owner");
        $card = $this->createCard("card_ability_3_3", Trigger::Roll);
        $this->assertFalse($card->canTriggerEffectOn(Trigger::Roll));
    }

    public function testCanTriggerReturnsFalseForCardWithNoOnField(): void {
        // Sure Shot I (card_ability_1_3) has no `on` field
        $card = $this->createCard("card_ability_1_3", Trigger::Roll);
        $this->assertFalse($card->canTriggerEffectOn(Trigger::Roll));
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

        $card = $this->createCard("card_ability_3_3", Trigger::ResolveHits);
        $this->assertTrue($card->canBePlayed(Trigger::ResolveHits));
    }

    public function testCanBePlayedReturnsFalseWhenTriggerDoesNotMatch(): void {
        $this->game->tokens->moveToken("card_ability_3_3", "tableau_$this->owner");
        $card = $this->createCard("card_ability_3_3", Trigger::Roll);
        $this->assertFalse($card->canBePlayed(Trigger::Roll));
    }

    public function testCanBePlayedReturnsFalseWhenCannotPayCost(): void {
        // Bjorn's hero card on=roll, r=spendAction(actionFocus):2addDamage — both actions already taken
        $hero = $this->game->getHero($this->owner);
        $hero->placeActionMarker("actionMove");
        $hero->placeActionMarker("actionAttack");
        $card = $this->createCard("card_hero_1_1", Trigger::Roll);
        $this->assertFalse($card->canBePlayed(Trigger::Roll));
    }

    public function testCanBePlayedReturnsFalseWhenAlreadyUsedThisTurn(): void {
        // Sure Shot I (card_ability_1_3) has no `on` field → once-per-turn, state=1 means used
        $this->game->tokens->setTokenState("card_ability_1_3", 1);
        $card = $this->createCard("card_ability_1_3");
        $this->assertFalse($card->canBePlayed(Trigger::Manual));
    }

    public function testCanBePlayedReturnsFalseWhenRFieldEmpty(): void {
        // Bjorn's First Bow (card_equip_1_15) has no `r` field — passive stats only
        $card = $this->createCard("card_equip_1_15", Trigger::Roll);
        $this->assertFalse($card->canBePlayed(Trigger::Roll));
    }

    public function testCanBePlayedPopulatesErrorInfo(): void {
        $this->game->tokens->moveToken("card_ability_3_3", "tableau_$this->owner");
        $card = $this->createCard("card_ability_3_3", Trigger::Roll);
        $errorRes = [];
        $result = $card->canBePlayed(Trigger::Roll, $errorRes);
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

        $card = $this->createCard("card_ability_3_3", Trigger::ResolveHits);
        $card->onTrigger(Trigger::ResolveHits);

        $this->assertContains("useCard", $this->queuedOpTypes());
    }

    public function testEventCardWithMatchingOnQueuesPlayEvent(): void {
        // Perfect Aim (card_event_1_31) on=roll
        $this->game->tokens->moveToken("card_event_1_31", "hand_$this->owner");
        $this->game->tokens->moveToken("die_attack_1", "display_battle", 2);

        $card = $this->createCard("card_event_1_31", Trigger::Roll);
        $card->onTrigger(Trigger::Roll);

        $this->assertContains("useCard", $this->queuedOpTypes());
    }

    // -------------------------------------------------------------------------
    // Non-matching `on` field — nothing gets queued
    // -------------------------------------------------------------------------

    public function testCardWithNonMatchingOnDoesNothing(): void {
        // Riposte I has on=resolveHits — should not react to trigger(roll).
        $this->game->tokens->moveToken("card_ability_3_3", "tableau_$this->owner");

        $card = $this->createCard("card_ability_3_3", Trigger::Roll);
        $card->onTrigger(Trigger::Roll);

        $opTypes = $this->queuedOpTypes();

        $this->assertNotContains("useCard", $opTypes);
    }

    public function testCardWithoutOnFieldDoesNothing(): void {
        // Sure Shot I (card_ability_1_3) has no `on` field — voluntary-anytime.
        // CardGeneric only reacts when `on` matches; for empty `on` it never fires.
        $card = $this->createCard("card_ability_1_3", Trigger::Roll);
        $card->onTrigger(Trigger::Roll);

        $this->assertNotContains("useCard", $this->queuedOpTypes());
    }

    // -------------------------------------------------------------------------
    // Card cannot be paid → still skipped (delegates to action op's getArgsInfo)
    // -------------------------------------------------------------------------

    public function testHeroCardSkippedWhenCannotPay(): void {
        // Bjorn's hero card (card_hero_1_1) on=roll, r=spendAction(actionFocus):2addDamage
        // Both actions already taken, so spendAction can't be paid; the card is not playable.
        $hero = $this->game->getHero($this->owner);
        $hero->placeActionMarker("actionMove");
        $hero->placeActionMarker("actionAttack");
        $card = $this->createCard("card_hero_1_1", Trigger::Roll);
        $card->onTrigger(Trigger::Roll);

        $this->assertNotContains("useCard", $this->queuedOpTypes());
    }
}
