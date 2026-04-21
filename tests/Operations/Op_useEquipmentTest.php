<?php

declare(strict_types=1);

/**
 * Tests for Op_useEquipment — filters to equipment cards only.
 */
final class Op_useEquipmentTest extends AbstractOpTestCase {
    /** card_equip_1_19 = Leather Purse (hero 1, durability 3, r=spendDurab:2heal(adj)) */
    private string $equipCard = "card_equip_1_19";
    function getOperationType(): string {
        return "useCard";
    }
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // Add damage so heal(adj) is not void
        $this->game->effect_moveCrystals("hero_1", "red", 3, "hero_1");
    }

    public function testEquipmentCardIsValidTarget(): void {
        $this->game->tokens->moveToken($this->equipCard, $this->getPlayersTableau());
        $this->assertValidTarget($this->equipCard);
    }

    public function testHeroCardExcluded(): void {
        // card_hero_1_1 is on tableau from setup
        $this->assertNotValidTarget("card_hero_1_1");
    }

    public function testEmptyRCardSkipped(): void {
        // Bjorn's First Bow has r=""
        $this->game->tokens->moveToken("card_equip_1_15", $this->getPlayersTableau());
        $this->assertNotValidTarget("card_equip_1_15");
    }

    public function testMaxDurabilityReturnsError(): void {
        $this->game->tokens->moveToken($this->equipCard, $this->getPlayersTableau());
        // Leather Purse has durability 3, fill it up
        $this->game->effect_moveCrystals("hero_1", "red", 3, $this->equipCard);
        $this->assertNotValidTarget($this->equipCard);
    }

    public function testMultipleEquipmentOffered(): void {
        $this->game->tokens->moveToken($this->equipCard, $this->getPlayersTableau()); // Leather Purse
        $this->game->tokens->moveToken("card_equip_1_17", $this->getPlayersTableau()); // Throwing Axes
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->assertValidTarget($this->equipCard);
        $this->assertValidTarget("card_equip_1_17");
    }
}
