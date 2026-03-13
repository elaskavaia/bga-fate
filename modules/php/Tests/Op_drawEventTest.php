<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Operations\Op_drawEvent;
use Bga\Games\Fate\Tests\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_drawEventTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->setupGameTables();
    }

    private function createOp(): Op_drawEvent {
        /** @var Op_drawEvent */
        $op = $this->game->machine->instanciateOperation("drawEvent", PCOLOR);
        return $op;
    }

    private function getHandCards(): array {
        return $this->game->tokens->getTokensOfTypeInLocation("card", "hand_" . PCOLOR);
    }

    private function fillHandFromDeck(int $count): void {
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
    // auto() — hand < 4
    // -------------------------------------------------------------------------

    public function testAutoDrawsWhenHandNotFull(): void {
        // Setup gives 1 card in hand, so hand < 4
        $handBefore = count($this->getHandCards());
        $op = $this->createOp();
        $result = $op->auto();
        $this->assertTrue($result);
        $this->assertCount($handBefore + 1, $this->getHandCards());
    }

    public function testAutoDoesNothingWhenDeckEmpty(): void {
        // Empty the deck
        $deck = $this->game->tokens->getTokensOfTypeInLocation("card", "deck_event_" . PCOLOR);
        foreach ($deck as $cardId => $info) {
            $this->game->tokens->moveToken($cardId, "limbo");
        }
        $handBefore = count($this->getHandCards());
        $op = $this->createOp();
        $result = $op->auto();
        $this->assertTrue($result);
        $this->assertCount($handBefore, $this->getHandCards());
    }

    // -------------------------------------------------------------------------
    // auto() — hand >= 4, enters player state
    // -------------------------------------------------------------------------

    public function testAutoReturnsFalseWhenHandFull(): void {
        $this->fillHandFromDeck(3); // 1 from setup + 3 = 4
        $this->assertGreaterThanOrEqual(4, count($this->getHandCards()));
        $op = $this->createOp();
        $result = $op->auto();
        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves — when hand is full
    // -------------------------------------------------------------------------

    public function testPossibleMovesReturnsHandCards(): void {
        $this->fillHandFromDeck(3);
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $handCards = $this->getHandCards();
        $this->assertCount(count($handCards), $moves);
        foreach ($handCards as $cardId => $info) {
            $this->assertArrayHasKey($cardId, $moves);
        }
    }

    public function testCanSkip(): void {
        $op = $this->createOp();
        $this->assertTrue($op->canSkip());
    }

    // -------------------------------------------------------------------------
    // resolve — discard then draw
    // -------------------------------------------------------------------------

    public function testResolveDiscardsAndDraws(): void {
        $this->fillHandFromDeck(3); // hand = 4
        $handCards = $this->getHandCards();
        $cardToDiscard = array_key_first($handCards);
        $handBefore = count($handCards);

        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => $cardToDiscard]);

        // Discarded card should be in discard pile
        $this->assertEquals("discard_" . PCOLOR, $this->game->tokens->getTokenLocation($cardToDiscard));
        // Hand size should stay the same (discarded 1, drew 1)
        $this->assertCount($handBefore, $this->getHandCards());
    }

    public function testResolveDrawsNewCard(): void {
        $this->fillHandFromDeck(3); // hand = 4
        $handCards = $this->getHandCards();
        $cardToDiscard = array_key_first($handCards);

        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => $cardToDiscard]);

        // The discarded card should no longer be in hand
        $newHand = $this->getHandCards();
        $this->assertArrayNotHasKey($cardToDiscard, $newHand);
    }

    // -------------------------------------------------------------------------
    // skip — keeps hand, no draw
    // -------------------------------------------------------------------------

    public function testSkipKeepsHandUnchanged(): void {
        $this->fillHandFromDeck(3); // hand = 4
        $handBefore = $this->getHandCards();

        $op = $this->createOp();
        $op->action_skip();

        $handAfter = $this->getHandCards();
        $this->assertEquals(array_keys($handBefore), array_keys($handAfter));
    }
}
