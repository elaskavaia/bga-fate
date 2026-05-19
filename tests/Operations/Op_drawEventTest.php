<?php

declare(strict_types=1);

final class Op_drawEventTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->fillHandFromDeck(1);
    }
    private function getHandCards(): array {
        return $this->game->tokens->getTokensOfTypeInLocation("card", "hand_" . $this->owner);
    }

    private function fillHandFromDeck(int $count): void {
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

    public function testConfirmOfferedWhenHandNotFull(): void {
        $this->assertValidTarget("confirm");
    }

    // -------------------------------------------------------------------------
    // Reshuffle: when deck is empty but discard has cards, drawing reshuffles
    // -------------------------------------------------------------------------

    /** Move every card in `deck_event_{owner}` to the given location. */
    private function drainDeckTo(string $location): array {
        $deck = $this->game->tokens->getTokensOfTypeInLocation("card", "deck_event_" . $this->owner);
        $ids = array_keys($deck);
        foreach ($ids as $cardId) {
            $this->game->tokens->moveToken($cardId, $location);
        }
        return $ids;
    }

    public function testDrawReshufflesDiscardWhenDeckEmpty(): void {
        $discarded = $this->drainDeckTo("discard_" . $this->owner);
        $this->assertGreaterThan(0, count($discarded));
        $this->assertEquals(0, $this->game->tokens->countTokensInLocation("deck_event_" . $this->owner));

        $hero = $this->game->getHero($this->owner);
        $this->assertTrue($hero->drawEventCard(), "draw should succeed after reshuffle");

        // One discarded card landed in hand, the rest were reshuffled back into the deck
        $deckAfter = $this->game->tokens->countTokensInLocation("deck_event_" . $this->owner);
        $discardAfter = $this->game->tokens->countTokensInLocation("discard_" . $this->owner);
        $this->assertEquals(count($discarded) - 1, $deckAfter);
        $this->assertEquals(0, $discardAfter);
    }

    public function testGetPossibleMovesAllowsDrawWhenOnlyDiscardHasCards(): void {
        $this->drainDeckTo("discard_" . $this->owner);
        $this->createOp(); // refresh op after state change
        $this->assertValidTarget("confirm");
    }

    public function testDrawReturnsFalseWhenDeckAndDiscardEmpty(): void {
        $this->drainDeckTo("limbo");
        $this->assertFalse($this->game->getHero($this->owner)->drawEventCard());
    }

    // -------------------------------------------------------------------------
    // hand limit — Starsong II raises limit to 5
    // -------------------------------------------------------------------------

    public function testStarsongIIOffersConfirmAtHandSize4(): void {
        $this->game->tokens->moveToken("card_ability_2_8", $this->getPlayersTableau());
        $this->game->getHero($this->owner)->recalcTrackers();
        $this->fillHandFromDeck(3); // 1 from setup + 3 = 4
        $this->assertCount(4, $this->getHandCards());
        $this->assertValidTarget("confirm"); // still under limit of 5
    }

    public function testStarsongIIPromtsDiscardAtHandSize5(): void {
        $this->game->tokens->moveToken("card_ability_2_8", $this->getPlayersTableau());
        $this->game->getHero($this->owner)->recalcTrackers();
        $this->fillHandFromDeck(4); // 1 from setup + 4 = 5
        $this->assertCount(5, $this->getHandCards());
        $this->assertNotValidTarget("confirm"); // at limit, offers discard
    }

    // -------------------------------------------------------------------------
    // Testing possible moves — when hand is full
    // -------------------------------------------------------------------------

    public function testPossibleMovesReturnsHandCards(): void {
        $this->fillHandFromDeck(3);
        $handCards = $this->getHandCards();
        foreach (array_keys($handCards) as $cardId) {
            $this->assertValidTarget($cardId);
        }
    }

    public function testCanSkip(): void {
        $op = $this->op;
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

        $op = $this->op;
        $this->call_resolve($cardToDiscard);

        // Discarded card should be in discard pile
        $this->assertEquals("discard_" . $this->owner, $this->game->tokens->getTokenLocation($cardToDiscard));
        // Hand size should stay the same (discarded 1, drew 1)
        $this->assertCount($handBefore, $this->getHandCards());
    }

    public function testResolveConfirmDrawsCard(): void {
        $handBefore = count($this->getHandCards());
        $op = $this->op;
        $this->call_resolve("confirm");
        $this->assertCount($handBefore + 1, $this->getHandCards());
    }

    public function testResolveDrawsNewCard(): void {
        $this->fillHandFromDeck(3); // hand = 4
        $handCards = $this->getHandCards();
        $cardToDiscard = array_key_first($handCards);

        $op = $this->op;
        $this->call_resolve($cardToDiscard);

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

        $op = $this->op;
        $op->action_skip();

        $handAfter = $this->getHandCards();
        $this->assertEquals(array_keys($handBefore), array_keys($handAfter));
    }
}
