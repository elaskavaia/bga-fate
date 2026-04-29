<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;

/**
 * Tests for Op_gainTracker — quest progress crystal on the deck-top equip card.
 *
 * card_equip_1_22 = Trollbane (Bjorn). Used here only as an arbitrary equip card to
 * place on top of deck_equip; the test does not exercise its quest semantics.
 */
final class Op_gainTrackerTest extends AbstractOpTestCase {
    private string $deckTopCard = "card_equip_1_22";

    private function deckLocation(): string {
        return "deck_equip_" . $this->owner;
    }

    /** Force a specific card to the top of deck_equip_{owner}. */
    private function seedDeckTop(string $cardId): void {
        $this->game->tokens->moveToken($cardId, $this->deckLocation(), 9999);
    }

    public function testEmptyDeckIsNotApplicable(): void {
        // Move every card out of the deck.
        foreach (array_keys($this->game->tokens->getTokensOfTypeInLocation("card_equip", $this->deckLocation())) as $key) {
            $this->game->tokens->moveToken($key, "limbo");
        }
        $this->createOp();
        $this->assertNoValidTargetsAndError(Material::ERR_NOT_APPLICABLE);
    }

    public function testDeckTopIsTheValidTarget(): void {
        $this->seedDeckTop($this->deckTopCard);
        $this->createOp();
        $this->assertValidTarget($this->deckTopCard);
        $this->assertValidTargetCount(1);
    }

    public function testResolveAddsRedCrystalToDeckTop(): void {
        $this->seedDeckTop($this->deckTopCard);
        $this->assertEquals(0, $this->countRedCrystals($this->deckTopCard));
        $this->createOp();
        $this->call_resolve($this->deckTopCard);
        $this->assertEquals(1, $this->countRedCrystals($this->deckTopCard));
    }

    public function testProgressStacks(): void {
        $this->seedDeckTop($this->deckTopCard);
        for ($i = 1; $i <= 3; $i++) {
            $this->createOp();
            $this->call_resolve($this->deckTopCard);
        }
        $this->assertEquals(3, $this->countRedCrystals($this->deckTopCard));
    }

    public function testProgressIgnoresDurabilityCap(): void {
        // card_equip_1_21 (Helmet) has durability=3. spendDurab would reject the 4th.
        // gainTracker must allow it.
        $helmet = "card_equip_1_21";
        $this->seedDeckTop($helmet);
        for ($i = 1; $i <= 5; $i++) {
            $this->createOp();
            $this->call_resolve($helmet);
        }
        $this->assertEquals(5, $this->countRedCrystals($helmet));
    }

    public function testCrystalsTakenFromSupply(): void {
        $this->seedDeckTop($this->deckTopCard);
        $supplyBefore = $this->countRedCrystals("supply_crystal_red");
        $this->createOp();
        $this->call_resolve($this->deckTopCard);
        $supplyAfter = $this->countRedCrystals("supply_crystal_red");
        $this->assertEquals($supplyBefore - 1, $supplyAfter);
    }

    public function testMultiplicityAddsManyAtOnce(): void {
        // NgainTracker (e.g. 5gainTracker) sets count=N → one resolve adds N crystals.
        $this->seedDeckTop($this->deckTopCard);
        $this->createOp(null, ["count" => 5]);
        $this->call_resolve($this->deckTopCard);
        $this->assertEquals(5, $this->countRedCrystals($this->deckTopCard));
    }

    public function testCountTrackerMathIdentifier(): void {
        // counter('countTracker>=N') reads the deck-top crystal count via Game::countTracker.
        $this->seedDeckTop($this->deckTopCard);
        $this->assertEquals(0, $this->game->evaluateExpression("countTracker", $this->owner));

        $this->game->effect_moveCrystals("hero_1", "red", 3, $this->deckTopCard);
        $this->assertEquals(3, $this->game->evaluateExpression("countTracker", $this->owner));
        $this->assertEquals(1, $this->game->evaluateExpression("countTracker>=3", $this->owner));
        $this->assertEquals(0, $this->game->evaluateExpression("countTracker>=4", $this->owner));
    }
}
