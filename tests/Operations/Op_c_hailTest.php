<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;

/**
 * Hail of Arrows I (card_ability_2_3): 3[MANA]: Deal 1 damage to up to 3 different
 * monsters within attack range. Fixed 3-mana cost regardless of how many targets
 * the player actually picks.
 */
final class Op_c_hailTest extends AbstractOpTestCase {
    private string $cardId = "card_ability_2_3";
    private string $hero = "hero_2";

    protected function setUp(): void {
        $this->init(2);
        // Reposition Alva to hex_11_8 so the range-2 bow can reach the test hexes around her.
        $this->game->tokens->moveToken($this->hero, "hex_11_8");
        $this->game->effect_moveCrystals($this->hero, "green", 2, $this->cardId);
    }

    private function getMana(): int {
        return $this->countGreenCrystals($this->cardId);
    }

    // --- Target selection ---

    public function testListsMonsterHexesInRange(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_goblin_2", "hex_10_8");
        $this->game->tokens->moveToken("monster_goblin_3", "hex_11_9");

        $this->createOp(null, ["card" => $this->cardId]);
        $targets = $this->op->getArgsTarget();
        $this->assertContains("hex_12_8", $targets);
        $this->assertContains("hex_10_8", $targets);
        $this->assertContains("hex_11_9", $targets);
    }

    public function testNoValidTargetsWhenNoMonstersInRange(): void {
        $this->createOp(null, ["card" => $this->cardId]);
        $this->assertNoValidTargets();
    }

    public function testNoValidTargetsWhenInsufficientMana(): void {
        $this->game->effect_moveCrystals($this->hero, "green", -1, $this->cardId);
        $this->assertEquals(2, $this->getMana());
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        $this->createOp(null, ["card" => $this->cardId]);
        $this->assertNoValidTargetsAndError(Material::ERR_COST);
    }

    public function testVoidWhenOnlyOneMana(): void {
        $this->game->effect_moveCrystals($this->hero, "green", -2, $this->cardId);
        $this->assertEquals(1, $this->getMana());
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        $op = $this->createOp(null, ["card" => $this->cardId]);
        $this->assertTrue($op->isVoid(), "Hail I needs 3 mana — with only 1 on the card the op is void");
    }

    public function testVoidWhenHeroInGrimheim(): void {
        // Heroes in Grimheim can't attack — even with monsters in range, op should be void.
        $this->game->tokens->moveToken($this->hero, "hex_9_9");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_11_8");

        $op = $this->createOp(null, ["card" => $this->cardId]);
        $this->assertTrue($op->isVoid(), "Hail I from inside Grimheim is void");
    }

    public function testTargetsAreSingleSelectPerHex(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        $this->createOp(null, ["card" => $this->cardId]);
        $info = $this->op->getArgsInfo();
        $this->assertValidTarget("hex_12_8");
    }

    // --- Resolve drains spendMana + dealDamage sub-ops ---

    public function testResolveSpends3ManaRegardlessOfTargetsPicked(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->assertEquals(3, $this->getMana());

        $this->createOp(null, ["card" => $this->cardId]);
        $this->call_resolve(["hex_12_8"]);
        $this->game->machine->dispatchAll();

        $this->assertEquals(0, $this->getMana(), "Hail I spends 3 mana even when picking 1 target");
    }

    public function testResolveDealsOneDamagePerTarget(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_goblin_2", "hex_10_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_11_9"); // brute has health 3 so it survives 1 damage

        $this->createOp(null, ["card" => $this->cardId]);
        $this->call_resolve(["hex_12_8", "hex_10_8", "hex_11_9"]);
        $this->game->machine->dispatchAll();

        // Goblin health=2, 1 damage doesn't kill — red crystal on token.
        $this->assertEquals(1, $this->countRedCrystals("monster_goblin_1"));
        $this->assertEquals(1, $this->countRedCrystals("monster_goblin_2"));
        $this->assertEquals(1, $this->countRedCrystals("monster_brute_1"));
    }

    public function testResolveRejectsSelectingMoreThanMax(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_goblin_2", "hex_10_8");
        $this->game->tokens->moveToken("monster_goblin_3", "hex_11_9");
        $this->game->tokens->moveToken("monster_goblin_4", "hex_10_9");

        $this->createOp(null, ["card" => $this->cardId]);
        $this->expectException(Exception::class);
        $this->call_resolve(["hex_12_8", "hex_10_8", "hex_11_9", "hex_10_9"]);
    }

    public function testMultiSelectArgType(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        $this->createOp(null, ["card" => $this->cardId]);
        $this->assertEquals("token_array", $this->op->getArgType());
    }
}
