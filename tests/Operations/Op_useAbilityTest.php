<?php

declare(strict_types=1);

/**
 * Tests for Op_useAbility — filters to ability and hero cards only.
 */
final class Op_useAbilityTest extends AbstractOpTestCase {
    /** card_ability_1_7 = Stitching I (hero 1, r=1heal(adj)) */
    private string $abilityCard = "card_ability_1_7";

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // Add damage so heal(adj) is not void
        $this->game->effect_moveCrystals("hero_1", "red", 3, "hero_1");
    }

    public function testAbilityCardIsValidTarget(): void {
        $this->game->tokens->moveToken($this->abilityCard, $this->getPlayersTableau());
        $this->assertValidTarget($this->abilityCard);
    }

    public function testHeroCardIncludedInCandidates(): void {
        // Hero card (card_hero_1_1) has on=roll — not offered as free action (no trigger match),
        // but IS included in candidate list (not filtered out by type).
        // When triggered during roll, it should be offered.
        $this->createOp("useAbility", ["on" => "roll"]);
        $this->assertValidTarget("card_hero_1_1");
    }

    public function testEquipmentCardExcluded(): void {
        $this->game->tokens->moveToken("card_equip_1_19", $this->getPlayersTableau());
        $this->assertNotValidTarget("card_equip_1_19");
    }

    public function testEventCardExcluded(): void {
        $this->game->tokens->moveToken("card_event_1_27", "hand_" . $this->owner);
        $this->assertNotValidTarget("card_event_1_27");
    }

    public function testMultipleAbilitiesOffered(): void {
        $this->game->tokens->moveToken($this->abilityCard, $this->getPlayersTableau()); // Stitching I
        // Sure Shot I: r=3spendMana:3dealDamage(inRange) — needs mana + monster
        $sureShotId = "card_ability_1_3";
        $this->game->tokens->moveToken($sureShotId, $this->getPlayersTableau());
        $this->game->effect_moveCrystals("hero_1", "green", 3, $sureShotId, ["message" => ""]);
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->assertValidTarget($this->abilityCard);
        $this->assertValidTarget($sureShotId);
    }
}
