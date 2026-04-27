<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_actionMend;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_actionMendTest extends AbstractOpTestCase {
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
}
