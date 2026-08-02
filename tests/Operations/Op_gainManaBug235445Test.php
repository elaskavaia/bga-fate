<?php

declare(strict_types=1);

/**
 * Investigation for BGA bug #235445 ("Alva's racial not asking for target").
 * Verdict: NOT A BUG, on both counts. These tests pin the correct behavior.
 *
 * Scenario from the report: Alva ends a move in a forest with two mana cards on her
 * tableau: Alva's Bracers (card_equip_2_23, holding 1 crystal) and Hail of Arrows II
 * (card_ability_2_4). The racial queues `gainMana`.
 *
 * 1. "No prompt / auto-assign" does not reproduce: with two mana cards the server
 *    prompts (testOffersChoiceNotAutoAssignWithTwoManaCards).
 * 2. A card holding as many crystals as its `mana` value is NOT "full" - the `mana`
 *    material field is per-turn mana GENERATION (see Op_upgrade::generateTurnMana),
 *    not storage capacity. Cards must accumulate beyond it: Bracers generates 1/turn
 *    but its activation costs 3 [MANA] (3spendMana). So offering Bracers at 1 crystal
 *    and stacking a 2nd is correct save-up behavior, not overflow. Designer ruling
 *    FORUM.md:475: "Mana generation has no limit".
 *
 * Generic gainMana coverage (multi-target prompt, stacking) also lives in Op_gainManaTest;
 * this file exists to pin the #235445 scenario specifically.
 */
final class Op_gainManaBug235445Test extends AbstractOpTestCase {
    private const BRACERS = "card_equip_2_23"; // mana generation 1, activation costs 3 mana
    private const HAIL_II = "card_ability_2_4"; // mana generation 2, activation costs 1-4 mana

    function getOperationType(): string {
        return "gainMana";
    }

    protected function setUp(): void {
        parent::setUp(); // init(1): Bjorn, owner = player 1
        // Remove Bjorn's default mana card so the target count reflects only the two we place.
        $this->game->tokens->moveToken("card_ability_1_3", "limbo");

        $tableau = $this->getPlayersTableau();
        $this->game->tokens->moveToken(self::BRACERS, $tableau);
        $this->game->tokens->moveToken(self::HAIL_II, $tableau);

        // Bracers holds 1 crystal, matching the reporter's screenshot.
        $green = array_keys($this->game->tokens->getTokensOfTypeInLocation("crystal_green", "supply_crystal_green"));
        $this->game->tokens->moveToken($green[0], self::BRACERS);
    }

    public function testOffersChoiceNotAutoAssignWithTwoManaCards(): void {
        $op = $this->createOp("?gainMana");
        $this->assertEqualsCanonicalizing([self::BRACERS, self::HAIL_II], $op->getArgsTarget());
        $this->assertFalse($op->isOneChoice(), "two mana cards -> not a single choice");
        $this->assertFalse($op->canResolveAutomatically(), "server prompts the player; it does not auto-assign");
    }

    public function testCardHoldingItsGenerationValueIsStillOffered(): void {
        $this->createOp("?gainMana");
        $this->assertEquals(1, $this->countGreenCrystals(self::BRACERS), "precondition: Bracers holds 1 crystal");
        $this->assertValidTarget(self::BRACERS, "no capacity concept: any mana card is a valid target");
    }

    public function testManaAccumulatesBeyondGenerationValue(): void {
        $this->createOp("gainMana");
        $this->call_resolve(self::BRACERS);
        $this->assertEquals(2, $this->countGreenCrystals(self::BRACERS), "saving up toward the 3-mana activation cost");
    }
}
