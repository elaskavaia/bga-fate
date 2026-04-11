<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_turnEnd;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_turnEndTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        // setupGameTables already puts card_hero_1_1, card_ability_1_3 (Sure Shot I, mana=1),
        // and card_equip_1_15 on tableau, and seeds 1 mana on Sure Shot I — drain that mana
        // so tests can reason about zero-start mana generation.
        foreach (array_keys($this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_ability_1_3")) as $key) {
            $this->game->tokens->moveToken($key, "supply_crystal_green");
        }
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // Place action markers
        $this->game->tokens->moveToken("marker_" . $this->owner . "_1", "aslot_" . $this->owner . "_actionPractice");
        $this->game->tokens->moveToken("marker_" . $this->owner . "_2", "aslot_" . $this->owner . "_actionMove");
    }

    // -------------------------------------------------------------------------
    // Mana generation
    // -------------------------------------------------------------------------

    public function testManaGeneratedForCardWithManaField(): void {
        $op = $this->op;
        $this->call_resolve();

        // Sure Shot I has mana=1, should get 1 green crystal
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_ability_1_3");
        $this->assertCount(1, $crystals);
    }

    public function testNoManaGeneratedForCardWithoutManaField(): void {
        $op = $this->op;
        $this->call_resolve();

        // First Bow has no mana field — should get no green crystals
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_equip_1_15");
        $this->assertCount(0, $crystals);
    }

    public function testManaAccumulatesAcrossTurns(): void {
        // Pre-place 1 mana on the card
        $this->game->tokens->pickTokensForLocation(1, "supply_crystal_green", "card_ability_1_3");

        $op = $this->op;
        $this->call_resolve();

        // Should now have 2 green crystals
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_ability_1_3");
        $this->assertCount(2, $crystals);
    }

    public function testMana2CardGenerates2(): void {
        // Add Sure Shot II (mana=2) to tableau
        $this->game->tokens->moveToken("card_ability_1_4", "tableau_" . $this->owner);

        $op = $this->op;
        $this->call_resolve();

        // Sure Shot II should get 2 green crystals
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_ability_1_4");
        $this->assertCount(2, $crystals);

        // Sure Shot I should still get 1
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_ability_1_3");
        $this->assertCount(1, $crystals);
    }

    public function testHeroCardGetsNoMana(): void {
        $op = $this->op;
        $this->call_resolve();

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_hero_1_1");
        $this->assertCount(0, $crystals);
    }

    // -------------------------------------------------------------------------
    // Action markers reset
    // -------------------------------------------------------------------------

    public function testActionMarkersResetToEmpty(): void {
        $op = $this->op;
        $this->call_resolve();

        $loc1 = $this->game->tokens->getTokenLocation("marker_" . $this->owner . "_1");
        $loc2 = $this->game->tokens->getTokenLocation("marker_" . $this->owner . "_2");
        $this->assertEquals("aslot_" . $this->owner . "_empty_1", $loc1);
        $this->assertEquals("aslot_" . $this->owner . "_empty_2", $loc2);
    }

    // -------------------------------------------------------------------------
    // Battle dice cleanup
    // -------------------------------------------------------------------------

    public function testBattleDiceReturnedToSupply(): void {
        // Place some dice on display_battle
        $this->game->tokens->moveToken("die_attack_1", "display_battle");
        $this->game->tokens->moveToken("die_attack_2", "display_battle");

        $op = $this->op;
        $this->call_resolve();

        $this->assertEquals("supply_die_attack", $this->game->tokens->getTokenLocation("die_attack_1"));
        $this->assertEquals("supply_die_attack", $this->game->tokens->getTokenLocation("die_attack_2"));
    }

    public function testNoBattleDiceNoCrash(): void {
        // No dice on display_battle — should not error
        $op = $this->op;
        $this->call_resolve();
        $this->assertTrue(true); // no exception
    }
}
