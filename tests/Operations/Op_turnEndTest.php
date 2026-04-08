<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_turnEnd;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_turnEndTest extends TestCase {
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
        // Place action markers
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_1", "aslot_" . PCOLOR . "_actionPractice");
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_2", "aslot_" . PCOLOR . "_actionMove");
    }

    private function createOp(): Op_turnEnd {
        /** @var Op_turnEnd */
        $op = $this->game->machine->instanciateOperation("turnEnd", PCOLOR);
        return $op;
    }

    // -------------------------------------------------------------------------
    // Mana generation
    // -------------------------------------------------------------------------

    public function testManaGeneratedForCardWithManaField(): void {
        $op = $this->createOp();
        $op->action_resolve([]);

        // Sure Shot I has mana=1, should get 1 green crystal
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_ability_1_3");
        $this->assertCount(1, $crystals);
    }

    public function testNoManaGeneratedForCardWithoutManaField(): void {
        $op = $this->createOp();
        $op->action_resolve([]);

        // First Bow has no mana field — should get no green crystals
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_equip_1_15");
        $this->assertCount(0, $crystals);
    }

    public function testManaAccumulatesAcrossTurns(): void {
        // Pre-place 1 mana on the card
        $this->game->tokens->pickTokensForLocation(1, "supply_crystal_green", "card_ability_1_3");

        $op = $this->createOp();
        $op->action_resolve([]);

        // Should now have 2 green crystals
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_ability_1_3");
        $this->assertCount(2, $crystals);
    }

    public function testMana2CardGenerates2(): void {
        // Add Sure Shot II (mana=2) to tableau
        $this->game->tokens->moveToken("card_ability_1_4", "tableau_" . PCOLOR);

        $op = $this->createOp();
        $op->action_resolve([]);

        // Sure Shot II should get 2 green crystals
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_ability_1_4");
        $this->assertCount(2, $crystals);

        // Sure Shot I should still get 1
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_ability_1_3");
        $this->assertCount(1, $crystals);
    }

    public function testHeroCardGetsNoMana(): void {
        $op = $this->createOp();
        $op->action_resolve([]);

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_hero_1_1");
        $this->assertCount(0, $crystals);
    }

    // -------------------------------------------------------------------------
    // Action markers reset
    // -------------------------------------------------------------------------

    public function testActionMarkersResetToEmpty(): void {
        $op = $this->createOp();
        $op->action_resolve([]);

        $loc1 = $this->game->tokens->getTokenLocation("marker_" . PCOLOR . "_1");
        $loc2 = $this->game->tokens->getTokenLocation("marker_" . PCOLOR . "_2");
        $this->assertEquals("aslot_" . PCOLOR . "_empty_1", $loc1);
        $this->assertEquals("aslot_" . PCOLOR . "_empty_2", $loc2);
    }

    // -------------------------------------------------------------------------
    // Battle dice cleanup
    // -------------------------------------------------------------------------

    public function testBattleDiceReturnedToSupply(): void {
        // Place some dice on display_battle
        $this->game->tokens->moveToken("die_attack_1", "display_battle");
        $this->game->tokens->moveToken("die_attack_2", "display_battle");

        $op = $this->createOp();
        $op->action_resolve([]);

        $this->assertEquals("supply_die_attack", $this->game->tokens->getTokenLocation("die_attack_1"));
        $this->assertEquals("supply_die_attack", $this->game->tokens->getTokenLocation("die_attack_2"));
    }

    public function testNoBattleDiceNoCrash(): void {
        // No dice on display_battle — should not error
        $op = $this->createOp();
        $op->action_resolve([]);
        $this->assertTrue(true); // no exception
    }
}
