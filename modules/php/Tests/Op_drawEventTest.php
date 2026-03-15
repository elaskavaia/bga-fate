<?php

declare(strict_types=1);

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
    // getPossibleMoves — hand < limit: confirm target
    // -------------------------------------------------------------------------

    public function testConfirmOfferedWhenHandNotFull(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("confirm", $moves);
        $this->assertEquals(0, $moves["confirm"]["q"]);
    }

    public function testDeckEmptyReturnsError(): void {
        $deck = $this->game->tokens->getTokensOfTypeInLocation("card", "deck_event_" . PCOLOR);
        foreach ($deck as $cardId => $info) {
            $this->game->tokens->moveToken($cardId, "limbo");
        }
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayNotHasKey("confirm", $moves);
        $this->assertNotEquals(0, $moves["q"]);
    }

    // -------------------------------------------------------------------------
    // hand limit — Starsong II raises limit to 5
    // -------------------------------------------------------------------------

    public function testStarsongIIOffersConfirmAtHandSize4(): void {
        $this->game->tokens->moveToken("card_ability_2_8", "tableau_" . PCOLOR);
        $this->fillHandFromDeck(3); // 1 from setup + 3 = 4
        $this->assertCount(4, $this->getHandCards());
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("confirm", $moves); // still under limit of 5
    }

    public function testStarsongIIPromtsDiscardAtHandSize5(): void {
        $this->game->tokens->moveToken("card_ability_2_8", "tableau_" . PCOLOR);
        $this->fillHandFromDeck(4); // 1 from setup + 4 = 5
        $this->assertCount(5, $this->getHandCards());
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayNotHasKey("confirm", $moves); // at limit, offers discard
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

    public function testResolveConfirmDrawsCard(): void {
        $handBefore = count($this->getHandCards());
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "confirm"]);
        $this->assertCount($handBefore + 1, $this->getHandCards());
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
