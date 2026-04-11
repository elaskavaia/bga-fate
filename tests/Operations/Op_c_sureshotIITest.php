<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;

final class Op_c_sureshotIITest extends AbstractOpTestCase {
    private string $cardId = "card_ability_1_4";

    protected function setUp(): void {
        parent::setUp();
        $this->game->clearMachine();
        $this->game->tokens->moveToken($this->cardId, "tableau_" . $this->owner); // Sure Shot II
        $this->game->tokens->moveToken("card_equip_1_15", "tableau_" . $this->owner); // First Bow (range=2)
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // Add 4 mana to Sure Shot II
        $this->game->effect_moveCrystals("hero_1", "green", 4, $this->cardId);
    }

    private function getMana(): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", $this->cardId));
    }

    // --- Step 1: Monster selection ---

    public function testStep1ReturnsMonsterHexesInRange(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8"); // adjacent
        $this->game->hexMap->invalidateOccupancy();
        $this->op = $this->createOp(null, ["card" => $this->cardId]);
        $targets = $this->op->getArgsTarget();
        $this->assertContains("hex_12_8", $targets);
    }

    public function testStep1ErrorWhenNoMonstersInRange(): void {
        $this->op = $this->createOp(null, ["card" => $this->cardId]);
        $targets = $this->op->getArgsTarget();
        $this->assertEmpty($targets);
    }

    public function testStep1ErrorWhenInsufficientMana(): void {
        $this->game->effect_moveCrystals("hero_1", "green", -3, $this->cardId);
        $this->assertEquals(1, $this->getMana());
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();
        $this->op = $this->createOp(null, ["card" => $this->cardId]);
        $targets = $this->op->getArgsTarget();
        $this->assertEmpty($targets);
    }

    public function testStep1ResolveQueuesStep2(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();
        $this->op = $this->createOp(null, ["card" => $this->cardId]);
        $this->call_resolve("hex_12_8");
        $pending = $this->game->machine->getTopOperations($this->owner);
        $this->assertNotEmpty($pending);
        $this->assertEquals("c_sureshotII", $pending[0]["type"]);
    }

    // --- Step 2: Mana amount selection ---

    public function testStep2OffersChoices2To4(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8"); // troll health=7
        $this->game->hexMap->invalidateOccupancy();
        $this->op = $this->createOp(null, ["card" => $this->cardId, "target" => "hex_12_8"]);
        $targets = $this->op->getArgsTarget();
        $this->assertContains("choice_2", $targets);
        $this->assertContains("choice_3", $targets);
        $this->assertContains("choice_4", $targets);
        $this->assertNotContains("choice_5", $targets);
        $this->assertNotContains("choice_1", $targets);
    }

    public function testStep2CapsAtManaOnCard(): void {
        $this->game->effect_moveCrystals("hero_1", "green", -1, $this->cardId);
        $this->assertEquals(3, $this->getMana());
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8"); // troll health=7
        $this->game->hexMap->invalidateOccupancy();
        $this->op = $this->createOp(null, ["card" => $this->cardId, "target" => "hex_12_8"]);
        $targets = $this->op->getArgsTarget();
        $this->assertContains("choice_2", $targets);
        $this->assertContains("choice_3", $targets);
        $this->assertNotContains("choice_4", $targets);
    }

    public function testStep2CapsAtMonsterRemainingHealth(): void {
        // Goblin health=2, no pre-damage → max=2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();
        $this->op = $this->createOp(null, ["card" => $this->cardId, "target" => "hex_12_8"]);
        $targets = $this->op->getArgsTarget();
        $this->assertContains("choice_2", $targets);
        $this->assertNotContains("choice_3", $targets);
    }

    public function testStep2CapsAtDamagedMonsterHealth(): void {
        // Brute health=3, pre-damage 1 → remaining=2 → max=2
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();
        $this->game->effect_moveCrystals("hero_1", "red", 1, "monster_brute_1");
        $this->op = $this->createOp(null, ["card" => $this->cardId, "target" => "hex_12_8"]);
        $targets = $this->op->getArgsTarget();
        $this->assertContains("choice_2", $targets);
        $this->assertNotContains("choice_3", $targets);
    }

    public function testStep2ResolveQueuesSpendManaAndDealDamage(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();
        $this->op = $this->createOp(null, ["card" => $this->cardId, "target" => "hex_12_8"]);
        $this->call_resolve("choice_3");
        $pending = $this->game->machine->getTopOperations($this->owner);
        $this->assertNotEmpty($pending);
    }

    public function testStep2ExtraArgsContainsRemainingHealth(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8"); // troll health=7
        $this->game->hexMap->invalidateOccupancy();
        $this->op = $this->createOp(null, ["card" => $this->cardId, "target" => "hex_12_8"]);
        $extra = $this->op->getExtraArgs();
        $this->assertArrayHasKey("remaining_health", $extra);
        $this->assertEquals(7, $extra["remaining_health"]);
    }
}
