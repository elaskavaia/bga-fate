<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

final class Op_gainDamageTest extends AbstractOpTestCase {
    /** card_equip_1_21 = Helmet (hero 1, durability 3) */
    private string $cardId = "card_equip_1_21";

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken($this->cardId, "tableau_" . $this->owner);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function getDamage(string $cardId): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $cardId));
    }

    public function testCardWithRoomIsValid(): void {
        $this->op = $this->createOp(null, ["card" => $this->cardId]);
        $moves = $this->op->getPossibleMoves();
        $this->assertArrayHasKey($this->cardId, $moves);
        $this->assertEquals(Material::RET_OK, $moves[$this->cardId]["q"]);
    }

    public function testCardAtMaxDurabilityNotApplicable(): void {
        // Helmet has durability 3, add 3 red crystals
        $this->game->effect_moveCrystals("hero_1", "red", 3, $this->cardId);
        $this->op = $this->createOp(null, ["card" => $this->cardId]);
        $moves = $this->op->getPossibleMoves();
        $this->assertArrayHasKey($this->cardId, $moves);
        $this->assertEquals(Material::ERR_NOT_APPLICABLE, $moves[$this->cardId]["q"]);
    }

    public function testNoCardReturnsError(): void {
        $op = $this->createOp("gainDamage");
        $moves = $op->getPossibleMoves();
        $this->assertNotEquals(0, $moves["q"] ?? 0);
    }

    public function testResolveAdds1Damage(): void {
        $this->assertEquals(0, $this->getDamage($this->cardId));
        $this->op = $this->createOp(null, ["card" => $this->cardId]);
        $this->call_resolve($this->cardId);
        $this->assertEquals(1, $this->getDamage($this->cardId));
    }

    public function testResolveTakesFromSupply(): void {
        $supplyBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", "supply_crystal_red"));
        $this->op = $this->createOp(null, ["card" => $this->cardId]);
        $this->call_resolve($this->cardId);
        $supplyAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", "supply_crystal_red"));
        $this->assertEquals($supplyBefore - 1, $supplyAfter);
    }

    public function testDamageStacks(): void {
        $op1 = $this->createOp(null, ["card" => $this->cardId]);
        $op1->action_resolve([Operation::ARG_TARGET => $this->cardId]);
        $op2 = $this->createOp(null, ["card" => $this->cardId]);
        $op2->action_resolve([Operation::ARG_TARGET => $this->cardId]);
        $this->assertEquals(2, $this->getDamage($this->cardId));
    }

    public function testPartialDurabilityStillValid(): void {
        // Add 2 of 3 durability
        $this->game->effect_moveCrystals("hero_1", "red", 2, $this->cardId);
        $this->op = $this->createOp(null, ["card" => $this->cardId]);
        $moves = $this->op->getPossibleMoves();
        $this->assertEquals(Material::RET_OK, $moves[$this->cardId]["q"]);
    }
}
