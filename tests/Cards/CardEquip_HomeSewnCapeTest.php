<?php

declare(strict_types=1);

/**
 * Smoke test for the Card subclass dispatch mechanism via Home Sewn Cape.
 *
 * Home Sewn Cape (card_equip_1_24) uses on=custom and the
 * Bga\Games\Fate\Cards\CardEquip_HomeSewnCape class. Its onRoll(auto=true)
 * hook reads the rune count from display_battle and adds that many green
 * crystals to the card.
 */
class CardEquip_HomeSewnCapeTest extends AbstractCardTestCase {
    private const CAPE = "card_equip_1_24";

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken(self::CAPE, $this->getPlayersTableau());
    }

    /** No runes on display → no mana gained. */
    public function testNoRunesNoMana(): void {
        $this->createOp("trigger(EventRoll)");
        $this->call_resolve(); // no voluntary cards; auto-fire happens in skip path

        $this->assertEquals(0, $this->countGreenCrystals(self::CAPE));
    }

    /** Cape not on tableau → trigger does nothing for it. */
    public function testCapeNotOnTableauDoesNothing(): void {
        $this->game->tokens->moveToken(self::CAPE, "deck_equip_" . $this->owner);
        $this->game->tokens->moveToken("die_attack_1", "display_battle", 3);

        $this->createOp("trigger(EventRoll)");
        $this->call_resolve();

        $this->assertEquals(0, $this->countGreenCrystals(self::CAPE));
    }
}
