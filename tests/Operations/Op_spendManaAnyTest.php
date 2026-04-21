<?php

declare(strict_types=1);

final class Op_spendManaAnyTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // setupGameTables seeds 1 mana on card_ability_1_3 (Sure Shot I). Top it up to 2.
        $this->game->effect_moveCrystals("hero_1", "green", 1, "card_ability_1_3");
    }

    private function getMana(string $cardId): int {
        return $this->countGreenCrystals($cardId);
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves: offers all tableau cards with at least 1 mana
    // -------------------------------------------------------------------------

    public function testOffersCardWithMana(): void {
        $this->createOp();
        $this->assertValidTarget("card_ability_1_3");
    }

    public function testExcludesCardWithNoMana(): void {
        // Drain Sure Shot I
        $this->game->effect_moveCrystals("hero_1", "green", -2, "card_ability_1_3");
        $this->createOp();
        $this->assertNoValidTargets();
    }

    public function testOffersMultipleCards(): void {
        // Seed 1 mana on a second tableau card (Long Shot I, card_ability_1_11)
        $color = $this->owner;
        $this->game->tokens->moveToken("card_ability_1_11", "tableau_$color");
        $this->game->effect_moveCrystals("hero_1", "green", 1, "card_ability_1_11");

        $this->createOp();
        $this->assertValidTarget("card_ability_1_3");
        $this->assertValidTarget("card_ability_1_11");
    }

    // -------------------------------------------------------------------------
    // resolve: single-count case (count=1) spends 1 mana and does not re-queue
    // -------------------------------------------------------------------------

    public function testResolveCount1Spends1Mana(): void {
        $this->assertEquals(2, $this->getMana("card_ability_1_3"));
        $this->createOp(); // default count=1
        $this->call_resolve("card_ability_1_3");
        $this->assertEquals(1, $this->getMana("card_ability_1_3"));
    }

    public function testResolveCount1DoesNotRequeue(): void {
        $this->createOp();
        $this->call_resolve("card_ability_1_3");
        $this->assertFalse((bool) $this->game->machine->findOperation($this->owner, "spendManaAny"));
    }

    // -------------------------------------------------------------------------
    // resolve: multi-count case re-queues with decremented count
    // -------------------------------------------------------------------------

    public function testResolveCount2Requeues(): void {
        $this->createOp("2spendManaAny");
        $this->call_resolve("card_ability_1_3");

        // 1 mana spent from card_ability_1_3
        $this->assertEquals(1, $this->getMana("card_ability_1_3"));

        // A spendManaAny op should be queued with count=1
        $row = $this->game->machine->findOperation($this->owner, "spendManaAny");
        $this->assertNotNull($row, "spendManaAny should be re-queued for remaining mana");
        $requeued = $this->game->machine->instantiateOperationFromDbRow($row);
        $this->assertEquals(1, (int) $requeued->getCount());
    }

    public function testResolveCount2AcrossDifferentCards(): void {
        // Seed a second card with 1 mana so the re-queue can pick a different card
        $color = $this->owner;
        $this->game->tokens->moveToken("card_ability_1_11", "tableau_$color");
        $this->game->effect_moveCrystals("hero_1", "green", 1, "card_ability_1_11");

        // First resolve: spend from card_ability_1_3
        $this->createOp("2spendManaAny");
        $this->call_resolve("card_ability_1_3");
        $this->assertEquals(1, $this->getMana("card_ability_1_3"));
        $this->assertEquals(1, $this->getMana("card_ability_1_11"));

        // Instantiate + resolve the re-queued op, this time picking the other card
        $row = $this->game->machine->findOperation($this->owner, "spendManaAny");
        $this->assertNotNull($row);
        $this->op = $this->game->machine->instantiateOperationFromDbRow($row);
        $this->call_resolve("card_ability_1_11");

        $this->assertEquals(1, $this->getMana("card_ability_1_3"));
        $this->assertEquals(0, $this->getMana("card_ability_1_11"));
    }
}
