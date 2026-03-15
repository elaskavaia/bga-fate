<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Operations\Op_discardEvent;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_discardEventTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->setupGameTables();
    }

    private function createOp(): Op_discardEvent {
        /** @var Op_discardEvent */
        $op = $this->game->machine->instanciateOperation("discardEvent", PCOLOR);
        return $op;
    }

    /** Helper: get event cards in hand for PCOLOR */
    private function getHandCards(): array {
        return $this->game->tokens->getTokensOfTypeInLocation("card", "hand_" . PCOLOR);
    }

    /** Helper: move N event cards from deck to hand */
    private function fillHand(int $count): void {
        $deck = $this->game->tokens->getTokensOfTypeInLocation("card", "deck_event_" . PCOLOR);
        $i = 0;
        foreach ($deck as $cardId => $info) {
            if ($i >= $count) {
                break;
            }
            $this->game->tokens->moveToken($cardId, "hand_" . PCOLOR);
            $i++;
        }
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves
    // -------------------------------------------------------------------------

    public function testHandCardsAreTargets(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        // Setup draws 1 card to hand
        $handCards = $this->getHandCards();
        $this->assertNotEmpty($handCards);
        foreach ($handCards as $cardId => $info) {
            $this->assertArrayHasKey($cardId, $moves);
        }
    }

    public function testEmptyHandReturnsNoMoves(): void {
        // Move all hand cards back to deck
        $handCards = $this->getHandCards();
        foreach ($handCards as $cardId => $info) {
            $this->game->tokens->moveToken($cardId, "deck_event_" . PCOLOR);
        }
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    public function testMultipleHandCardsAllTargetable(): void {
        $this->fillHand(2); // plus the 1 from setup = 3 total
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $handCards = $this->getHandCards();
        $this->assertCount(count($handCards), $moves);
    }

    // -------------------------------------------------------------------------
    // resolve
    // -------------------------------------------------------------------------

    public function testResolveMovesCardToDiscard(): void {
        $handCards = $this->getHandCards();
        $cardId = array_key_first($handCards);

        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => $cardId]);

        $this->assertEquals("discard_" . PCOLOR, $this->game->tokens->getTokenLocation($cardId));
    }

    public function testResolveRemovesCardFromHand(): void {
        $handBefore = count($this->getHandCards());

        $cardId = array_key_first($this->getHandCards());
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => $cardId]);

        $handAfter = count($this->getHandCards());
        $this->assertEquals($handBefore - 1, $handAfter);
    }
}
