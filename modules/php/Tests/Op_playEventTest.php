<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Operations\Op_playEvent;
use Bga\Games\Fate\Tests\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

// Hero 1 (Bjorn) is always assigned to PCOLOR in a 1-player setup.
// Use a specific known Bjorn event card to avoid relying on deck order.
// card_event_1_27 = "Rest" (r: 2heal(self)) — simple, no expression args.
const EVENT_CARD = "card_event_1_27";

final class Op_playEventTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->setupGameTables();
        // Clear the hand and place only the card we want to test with
        $this->clearHand();
        $this->putInHand(EVENT_CARD);
    }

    private function createOp(): Op_playEvent {
        /** @var Op_playEvent */
        $op = $this->game->machine->instanciateOperation("playEvent", PCOLOR);
        return $op;
    }

    /** Move all event cards from hand back to deck */
    private function clearHand(): void {
        $handCards = $this->game->tokens->getTokensOfTypeInLocation("card", "hand_" . PCOLOR);
        foreach ($handCards as $cardId => $info) {
            $this->game->tokens->moveToken($cardId, "deck_event_" . PCOLOR);
        }
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
    // getPossibleMoves
    // -------------------------------------------------------------------------

    public function testHandEventCardsAreTargets(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey(EVENT_CARD, $moves);
    }

    public function testEmptyHandReturnsNoMoves(): void {
        $this->clearHand();
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    public function testMultipleHandCardsAllTargetable(): void {
        $second = "card_event_1_30"; // Bjorn "Sewing"
        $this->putInHand($second);
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey(EVENT_CARD, $moves);
        $this->assertArrayHasKey($second, $moves);
        $this->assertCount(2, $moves);
    }

    /** Setup needed for integration tests that require hero on map.
     *  Creates a fresh game with createAllTokens (no setupGameTables), then
     *  places hero 1 and the test event card for use. */
    private function setupHeroOnMap(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->tokens->moveToken(EVENT_CARD, "hand_" . PCOLOR);
    }

    // -------------------------------------------------------------------------
    // resolve
    // -------------------------------------------------------------------------

    public function testResolveMovesCardToDiscard(): void {
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => EVENT_CARD]);

        $this->assertEquals("discard_" . PCOLOR, $this->game->tokens->getTokenLocation(EVENT_CARD));
    }

    public function testResolveRemovesCardFromHand(): void {
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => EVENT_CARD]);

        $this->assertArrayNotHasKey(EVENT_CARD, $this->getHandCards());
    }

    public function testResolveQueuesSubOperation(): void {
        $this->setupHeroOnMap();
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => EVENT_CARD]);

        $queued = $this->game->machine->getTopOperations(PCOLOR);
        $this->assertNotEmpty($queued);
        $top = reset($queued);
        $this->assertStringContainsString("heal", $top["type"]);
    }

    public function testPlayRestCardHealsHero(): void {
        $this->setupHeroOnMap();
        // Add 4 damage to hero_1
        $this->game->effect_moveCrystals("hero_1", "red", 4, "hero_1", ["message" => ""]);

        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => EVENT_CARD]);

        // Get and resolve the queued heal operation
        $queued = $this->game->machine->getTopOperations(PCOLOR);
        $this->assertNotEmpty($queued);
        $top = reset($queued);
        $healOp = $this->game->machine->instanciateOperation($top["type"], PCOLOR);
        $healOp->action_resolve([Operation::ARG_TARGET => "hex_11_8"]);

        $damage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1"));
        $this->assertEquals(2, $damage);
    }
}
