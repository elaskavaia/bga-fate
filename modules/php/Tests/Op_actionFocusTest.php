<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Operations\Op_actionFocus;
use Bga\Games\Fate\Tests\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_actionFocusTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        // Assign hero 1 (Bjorn) to PCOLOR with Sure Shot I (mana=1) and First Bow (no mana)
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_ability_1_3", "tableau_" . PCOLOR); // Sure Shot I, mana=1
        $this->game->tokens->moveToken("card_equip_1_15", "tableau_" . PCOLOR); // Bjorn's First Bow, no mana
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function createOp(): Op_actionFocus {
        /** @var Op_actionFocus */
        $op = $this->game->machine->instanciateOperation("actionFocus", PCOLOR);
        return $op;
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves
    // -------------------------------------------------------------------------

    public function testOnlyManaCardsAreTargets(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        // Sure Shot I has mana capacity — should be a target
        $this->assertArrayHasKey("card_ability_1_3", $moves);
        // First Bow has no mana capacity — should not be a target
        $this->assertArrayNotHasKey("card_equip_1_15", $moves);
        // Hero card has no mana — should not be a target
        $this->assertArrayNotHasKey("card_hero_1_1", $moves);
    }

    public function testManaCardStillTargetableWithExistingMana(): void {
        // Mana is unlimited — card with existing mana should still be targetable
        $this->game->tokens->moveToken("crystal_green_1", "card_ability_1_3");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("card_ability_1_3", $moves);
    }

    public function testNoManaCardsReturnsEmpty(): void {
        // Remove the mana card from tableau
        $this->game->tokens->moveToken("card_ability_1_3", "limbo");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    public function testMultipleManaCardsAllTargetable(): void {
        // Add Sure Shot II (mana=2) to tableau as well
        $this->game->tokens->moveToken("card_ability_1_4", "tableau_" . PCOLOR);
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("card_ability_1_3", $moves);
        $this->assertArrayHasKey("card_ability_1_4", $moves);
    }

    // -------------------------------------------------------------------------
    // resolve
    // -------------------------------------------------------------------------

    public function testResolveAddsManaToCard(): void {
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);

        $mana = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_ability_1_3");
        $this->assertCount(1, $mana);
    }

    public function testResolveTakesFromSupply(): void {
        $supplyBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", "supply_crystal_green"));

        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);

        $supplyAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", "supply_crystal_green"));
        $this->assertEquals($supplyBefore - 1, $supplyAfter);
    }

}
