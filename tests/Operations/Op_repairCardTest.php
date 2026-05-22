<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;

final class Op_repairCardTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();

        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // Place some equipment on tableau
        $this->game->tokens->moveToken("card_equip_1_21", $this->getPlayersTableau());
        $this->game->tokens->moveToken("card_equip_1_23", $this->getPlayersTableau());
    }

    private function addDamageToCard(string $cardId, int $amount): void {
        $this->game->effect_moveCrystals($cardId, "red", $amount, $cardId, ["message" => ""]);
    }

    private function getCardDamage(string $cardId): int {
        return $this->countRedCrystals($cardId);
    }

    private function getQueuedOp(): ?array {
        $ops = $this->game->machine->getTopOperations(PCOLOR);
        return $ops ? reset($ops) : null;
    }

    public function testNoDamagedCardsAllNotApplicable(): void {
        // Equipment cards should be listed but not applicable
        $this->assertTargetError("card_equip_1_21", Material::ERR_NOT_APPLICABLE);
    }

    public function testDamagedCardIsValid(): void {
        $this->addDamageToCard("card_equip_1_21", 2);
        $this->assertValidTarget("card_equip_1_21");
    }

    public function testUndamagedCardNotApplicable(): void {
        $this->addDamageToCard("card_equip_1_21", 2);
        // card_equip_1_23 has no damage
        $this->assertTargetError("card_equip_1_23", Material::ERR_NOT_APPLICABLE);
    }

    public function testResolveRemovesAllDamage(): void {
        $this->addDamageToCard("card_equip_1_21", 3);
        $this->assertEquals(3, $this->getCardDamage("card_equip_1_21"));
        $this->createOp("repairCard(max)");
        $this->call_resolve("card_equip_1_21");
        $this->assertEquals(0, $this->getCardDamage("card_equip_1_21"));
    }

    public function testResolveDoesNotAffectOtherCards(): void {
        $this->addDamageToCard("card_equip_1_21", 2);
        $this->addDamageToCard("card_equip_1_23", 1);
        $this->call_resolve("card_equip_1_21");
        // Other card should still have damage
        $this->assertEquals(1, $this->getCardDamage("card_equip_1_23"));
    }

    public function testMaxModeRemovesAllDamageIgnoringCount(): void {
        $this->addDamageToCard("card_equip_1_21", 5);
        // Even with a count prefix, "max" should remove all damage.
        $this->createOp("2repairCard(max)");
        $this->call_resolve("card_equip_1_21");
        $this->assertEquals(0, $this->getCardDamage("card_equip_1_21"));
    }

    public function testLimitedCountRemovesOnlyUpToCount(): void {
        $this->addDamageToCard("card_equip_1_21", 3);
        $this->createOp("2repairCard");
        $this->call_resolve("card_equip_1_21");
        // Should remove only 2 of 3 damage
        $this->assertEquals(1, $this->getCardDamage("card_equip_1_21"));
    }

    public function testSplitPickAcrossMultipleDamagedCards(): void {
        // Two damaged candidates → 2repairCard removes 1 from the pick and re-queues 1repairCard
        // for the remaining unit, letting the player split across targets.
        $this->addDamageToCard("card_equip_1_21", 2);
        $this->addDamageToCard("card_equip_1_23", 1);
        $this->createOp("2repairCard");
        $this->call_resolve("card_equip_1_21");

        $this->assertEquals(1, $this->getCardDamage("card_equip_1_21"));
        $this->assertEquals(1, $this->getCardDamage("card_equip_1_23"));

        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("repairCard", $queued["type"]);
    }

    public function testPresetTargetReturnsOnlyThatTarget(): void {
        $this->addDamageToCard("card_equip_1_21", 2);
        $this->addDamageToCard("card_equip_1_23", 1);
        $this->createOp("5repairCard", ["target" => "card_equip_1_21"]);
        $this->assertValidTargetCount(1);
        $this->assertValidTarget("card_equip_1_21");
    }

    /**
     * Regression: with a preset target AND multiple damaged candidates, the op
     * must apply only 1 damage to the picked target and re-queue with the
     * remaining count and cleared target so the player can pick again
     * (e.g. for actionMend's "remove X damage from heroes/cards" flow).
     */
    public function testPresetTargetWithMultipleDamagedCandidatesSplits(): void {
        $this->addDamageToCard("card_equip_1_21", 3);
        $this->addDamageToCard("card_equip_1_23", 2);
        $this->createOp("5repairCard", ["target" => "card_equip_1_21"]);
        $this->call_resolve("card_equip_1_21");

        // Only 1 damage removed from the picked card; the other card untouched
        $this->assertEquals(2, $this->getCardDamage("card_equip_1_21"));
        $this->assertEquals(2, $this->getCardDamage("card_equip_1_23"));

        // A new repairCard is queued with count-1 and no preset target
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("repairCard", $queued["type"]);
        $data = is_string($queued["data"] ?? null) ? (json_decode($queued["data"], true) ?? []) : ($queued["data"] ?? []);
        $this->assertArrayNotHasKey("target", $data, "preset target should be cleared on re-queue");
    }

    public function testAllModeReturnsConfirmTarget(): void {
        $this->addDamageToCard("card_equip_1_21", 2);
        $this->createOp("repairCard(all)");
        // In 'all' mode there is no per-card selection — a single "confirm" target represents the action.
        $this->assertValidTarget("confirm");
    }

    public function testAllModeRepairsEveryDamagedCard(): void {
        $this->addDamageToCard("card_equip_1_21", 2);
        $this->addDamageToCard("card_equip_1_23", 1);
        $this->createOp("1repairCard(all)");
        $this->call_resolve("confirm");
        $this->assertEquals(1, $this->getCardDamage("card_equip_1_21"));
        $this->assertEquals(0, $this->getCardDamage("card_equip_1_23"));
    }

    public function testAllModeLeavesUndamagedCardsAtZero(): void {
        $this->addDamageToCard("card_equip_1_21", 1);
        // card_equip_1_23 has no damage
        $this->createOp("1repairCard(all)");
        $this->call_resolve("confirm");
        $this->assertEquals(0, $this->getCardDamage("card_equip_1_21"));
        // Must not go negative
        $this->assertEquals(0, $this->getCardDamage("card_equip_1_23"));
    }

    public function testAllModeCapsPerCard(): void {
        $this->addDamageToCard("card_equip_1_21", 1);
        $this->addDamageToCard("card_equip_1_23", 3);
        // Count=2 per card — cap at existing damage on first, remove 2 of 3 on second.
        $this->createOp("2repairCard(all)");
        $this->call_resolve("confirm");
        $this->assertEquals(0, $this->getCardDamage("card_equip_1_21"));
        $this->assertEquals(1, $this->getCardDamage("card_equip_1_23"));
    }

    public function testAllModeNoDamagedCardsResolvesCleanly(): void {
        // No pre-damage on any card.
        $this->createOp("1repairCard(all)");
        $this->call_resolve("confirm");
        $this->assertEquals(0, $this->getCardDamage("card_equip_1_21"));
        $this->assertEquals(0, $this->getCardDamage("card_equip_1_23"));
    }

    // -------------------------------------------------------------------------
    // "adj" mode — cross-tableau repair (DESIGN.md #8, Stitching).
    // Cards on the tableau of any hero within range 1 of the acting hero are
    // also eligible.
    // -------------------------------------------------------------------------

    public function testAdjModeIncludesOwnCards(): void {
        $this->addDamageToCard("card_equip_1_21", 1);
        $this->createOp("repairCard(adj)");
        $this->assertValidTarget("card_equip_1_21", "Own damaged card is still a target under adj mode");
    }

    /** Register Alva as a second player so getHeroOwner can resolve hero_2 → BCOLOR. */
    private function registerAlvaAsSecondPlayer(): void {
        $this->game->setPlayersNumber(2);
        $this->game->tokens->moveToken("card_hero_2_1", "tableau_" . BCOLOR);
    }

    public function testAdjModePicksUpAdjacentHeroEquipment(): void {
        $this->registerAlvaAsSecondPlayer();
        // Park Alva on a hex adjacent to Bjorn at hex_11_8.
        $this->game->tokens->moveToken("hero_2", "hex_12_8");
        $alvaEquip = "card_equip_2_15";
        $this->game->tokens->moveToken($alvaEquip, "tableau_" . BCOLOR);
        $this->addDamageToCard($alvaEquip, 1);
        $this->game->hexMap->invalidateOccupancy();

        $this->createOp("repairCard(adj)");
        $this->assertValidTarget($alvaEquip, "Adjacent Alva's equipment must be reachable via repairCard(adj)");
    }

    public function testAdjModeExcludesNonAdjacentHeroEquipment(): void {
        $this->registerAlvaAsSecondPlayer();
        // Park Alva far away — distance > 1 from Bjorn at hex_11_8.
        $this->game->tokens->moveToken("hero_2", "hex_5_5");
        $alvaEquip = "card_equip_2_15";
        $this->game->tokens->moveToken($alvaEquip, "tableau_" . BCOLOR);
        $this->addDamageToCard($alvaEquip, 1);
        $this->game->hexMap->invalidateOccupancy();

        $this->createOp("repairCard(adj)");
        $this->assertNotValidTarget($alvaEquip, "Out-of-range hero's equipment is not eligible");
    }
}
