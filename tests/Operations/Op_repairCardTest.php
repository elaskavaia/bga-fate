<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_repairCard;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_repairCardTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        // Assign hero 1 (Bjorn) to PCOLOR
        $this->game->tokens->moveToken("card_hero_1_1", $this->getPlayersTableau());
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
        $this->op = $this->createOp("99repairCard");
        $this->call_resolve("card_equip_1_21");
        $this->assertEquals(0, $this->getCardDamage("card_equip_1_21"));
    }

    public function testResolveDoesNotAffectOtherCards(): void {
        $this->addDamageToCard("card_equip_1_21", 2);
        $this->addDamageToCard("card_equip_1_23", 1);
        $op = $this->op;
        $this->call_resolve("card_equip_1_21");
        // Other card should still have damage
        $this->assertEquals(1, $this->getCardDamage("card_equip_1_23"));
    }

    public function testLimitedCountRemovesOnlyUpToCount(): void {
        $this->addDamageToCard("card_equip_1_21", 3);
        $this->op = $this->createOp("2repairCard");
        $this->call_resolve("card_equip_1_21");
        // Should remove only 2 of 3 damage
        $this->assertEquals(1, $this->getCardDamage("card_equip_1_21"));
    }

    public function testPresetTargetReturnsOnlyThatTarget(): void {
        $this->addDamageToCard("card_equip_1_21", 2);
        $this->addDamageToCard("card_equip_1_23", 1);
        $this->op = $this->createOp("5repairCard", ["target" => "card_equip_1_21"]);
        $this->assertValidTargetCount(1);
        $this->assertValidTarget("card_equip_1_21");
    }

    public function testAllModeReturnsConfirmTarget(): void {
        $this->addDamageToCard("card_equip_1_21", 2);
        $this->op = $this->createOp("1repairCard(all)");
        // In 'all' mode there is no per-card selection — a single "confirm" target represents the action.
        $this->assertValidTarget("confirm");
    }

    public function testAllModeRepairsEveryDamagedCard(): void {
        $this->addDamageToCard("card_equip_1_21", 2);
        $this->addDamageToCard("card_equip_1_23", 1);
        $this->op = $this->createOp("1repairCard(all)");
        $this->call_resolve("confirm");
        $this->assertEquals(1, $this->getCardDamage("card_equip_1_21"));
        $this->assertEquals(0, $this->getCardDamage("card_equip_1_23"));
    }

    public function testAllModeLeavesUndamagedCardsAtZero(): void {
        $this->addDamageToCard("card_equip_1_21", 1);
        // card_equip_1_23 has no damage
        $this->op = $this->createOp("1repairCard(all)");
        $this->call_resolve("confirm");
        $this->assertEquals(0, $this->getCardDamage("card_equip_1_21"));
        // Must not go negative
        $this->assertEquals(0, $this->getCardDamage("card_equip_1_23"));
    }

    public function testAllModeCapsPerCard(): void {
        $this->addDamageToCard("card_equip_1_21", 1);
        $this->addDamageToCard("card_equip_1_23", 3);
        // Count=2 per card — cap at existing damage on first, remove 2 of 3 on second.
        $this->op = $this->createOp("2repairCard(all)");
        $this->call_resolve("confirm");
        $this->assertEquals(0, $this->getCardDamage("card_equip_1_21"));
        $this->assertEquals(1, $this->getCardDamage("card_equip_1_23"));
    }

    public function testAllModeNoDamagedCardsResolvesCleanly(): void {
        // No pre-damage on any card.
        $this->op = $this->createOp("1repairCard(all)");
        $this->call_resolve("confirm");
        $this->assertEquals(0, $this->getCardDamage("card_equip_1_21"));
        $this->assertEquals(0, $this->getCardDamage("card_equip_1_23"));
    }
}
