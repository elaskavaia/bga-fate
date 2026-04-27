<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;

/**
 * Tests for Op_encounter — picking up bonus crystals on Troll Caves / Nailfare / Wyrm Lair.
 */
final class Op_encounterTest extends AbstractOpTestCase {
    function getOperationType(): string {
        return "encounter";
    }

    private function getQueuedOp(): ?array {
        $ops = $this->game->machine->getTopOperations(PCOLOR);
        return $ops ? reset($ops) : null;
    }

    private function makeOpForHex(string $hex): void {
        $this->op = $this->game->machine->instantiateOperation("encounter", $this->owner, ["hex" => $hex]);
    }

    private function placeCrystals(string $color, int $count, string $hex): void {
        $this->game->tokens->pickTokensForLocation($count, "supply_crystal_$color", $hex);
    }

    // --- getPossibleMoves ---

    public function testNoCrystalsOnHexAutoSkips(): void {
        $this->makeOpForHex("hex_11_8");
        $this->assertNoValidTargetsAndError(Material::ERR_NOT_APPLICABLE);
    }

    public function testYellowOffersOneToN(): void {
        $this->placeCrystals("yellow", 3, "hex_11_8");
        $this->makeOpForHex("hex_11_8");
        $this->assertValidTarget(1);
        $this->assertValidTarget(2);
        $this->assertValidTarget(3);
        $this->assertNotValidTarget(0);
        $this->assertNotValidTarget(4);
    }

    public function testCanSkip(): void {
        $this->placeCrystals("red", 3, "hex_13_8");
        $this->makeOpForHex("hex_13_8");
        $this->assertTrue($this->op->canSkip());
    }

    // --- resolve: yellow ---

    public function testYellowPickAllQueuesGainXp(): void {
        $this->placeCrystals("yellow", 3, "hex_11_8");
        $this->makeOpForHex("hex_11_8");
        $this->call_resolve("3");

        $this->assertCount(0, $this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "hex_11_8"));
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("3gainXp", $queued["type"]);
    }

    public function testYellowPickPartialLeavesRemainder(): void {
        $this->placeCrystals("yellow", 3, "hex_11_8");
        $this->makeOpForHex("hex_11_8");
        $this->call_resolve("2");

        $this->assertCount(1, $this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "hex_11_8"));
        $queued = $this->getQueuedOp();
        $this->assertEquals("2gainXp", $queued["type"]);
    }

    // --- resolve: green ---

    public function testGreenPickQueuesGainMana(): void {
        $this->placeCrystals("green", 3, "hex_12_8");
        $this->makeOpForHex("hex_12_8");
        $this->call_resolve("2");

        $this->assertCount(1, $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "hex_12_8"));
        $queued = $this->getQueuedOp();
        $this->assertEquals("2gainMana", $queued["type"]);
    }

    // --- resolve: red ---

    public function testRedPickQueuesRemoveDamage(): void {
        $this->placeCrystals("red", 3, "hex_13_8");
        $this->makeOpForHex("hex_13_8");
        $this->call_resolve("1");

        $this->assertCount(2, $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hex_13_8"));
        $queued = $this->getQueuedOp();
        $this->assertEquals("1removeDamage", $queued["type"]);
    }
}
