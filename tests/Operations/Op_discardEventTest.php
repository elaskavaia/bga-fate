<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Operations\Op_discardEvent;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_discardEventTest extends AbstractOpTestCase {
    protected function setUp(): void {
        $this->game = new \Bga\Games\Fate\Stubs\GameUT();
        $this->game->initWithHero(1);
        // Don't clear hand — tests rely on the 1 card drawn by setupGameTables
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());
        $this->op = $this->createOp();
    }

    private function getHandCards(): array {
        return $this->game->tokens->getTokensOfTypeInLocation("card", "hand_" . $this->owner);
    }

    /** Helper: move N event cards from deck to hand */
    private function fillHand(int $count): void {
        $deck = $this->game->tokens->getTokensOfTypeInLocation("card", "deck_event_" . $this->owner);
        $i = 0;
        foreach ($deck as $cardId => $info) {
            if ($i >= $count) {
                break;
            }
            $this->game->tokens->moveToken($cardId, "hand_" . $this->owner);
            $i++;
        }
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves
    // -------------------------------------------------------------------------

    public function testHandCardsAreTargets(): void {
        $op = $this->op;
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
            $this->game->tokens->moveToken($cardId, "deck_event_" . $this->owner);
        }
        $op = $this->op;
        $this->assertNoValidTargets();
    }

    public function testMultipleHandCardsAllTargetable(): void {
        $this->fillHand(2); // plus the 1 from setup = 3 total
        $op = $this->op;
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

        $op = $this->op;
        $this->call_resolve($cardId);

        $this->assertEquals("discard_" . $this->owner, $this->game->tokens->getTokenLocation($cardId));
    }

    public function testResolveRemovesCardFromHand(): void {
        $handBefore = count($this->getHandCards());

        $cardId = array_key_first($this->getHandCards());
        $op = $this->op;
        $this->call_resolve($cardId);

        $handAfter = count($this->getHandCards());
        $this->assertEquals($handBefore - 1, $handAfter);
    }
}
