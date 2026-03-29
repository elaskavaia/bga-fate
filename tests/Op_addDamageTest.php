<?php

declare(strict_types=1);

use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_addDamageTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function getDiceOnBattle(): array {
        return $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
    }

    public function testAdds1DiceToBattle(): void {
        $op = $this->game->machine->instanciateOperation("1addDamage", PCOLOR);
        $op->resolve();
        $dice = $this->getDiceOnBattle();
        $this->assertCount(1, $dice);
    }

    public function testAdds3DiceToBattle(): void {
        $op = $this->game->machine->instanciateOperation("3addDamage", PCOLOR);
        $op->resolve();
        $dice = $this->getDiceOnBattle();
        $this->assertCount(3, $dice);
    }

    public function testDiceHaveHitState(): void {
        $op = $this->game->machine->instanciateOperation("2addDamage", PCOLOR);
        $op->resolve();
        $dice = $this->getDiceOnBattle();
        foreach ($dice as $die) {
            $this->assertEquals(6, (int) $die["state"], "Die should have state 6 (hit/damage)");
        }
    }

    public function testStacksWithExistingDice(): void {
        // Place 2 dice already on battle
        $this->game->tokens->moveToken("die_attack_1", "display_battle", 5);
        $this->game->tokens->moveToken("die_attack_2", "display_battle", 5);

        $op = $this->game->machine->instanciateOperation("2addDamage", PCOLOR);
        $op->resolve();
        $dice = $this->getDiceOnBattle();
        $this->assertCount(4, $dice);
    }

    public function testUsesCustomAttacker(): void {
        $op = $this->game->machine->instanciateOperation("1addDamage", PCOLOR, ["attacker" => "monster_goblin_1"]);
        $op->resolve();
        // Just verify it doesn't crash and dice are placed
        $dice = $this->getDiceOnBattle();
        $this->assertCount(1, $dice);
    }
}
