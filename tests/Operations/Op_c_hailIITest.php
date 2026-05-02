<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;

/**
 * Hail of Arrows II (card_ability_2_4): 1-4[MANA]: Deal 1 damage to that many
 * different monsters within attack range. Mana cost scales with number of targets.
 */
final class Op_c_hailIITest extends AbstractOpTestCase {
    private string $cardId = "card_ability_2_4";

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken($this->cardId, $this->getPlayersTableau());
        $this->game->tokens->moveToken("card_equip_1_15", $this->getPlayersTableau());
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->effect_moveCrystals("hero_1", "green", 4, $this->cardId);
    }

    private function getMana(): int {
        return $this->countGreenCrystals($this->cardId);
    }

    // --- Target selection ---

    public function testNoValidTargetsWhenNoMana(): void {
        $this->game->effect_moveCrystals("hero_1", "green", -4, $this->cardId);
        $this->assertEquals(0, $this->getMana());
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        $this->createOp(null, ["card" => $this->cardId]);
        $this->assertNoValidTargetsAndError(Material::ERR_COST);
    }

    public function testValidWithJust1Mana(): void {
        $this->game->effect_moveCrystals("hero_1", "green", -3, $this->cardId);
        $this->assertEquals(1, $this->getMana());
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        $this->createOp(null, ["card" => $this->cardId]);
        $this->assertValidTarget("hex_12_8");
    }

    public function testMaxCountIs4(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        $this->createOp(null, ["card" => $this->cardId]);
        $this->assertEquals(4, $this->op->getCount());
    }

    // --- Resolve: cost scales with selected count ---

    public function testResolveSpends1ManaFor1Target(): void {
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8"); // health 3, survives 1 damage

        $this->createOp(null, ["card" => $this->cardId]);
        $this->call_resolve(["hex_12_8"]);
        $this->game->machine->dispatchAll();

        $this->assertEquals(3, $this->getMana(), "Picking 1 target spends 1 mana");
        $this->assertEquals(1, $this->countRedCrystals("monster_brute_1"));
    }

    public function testResolveSpends3ManaFor3Targets(): void {
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_2", "hex_10_8");
        $this->game->tokens->moveToken("monster_brute_3", "hex_11_9");

        $this->createOp(null, ["card" => $this->cardId]);
        $this->call_resolve(["hex_12_8", "hex_10_8", "hex_11_9"]);
        $this->game->machine->dispatchAll();

        $this->assertEquals(1, $this->getMana(), "Picking 3 targets spends 3 mana");
        $this->assertEquals(1, $this->countRedCrystals("monster_brute_1"));
        $this->assertEquals(1, $this->countRedCrystals("monster_brute_2"));
        $this->assertEquals(1, $this->countRedCrystals("monster_brute_3"));
    }

    public function testResolveSpendsAll4WhenPicking4(): void {
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_2", "hex_10_8");
        $this->game->tokens->moveToken("monster_brute_3", "hex_11_9");
        $this->game->tokens->moveToken("monster_brute_4", "hex_10_9");

        $this->createOp(null, ["card" => $this->cardId]);
        $this->call_resolve(["hex_12_8", "hex_10_8", "hex_11_9", "hex_10_9"]);
        $this->game->machine->dispatchAll();

        $this->assertEquals(0, $this->getMana());
    }
}
