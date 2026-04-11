<?php

declare(strict_types=1);

use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_rerollMissesTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function getDiceOnBattle(): array {
        return $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
    }

    private function placeDie(string $dieId, int $side): void {
        $this->game->tokens->moveToken($dieId, "display_battle", $side);
    }

    public function testRerollsMissDice(): void {
        // Side 1 = miss, side 2 = miss
        $this->placeDie("die_attack_1", 1);
        $this->placeDie("die_attack_2", 2);
        // Seed rerolls to hit (side 5)
        $this->game->randQueue = [5, 5];

        $op = $this->createOp("rerollMisses");
        $op->resolve();

        $dice = $this->getDiceOnBattle();
        foreach ($dice as $die) {
            $this->assertEquals(5, (int) $die["state"], "Miss dice should be rerolled to seeded value 5");
        }
    }

    public function testRerollsRuneDice(): void {
        // Side 3 = rune (also a miss)
        $this->placeDie("die_attack_1", 3);
        $this->game->randQueue = [6];

        $op = $this->createOp("rerollMisses");
        $op->resolve();

        $dice = $this->getDiceOnBattle();
        $die = reset($dice);
        $this->assertEquals(6, (int) $die["state"], "Rune die should be rerolled");
    }

    public function testDoesNotRerollHits(): void {
        // Side 5 = hit, side 6 = hit, side 4 = hitcov
        $this->placeDie("die_attack_1", 5);
        $this->placeDie("die_attack_2", 6);
        $this->placeDie("die_attack_3", 4);

        $op = $this->createOp("rerollMisses");
        $op->resolve();

        $dice = $this->getDiceOnBattle();
        $this->assertEquals(5, (int) $dice["die_attack_1"]["state"]);
        $this->assertEquals(6, (int) $dice["die_attack_2"]["state"]);
        $this->assertEquals(4, (int) $dice["die_attack_3"]["state"]);
    }

    public function testMixedDiceOnlyRerollsMisses(): void {
        // 2 misses + 1 hit
        $this->placeDie("die_attack_1", 1); // miss
        $this->placeDie("die_attack_2", 5); // hit
        $this->placeDie("die_attack_3", 2); // miss
        $this->game->randQueue = [6, 6];

        $op = $this->createOp("rerollMisses");
        $op->resolve();

        $dice = $this->getDiceOnBattle();
        $this->assertEquals(6, (int) $dice["die_attack_1"]["state"], "Miss should be rerolled");
        $this->assertEquals(5, (int) $dice["die_attack_2"]["state"], "Hit should be unchanged");
        $this->assertEquals(6, (int) $dice["die_attack_3"]["state"], "Miss should be rerolled");
    }

    public function testNoDiceDoesNothing(): void {
        $op = $this->createOp("rerollMisses");
        $op->resolve();
        $dice = $this->getDiceOnBattle();
        $this->assertCount(0, $dice);
    }
}
