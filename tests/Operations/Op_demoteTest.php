<?php

declare(strict_types=1);

use Bga\Games\Fate\Stubs\GameUT;

/**
 * Tests for Op_demote — end-of-turn optional demotion of the top equipment or
 * top ability to the bottom of its respective pile (RULES.md End-of-Turn step 5).
 *
 * Uses Alva (hero 2) so we can exercise Belt of Youth (a card that legitimately
 * accumulates quest-progress crystals on the deck-top — the realistic "in flight"
 * scenario the demote rule is designed to discard).
 */
final class Op_demoteTest extends AbstractOpTestCase {
    private string $heroId;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(2); // Alva — Belt of Youth lives in her equip deck
        $this->game->clearHand();
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());
        $this->heroId = $this->game->getHeroTokenId($this->owner);
    }

    private function equipDeck(): string {
        return "deck_equip_" . $this->owner;
    }

    private function abilityDeck(): string {
        return "deck_ability_" . $this->owner;
    }

    /** Force a specific card to the very top of $deck (max state). */
    private function seedDeckTop(string $cardId, string $deck): void {
        $this->game->tokens->moveToken($cardId, $deck, 9999);
    }

    /** Empty a deck by shoving every card in it to limbo. */
    private function clearDeck(string $deck, string $tokenType): void {
        foreach (array_keys($this->game->tokens->getTokensOfTypeInLocation($tokenType, $deck)) as $key) {
            $this->game->tokens->moveToken($key, "limbo");
        }
    }

    private function pushDemote(): void {
        $this->game->machine->push("demote", $this->owner);
        $this->game->machine->dispatchAll();
    }

    /** Snapshot ordered card ids from a deck (top first, bottom last). */
    private function deckOrder(string $deck): array {
        $tokens = $this->game->tokens->getTokensOfTypeInLocation(null, $deck);
        uasort($tokens, fn($a, $b) => (int) $b["state"] <=> (int) $a["state"]);
        return array_keys($tokens);
    }

    // -------------------------------------------------------------------------
    // Equip demote — with quest progress in flight (the headline case)
    // -------------------------------------------------------------------------

    public function testDemoteEquipWithProgressSweepsCrystalsAndMovesCardToBottom(): void {
        $belt = "card_equip_2_22";
        $this->seedDeckTop($belt, $this->equipDeck());
        // Park 5 progress crystals on Belt of Youth as if it'd been accumulating
        // ticks while sitting on top of the deck.
        $this->game->effect_moveCrystals($this->heroId, "red", 5, $belt, ["message" => ""]);
        $this->assertEquals(5, $this->countRedCrystals($belt));
        $supplyBefore = $this->countRedCrystals("supply_crystal_red");

        // Capture what the next top will be so we can assert the reveal.
        $orderBefore = $this->deckOrder($this->equipDeck());
        $this->assertSame($belt, $orderBefore[0], "Belt should start at the top");
        $expectedNewTop = $orderBefore[1];

        $this->createOp();
        $this->assertValidTarget($belt);
        $this->call_resolve($belt);

        $orderAfter = $this->deckOrder($this->equipDeck());
        $this->assertSame($expectedNewTop, $orderAfter[0], "second card surfaces as the new top");
        $this->assertSame($belt, end($orderAfter), "Belt is now at the bottom of the pile");
        $this->assertEquals(0, $this->countRedCrystals($belt), "progress crystals swept off Belt");
        $this->assertEquals($supplyBefore + 5, $this->countRedCrystals("supply_crystal_red"), "5 crystals returned to supply");
        $this->assertEquals($this->equipDeck(), $this->game->tokens->getTokenLocation($belt), "still in the deck, just lower");
    }

    // -------------------------------------------------------------------------
    // Equip demote — no progress in flight (still works, no-op sweep)
    // -------------------------------------------------------------------------

    public function testDemoteEquipWithNoProgressMovesCardToBottom(): void {
        $belt = "card_equip_2_22";
        $this->seedDeckTop($belt, $this->equipDeck());
        $this->assertEquals(0, $this->countRedCrystals($belt));

        $orderBefore = $this->deckOrder($this->equipDeck());
        $expectedNewTop = $orderBefore[1];

        $this->createOp();
        $this->call_resolve($belt);

        $orderAfter = $this->deckOrder($this->equipDeck());
        $this->assertSame($expectedNewTop, $orderAfter[0]);
        $this->assertSame($belt, end($orderAfter));
        $this->assertEquals(0, $this->countRedCrystals($belt), "no crystals to sweep, none on card");
    }

    // -------------------------------------------------------------------------
    // Ability demote — different pile, untouched siblings
    // -------------------------------------------------------------------------

    public function testDemoteAbilityMovesToBottomAndLeavesEquipDeckAlone(): void {
        // Pin a known ability card on top so we can name it in the response.
        $abilityTop = "card_ability_2_13"; // Flexibility I
        $this->seedDeckTop($abilityTop, $this->abilityDeck());

        $equipOrderBefore = $this->deckOrder($this->equipDeck());
        $abilityOrderBefore = $this->deckOrder($this->abilityDeck());
        $this->assertSame($abilityTop, $abilityOrderBefore[0]);
        $expectedNewAbilityTop = $abilityOrderBefore[1];

        $this->createOp();
        // Both deck tops should be offered as targets.
        $this->assertValidTarget($abilityTop);
        $this->assertValidTarget($equipOrderBefore[0]);
        $this->call_resolve($abilityTop);

        $abilityOrderAfter = $this->deckOrder($this->abilityDeck());
        $this->assertSame($expectedNewAbilityTop, $abilityOrderAfter[0], "next ability surfaces");
        $this->assertSame($abilityTop, end($abilityOrderAfter), "demoted ability sits at bottom");

        // Equip pile must be untouched.
        $this->assertSame($equipOrderBefore, $this->deckOrder($this->equipDeck()), "equip deck unchanged");
    }

    // -------------------------------------------------------------------------
    // Skip — both decks unchanged
    // -------------------------------------------------------------------------

    public function testSkipLeavesBothDecksUnchanged(): void {
        $equipBefore = $this->deckOrder($this->equipDeck());
        $abilityBefore = $this->deckOrder($this->abilityDeck());

        $this->createOp();
        $this->assertTrue($this->op->canSkip(), "demote is voluntary");
        $this->op->action_skip();

        $this->assertSame($equipBefore, $this->deckOrder($this->equipDeck()), "equip deck untouched on skip");
        $this->assertSame($abilityBefore, $this->deckOrder($this->abilityDeck()), "ability deck untouched on skip");
    }

    // -------------------------------------------------------------------------
    // Empty equip deck — only the ability top should be offered
    // -------------------------------------------------------------------------

    public function testEmptyEquipDeckOnlyOffersAbilityTop(): void {
        $this->clearDeck($this->equipDeck(), "card_equip");
        $abilityTop = $this->game->tokens->getTokenOnTop($this->abilityDeck());
        $this->assertNotNull($abilityTop, "Alva's ability deck should still have cards");

        $this->createOp();
        $this->assertValidTargetCount(1);
        $this->assertValidTarget($abilityTop["key"]);
    }

    // -------------------------------------------------------------------------
    // Both decks empty — op auto-skips silently when pushed onto the machine
    // -------------------------------------------------------------------------

    public function testBothDecksEmptyAutoSkipsAndDrains(): void {
        $this->clearDeck($this->equipDeck(), "card_equip");
        $this->clearDeck($this->abilityDeck(), "card_ability");

        // Sanity-check the op-instance view: zero targets, but still skippable.
        $this->createOp();
        $this->assertNoValidTargets();
        $this->assertTrue($this->op->canSkip());

        // Now push for real and let the machine drain — it should auto-skip
        // (canSkip + noValidTargets ⇒ canResolveAutomatically). The pre-existing
        // turn op (sitting beneath what we pushed) is what's left over; what
        // matters is that demote isn't blocking the queue any more.
        $this->pushDemote();
        $pending = $this->game->machine->getTopOperations($this->owner);
        $top = $pending ? reset($pending) : null;
        $this->assertNotEquals("demote", $top["type"] ?? null, "demote should drain itself silently with no prompt");
    }
}
