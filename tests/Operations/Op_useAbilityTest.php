<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Operations\Op_useAbility;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_useAbilityTest extends AbstractOpTestCase {
    /** card_ability_1_7 = Stitching I (hero 1, r=1heal(adj)) */
    private string $cardId = "card_ability_1_7";

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
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
        $this->game->tokens->moveToken($this->cardId, "tableau_" . PCOLOR);
        $op = $this->op;
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey($this->cardId, $moves);
        $this->assertEquals(0, $moves[$this->cardId]["q"]);
    }

    public function testPassiveCardSkipped(): void {
        // Eagle Eye I has r=passive
        $this->game->tokens->moveToken("card_ability_1_9", "tableau_" . PCOLOR);
        $op = $this->op;
        $moves = $op->getPossibleMoves();
        $this->assertArrayNotHasKey("card_ability_1_9", $moves);
    }

    public function testEmptyRCardSkipped(): void {
        // Find a card with empty r — Long Shot I has r=passive too, but Eagle Eye has no r field
        // Eagle Eye I: r=passive — already tested. Check card with r=""
        // All non-passive Bjorn abilities have r set, so test with a passive one
        $this->game->tokens->moveToken("card_ability_1_9", "tableau_" . PCOLOR);
        $op = $this->op;
        $this->assertTrue($op->isVoid());
    }

    public function testNotVoidWithUsableCard(): void {
        $this->game->tokens->moveToken($this->cardId, "tableau_" . PCOLOR);
        $op = $this->op;
        $this->assertFalse($op->isVoid());
    }

    public function testResolveQueuesRExpression(): void {
        $this->game->tokens->moveToken($this->cardId, "tableau_" . PCOLOR);
        $op = $this->op;
        $this->call_resolve($this->cardId);
        $pending = $this->game->machine->getTopOperations(PCOLOR);
        $this->assertNotEmpty($pending);
    }

    public function testEffectVoidReturnsError(): void {
        // Stitching r=1heal(adj) — remove all hero damage so heal is void
        $this->game->effect_moveCrystals("hero_1", "red", -3, "hero_1");
        $this->game->tokens->moveToken($this->cardId, "tableau_" . PCOLOR);
        $op = $this->op;
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey($this->cardId, $moves);
        $this->assertNotEquals(0, $moves[$this->cardId]["q"]);
    }

    public function testMultipleCardsOffered(): void {
        $this->game->tokens->moveToken($this->cardId, "tableau_" . PCOLOR); // Stitching I
        // Sure Shot I: r=3spendMana:3dealDamage(inRange) — needs mana on card
        $sureShotId = "card_ability_1_3";
        $this->game->tokens->moveToken($sureShotId, "tableau_" . PCOLOR);
        $op = $this->op;
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey($this->cardId, $moves);
        $this->assertArrayHasKey($sureShotId, $moves);
    }

    public function testCardWithoutTriggerCannotBeUsedTwice(): void {
        $this->game->tokens->moveToken($this->cardId, "tableau_" . PCOLOR);
        $op = $this->op;
        // First use — card should be available
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey($this->cardId, $moves);
        // Use the card
        $this->call_resolve($this->cardId);
        // Second use — card should no longer be available
        $op2 = $this->createOp();
        $moves2 = $op2->getPossibleMoves();
        $this->assertArrayNotHasKey($this->cardId, $moves2);
    }

    public function testPresetTargetReturnsDirectly(): void {
        $op = $this->createOp("useAbility", ["target" => $this->cardId]);
        $moves = $op->getPossibleMoves();
        $this->assertEquals([$this->cardId], $moves);
    }
}
