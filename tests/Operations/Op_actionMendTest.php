<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_actionMend;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_actionMendTest extends AbstractOpTestCase {
    private const TUNIC = "card_equip_1_23";

    protected function setUp(): void {
        parent::setUp();
        // Assign hero 1 (Bjorn) to PCOLOR
        $this->game->tokens->moveToken("card_hero_1_1", $this->getPlayersTableau());
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // Place equipment on tableau
        $this->game->tokens->moveToken("card_equip_1_21", $this->getPlayersTableau());
    }

    private function addDamage(string $tokenId, int $amount): void {
        $this->game->effect_moveCrystals($tokenId, "red", $amount, $tokenId, ["message" => ""]);
    }

    private function getQueuedOp(): ?array {
        $ops = $this->game->machine->getTopOperations(PCOLOR);
        return $ops ? reset($ops) : null;
    }

    // --- Outside Grimheim ---

    public function testMendQueuesHealForHero(): void {
        $this->addDamage("hero_1", 4);
        $op = $this->op;
        $this->call_resolve("hex_11_8");
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("2heal", $queued["type"]);
    }

    public function testMendNotAvailableWithZeroDamage(): void {
        $this->assertNoValidTargetsAndError(Material::ERR_NOT_APPLICABLE);
    }

    public function testMendAvailableWithDamage(): void {
        $this->addDamage("hero_1", 2);
        $op = $this->op;
        $this->assertEquals(Material::RET_OK, $op->getErrorCode());
    }

    public function testMendOutsideGrimheimOnlyOffersHex(): void {
        $this->addDamage("hero_1", 2);
        $this->addDamage("card_equip_1_21", 1);
        $this->assertValidTarget("hex_11_8");
        $this->assertNotValidTarget("card_equip_1_21");
    }

    // --- In Grimheim ---

    public function testMendInGrimheimQueuesRemoveDamageForHero(): void {
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
        $this->addDamage("hero_1", 5);
        $op = $this->op;
        $this->call_resolve("hex_9_9");
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("5removeDamage", $queued["type"]);
    }

    public function testMendInGrimheimOffersHexAndCards(): void {
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
        $this->addDamage("hero_1", 2);
        $this->addDamage("card_equip_1_21", 1);
        $this->assertValidTarget("hex_9_9");
        $this->assertValidTarget("card_equip_1_21");
    }

    public function testMendInGrimheimQueuesRemoveDamageForCard(): void {
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
        $this->addDamage("card_equip_1_21", 2);
        $op = $this->op;
        $this->call_resolve("card_equip_1_21");
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("5removeDamage", $queued["type"]);
    }

    public function testMendInGrimheimAvailableWithOnlyCardDamage(): void {
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
        $this->addDamage("card_equip_1_21", 1);
        $op = $this->op;
        $this->assertEquals(Material::RET_OK, $op->getErrorCode());
    }

    /**
     * BGA #234119: Home Sewn Tunic (card_equip_1_23) - "Spend 1 mend action to remove all damage
     * from this card." A mend pick on the Tunic strips all of its damage at once, anywhere on the
     * map; inside Grimheim the rest of the 5 damage budget stays spendable on other targets.
     */
    private function placeTunic(string $heroHex): void {
        $this->game->tokens->moveToken(self::TUNIC, $this->getPlayersTableau());
        $this->game->tokens->moveToken("hero_1", $heroHex);
        $this->game->hexMap->invalidateOccupancy();
    }

    public function testMendOnTunicRemovesAllDamageInGrimheim(): void {
        $this->placeTunic("hex_9_9");
        $this->addDamage(self::TUNIC, 2);
        $this->addDamage("hero_1", 1); // second damaged target so mend is not single-choice

        $this->createOp("actionMend");
        $this->call_resolve(self::TUNIC); // spend the Mend action targeting the Tunic

        $top = $this->game->machine->createTopOperationFromDbForOwner();
        $this->assertNotNull($top, "Mend should queue a removeDamage op");
        $this->game->fakeUserAction($top, self::TUNIC); // pick the Tunic to repair

        $this->assertSame(0, $this->countRedCrystals(self::TUNIC));
    }

    public function testMendOnTunicKeepsRestOfGrimheimBudget(): void {
        $this->placeTunic("hex_9_9");
        $this->addDamage(self::TUNIC, 2);
        $this->addDamage("hero_1", 3);

        $this->createOp("actionMend");
        $this->call_resolve(self::TUNIC);
        $top = $this->game->machine->createTopOperationFromDbForOwner();
        $this->game->fakeUserAction($top, self::TUNIC);

        $remaining = $this->game->machine->createTopOperationFromDbForOwner();
        $this->assertNotNull($remaining, "3 of the 5 damage budget is left for other targets");
        $this->game->fakeUserAction($remaining, $this->game->tokens->getTokenLocation("hero_1"));
        $this->assertSame(0, $this->countRedCrystals("hero_1"));
    }

    public function testMendOnTunicOutsideGrimheim(): void {
        $this->placeTunic("hex_5_5");
        $this->addDamage(self::TUNIC, 3);
        $this->addDamage("hero_1", 1);

        $op = $this->createOp("actionMend");
        $this->assertArrayHasKey(self::TUNIC, $op->getArgsInfo(), "Tunic is repairable outside Grimheim");

        $this->call_resolve(self::TUNIC);
        $top = $this->game->machine->createTopOperationFromDbForOwner();
        $this->assertNotNull($top, "Mend should queue a removeDamage op");
        $this->game->fakeUserAction($top, self::TUNIC);

        $this->assertSame(0, $this->countRedCrystals(self::TUNIC));
        $this->assertSame(1, $this->countRedCrystals("hero_1"), "Tunic repair replaces the normal 2 heal");
    }

    public function testUndamagedTunicIsNotAMendTargetOutsideGrimheim(): void {
        $this->placeTunic("hex_5_5");
        $this->addDamage("hero_1", 2);

        $op = $this->createOp("actionMend");
        $this->assertArrayNotHasKey(self::TUNIC, $op->getArgsInfo());
    }
}
