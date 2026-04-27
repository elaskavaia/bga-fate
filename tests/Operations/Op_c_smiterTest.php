<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Op_c_smiter — spend N red crystals stored on the host card, queue NaddDamage.
 *
 * Range is dynamic: min=1, max=#crystals on the card. The host card id is
 * passed via the "card" data field (set by CardGeneric::canBePlayed when
 * promptUseCard fires).
 */
final class Op_c_smiterTest extends AbstractOpTestCase {
    private string $cardId = "card_equip_4_21"; // Smiterbiter

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken($this->cardId, $this->getPlayersTableau());
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function seedStored(int $n): void {
        $this->game->effect_moveCrystals("hero_1", "red", $n, $this->cardId, ["message" => ""]);
    }

    private function getQueuedOpTypes(): array {
        $ops = $this->game->machine->getAllOperations($this->owner);
        return array_map(fn($o) => $o["type"], $ops);
    }

    // -------------------------------------------------------------------------
    // target selection
    // -------------------------------------------------------------------------

    public function testNoCardContextBails(): void {
        $this->createOp("c_smiter");
        $this->assertNoValidTargetsAndError(Material::ERR_NOT_APPLICABLE);
    }

    public function testNoStoredCrystalsBails(): void {
        $this->createOp("c_smiter", ["card" => $this->cardId]);
        $this->assertNoValidTargetsAndError(Material::ERR_NOT_APPLICABLE);
    }

    public function testRangeKeysReflectStoredCount(): void {
        $this->seedStored(2);
        $this->createOp("c_smiter", ["card" => $this->cardId]);
        $targets = $this->op->getArgsTarget();
        $this->assertEquals([1, 2], $targets);
    }

    public function testRangeStartsAtOne(): void {
        // Even with 3 stored, 0 is not offered — min=1.
        $this->seedStored(3);
        $this->createOp("c_smiter", ["card" => $this->cardId]);
        $targets = $this->op->getArgsTarget();
        $this->assertEquals([1, 2, 3], $targets);
    }

    // -------------------------------------------------------------------------
    // resolve — crystal removal + NaddDamage queue
    // -------------------------------------------------------------------------

    public function testResolveSpend1RemovesOneRed(): void {
        $this->seedStored(3);
        $this->createOp("c_smiter", ["card" => $this->cardId]);
        $this->call_resolve("1");
        $this->assertEquals(2, $this->countRedCrystals($this->cardId));
    }

    public function testResolveSpendAllRemovesAllReds(): void {
        $this->seedStored(3);
        $this->createOp("c_smiter", ["card" => $this->cardId]);
        $this->call_resolve("3");
        $this->assertEquals(0, $this->countRedCrystals($this->cardId));
    }

    public function testResolveReturnsCrystalsToSupply(): void {
        $this->seedStored(2);
        $supplyBefore = $this->countRedCrystals("supply_crystal_red");
        $this->createOp("c_smiter", ["card" => $this->cardId]);
        $this->call_resolve("2");
        $supplyAfter = $this->countRedCrystals("supply_crystal_red");
        $this->assertEquals($supplyBefore + 2, $supplyAfter);
    }

    public function testResolveQueuesNaddDamage(): void {
        $this->seedStored(3);
        $this->createOp("c_smiter", ["card" => $this->cardId]);
        $this->call_resolve("2");
        $this->assertContains("2addDamage", $this->getQueuedOpTypes());
    }

    public function testResolveSpend1QueuesSingleAddDamage(): void {
        $this->seedStored(3);
        $this->createOp("c_smiter", ["card" => $this->cardId]);
        $this->call_resolve("1");
        $this->assertContains("1addDamage", $this->getQueuedOpTypes());
    }
}
