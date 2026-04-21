<?php

declare(strict_types=1);

use Bga\Games\Fate\Cards\CardEquip_HomeSewnCape;
use Bga\Games\Fate\Model\Trigger;

/**
 * Smoke test for the Card subclass dispatch mechanism via Home Sewn Cape.
 *
 * Home Sewn Cape (card_equip_1_24) uses on=custom and the
 * Bga\Games\Fate\Cards\CardEquip_HomeSewnCape class. Its onRoll(auto=true)
 * hook reads the rune count from display_battle and adds that many green
 * crystals to the card. ResolveHits and Manual triggers fall through to
 * the generic CardGeneric dispatch, which queues a useCard prompt whose
 * branches come from the CSV `r` field.
 */
class CardEquip_HomeSewnCapeTest extends AbstractCardTestCase {
    private const CAPE = "card_equip_1_24";

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken(self::CAPE, $this->getPlayersTableau());
    }

    private function createCard(?Trigger $event = null): CardEquip_HomeSewnCape {
        $opType = $event !== null ? "trigger({$event->value})" : "nop";
        $parentOp = $this->game->machine->instantiateOperation($opType, $this->owner, ["card" => self::CAPE]);
        return new CardEquip_HomeSewnCape($this->game, self::CAPE, $parentOp);
    }

    private function queuedOpTypes(): array {
        return array_map(fn($o) => $o["type"], $this->game->machine->getAllOperations($this->owner));
    }

    /** No runes on display → no mana gained. */
    public function testNoRunesNoMana(): void {
        $this->createOp("trigger(TRoll)");
        $this->call_resolve(); // no voluntary cards; auto-fire happens in skip path

        $this->assertEquals(0, $this->countGreenCrystals(self::CAPE));
    }

    /** ResolveHits trigger: cape should queue a useCard prompt. */
    public function testResolveHitsQueuesUseCard(): void {
        // Seed 3 mana on cape so the prevent branch is payable.
        $this->game->effect_moveCrystals("hero_1", "green", 3, self::CAPE, ["message" => ""]);
        $this->assertEquals(3, $this->countGreenCrystals(self::CAPE));
        // Pending dealDamage so preventDamage has something to reduce.
        $this->game->machine->push("dealDamage", $this->owner, [
            "target" => "hero_1",
            "count" => 2,
        ]);

        $card = $this->createCard(Trigger::ResolveHits);
        $card->onTrigger(Trigger::ResolveHits);

        $this->assertContains("useCard", $this->queuedOpTypes());
    }

    /** Manual trigger (free-action phase): cape should queue a useCard prompt. */
    public function testManualQueuesUseCard(): void {
        $this->game->effect_moveCrystals("hero_1", "green", 2, self::CAPE, ["message" => ""]);
        // Hero outside Grimheim so `1move` has a valid destination for the move branch.
        $this->game->tokens->moveToken("hero_1", "hex_7_9");

        $card = $this->createCard(Trigger::Manual);
        $card->onTrigger(Trigger::Manual);

        $this->assertContains("useCard", $this->queuedOpTypes());
    }

    /** Cape not on tableau → trigger does nothing for it. */
    public function testCapeNotOnTableauDoesNothing(): void {
        $this->game->tokens->moveToken(self::CAPE, "deck_equip_" . $this->owner);
        $this->game->tokens->moveToken("die_attack_1", "display_battle", 3);

        $this->createOp("trigger(TRoll)");
        $this->call_resolve();

        $this->assertEquals(0, $this->countGreenCrystals(self::CAPE));
    }
}
