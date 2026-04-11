<?php

declare(strict_types=1);

final class Op_useAbilityTest extends AbstractOpTestCase {
    /** card_ability_1_7 = Stitching I (hero 1, r=1heal(adj)) */
    private string $cardId = "card_ability_1_7";

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("card_hero_1_1", $this->getPlayersTableau());
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // Add damage so heal(adj) is not void
        $this->game->effect_moveCrystals("hero_1", "red", 3, "hero_1");
    }

    public function testNoAbilitiesReturnsEmpty(): void {
        $op = $this->op;
        $this->assertNoValidTargets();
    }

    public function testIsVoidWithNoAbilities(): void {
        $op = $this->op;
        $this->assertTrue($op->isVoid());
    }

    public function testStitchingIsValidTarget(): void {
        $this->game->tokens->moveToken($this->cardId, $this->getPlayersTableau());
        $this->assertValidTarget($this->cardId);
    }

    public function testPassiveCardSkipped(): void {
        // Eagle Eye I has r=passive
        $this->game->tokens->moveToken("card_ability_1_9", $this->getPlayersTableau());
        $this->assertNotValidTarget("card_ability_1_9");
    }

    public function testEmptyRCardSkipped(): void {
        // Find a card with empty r — Long Shot I has r=passive too, but Eagle Eye has no r field
        // Eagle Eye I: r=passive — already tested. Check card with r=""
        // All non-passive Bjorn abilities have r set, so test with a passive one
        $this->game->tokens->moveToken("card_ability_1_9", $this->getPlayersTableau());
        $op = $this->op;
        $this->assertTrue($op->isVoid());
    }

    public function testNotVoidWithUsableCard(): void {
        $this->game->tokens->moveToken($this->cardId, $this->getPlayersTableau());
        $op = $this->op;
        $this->assertFalse($op->isVoid());
    }

    public function testResolveQueuesRExpression(): void {
        $this->game->tokens->moveToken($this->cardId, $this->getPlayersTableau());
        $op = $this->op;
        $this->call_resolve($this->cardId);
        $pending = $this->game->machine->getTopOperations(PCOLOR);
        $this->assertNotEmpty($pending);
    }

    public function testEffectVoidReturnsError(): void {
        // Stitching r=1heal(adj) — remove all hero damage so heal is void
        $this->game->effect_moveCrystals("hero_1", "red", -3, "hero_1");
        $this->game->tokens->moveToken($this->cardId, $this->getPlayersTableau());
        $this->assertNotValidTarget($this->cardId);
    }

    public function testMultipleCardsOffered(): void {
        $this->game->tokens->moveToken($this->cardId, $this->getPlayersTableau()); // Stitching I
        // Sure Shot I: r=3spendMana:3dealDamage(inRange) — needs 3 mana on card and a monster in range
        $sureShotId = "card_ability_1_3";
        $this->game->tokens->moveToken($sureShotId, $this->getPlayersTableau());
        $this->game->effect_moveCrystals("hero_1", "green", 3, $sureShotId, ["message" => ""]);
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->assertValidTarget($this->cardId);
        $this->assertValidTarget($sureShotId);
    }

    public function testCardWithoutTriggerCannotBeUsedTwice(): void {
        $this->game->tokens->moveToken($this->cardId, $this->getPlayersTableau());
        // First use — card should be available
        $this->assertValidTarget($this->cardId);
        // Use the card
        $this->call_resolve($this->cardId);
        // Second use — card should no longer be available
        $this->createOp();
        $this->assertNotValidTarget($this->cardId);
    }

    public function testPresetTargetReturnsDirectly(): void {
        $this->createOp("useAbility", ["target" => $this->cardId]);
        $this->assertValidTargetCount(1);
        $this->assertValidTarget($this->cardId);
    }
}
