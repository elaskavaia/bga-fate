<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;

// Hero 1 (Bjorn) is always assigned to PCOLOR in a 1-player setup.
// Use a specific known Bjorn event card to avoid relying on deck order.
// card_event_1_27 = "Rest" (r: 2heal(self)) — simple, no expression args.
const EVENT_CARD = "card_event_1_27";

final class Op_playEventTest extends AbstractOpTestCase {
    function getOperationType(): string {
        return "useCard";
    }
    protected function setUp(): void {
        parent::setUp();
        $this->putInHand(EVENT_CARD);
        // Add damage so heal(self) from Rest card has valid targets
        $this->game->effect_moveCrystals("hero_1", "red", 3, "hero_1", ["message" => ""]);
    }

    /** Move a specific card to hand (from wherever it is) */
    private function putInHand(string $cardId): void {
        $this->game->tokens->moveToken($cardId, "hand_" . PCOLOR);
    }

    /** Helper: get event cards in hand for PCOLOR */
    private function getHandCards(): array {
        return $this->game->tokens->getTokensOfTypeInLocation("card", "hand_" . PCOLOR);
    }

    // -------------------------------------------------------------------------
    // Testing possible moves
    // -------------------------------------------------------------------------

    public function testHandEventCardsAreTargets(): void {
        $this->assertValidTarget(EVENT_CARD);
    }

    public function testEmptyHandReturnsNoMoves(): void {
        $this->game->clearHand();
        $this->assertNoValidTargets();
    }

    public function testMultipleHandCardsAllTargetable(): void {
        $second = "card_event_1_30"; // Bjorn "Sewing" — r=1repairCard(all), needs a damaged equipment card
        $this->putInHand($second);
        $this->game->tokens->moveToken("card_equip_1_15", $this->getPlayersTableau());
        $this->game->effect_moveCrystals("hero_1", "red", 1, "card_equip_1_15", ["message" => ""]);
        $this->assertValidTarget(EVENT_CARD);
        $this->assertValidTarget($second);
        $this->assertValidTargetCount(2);
    }

    // -------------------------------------------------------------------------
    // resolve
    // -------------------------------------------------------------------------

    public function testResolveMovesCardToDiscard(): void {
        $op = $this->op;
        $this->call_resolve(EVENT_CARD);

        $this->assertEquals("discard_" . PCOLOR, $this->game->tokens->getTokenLocation(EVENT_CARD));
    }

    public function testResolveRemovesCardFromHand(): void {
        $op = $this->op;
        $this->call_resolve(EVENT_CARD);

        $this->assertArrayNotHasKey(EVENT_CARD, $this->getHandCards());
    }

    public function testResolveQueuesSubOperation(): void {
        $this->call_resolve(EVENT_CARD);

        $queued = $this->game->machine->getTopOperations(PCOLOR);
        $this->assertNotEmpty($queued);
        $top = reset($queued);
        $this->assertStringContainsString("heal", $top["type"]);
    }

    public function testPlayRestCardHealsHero(): void {
        // setUp adds 3 damage; Rest r=2heal(self) heals 2 → 1 remaining
        $this->call_resolve(EVENT_CARD);

        $queued = $this->game->machine->getTopOperations(PCOLOR);
        $this->assertNotEmpty($queued);
        $healOp = $this->game->machine->instantiateOperationFromDbRow(reset($queued));
        $healOp->action_resolve([Operation::ARG_TARGET => "hero_1"]);

        $damage = $this->countRedCrystals("hero_1");
        $this->assertEquals(1, $damage);
    }
}
