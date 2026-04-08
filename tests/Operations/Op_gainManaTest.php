<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Operations\Op_gainMana;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_gainManaTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        // Assign hero 1 (Bjorn) to PCOLOR
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_ability_1_3", "tableau_" . PCOLOR); // Sure Shot I
        $this->game->tokens->moveToken("card_equip_1_15", "tableau_" . PCOLOR); // First Bow
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function createOp(string $expr = "gainMana"): Op_gainMana {
        /** @var Op_gainMana */
        $op = $this->game->machine->instanciateOperation($expr, PCOLOR);
        return $op;
    }

    private function getMana(string $cardId): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", $cardId));
    }

    public function testOnlyManaCardsAreTargets(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        // Sure Shot I has mana field — should be a target
        $this->assertArrayHasKey("card_ability_1_3", $moves);
        // First Bow has no mana field — should not be a target
        $this->assertArrayNotHasKey("card_equip_1_15", $moves);
        // Hero card has no mana field — should not be a target
        $this->assertArrayNotHasKey("card_hero_1_1", $moves);
    }

    public function testResolveAdds1Mana(): void {
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);
        $this->assertEquals(1, $this->getMana("card_ability_1_3"));
    }

    public function testResolveAdds2Mana(): void {
        $op = $this->createOp("2gainMana");
        $op->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);
        $this->assertEquals(2, $this->getMana("card_ability_1_3"));
    }

    public function testResolveTakesFromSupply(): void {
        $supplyBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", "supply_crystal_green"));
        $op = $this->createOp("2gainMana");
        $op->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);
        $supplyAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", "supply_crystal_green"));
        $this->assertEquals($supplyBefore - 2, $supplyAfter);
    }

    public function testPresetTargetReturnsOnlyThatTarget(): void {
        /** @var Op_gainMana */
        $op = $this->game->machine->instanciateOperation("2gainMana", PCOLOR, ["target" => "card_ability_1_3"]);
        $moves = $op->getPossibleMoves();
        $this->assertCount(1, $moves);
        $this->assertArrayHasKey("card_ability_1_3", $moves);
    }

    public function testManaStacksOnCard(): void {
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);
        $op2 = $this->createOp("2gainMana");
        $op2->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);
        $this->assertEquals(3, $this->getMana("card_ability_1_3"));
    }
}
