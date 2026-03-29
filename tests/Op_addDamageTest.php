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

    // --- Param: no param (unconditional) ---

    public function testNoParamAlwaysValid(): void {
        $op = $this->game->machine->instanciateOperation("2addDamage", PCOLOR);
        $this->assertEquals(0, $op->getErrorCode());
    }

    // --- Param: numeric minimum distance ---

    public function testMinDistRejectsCloseTarget(): void {
        // Hero at hex_11_8, marker_attack at adjacent hex (distance 1)
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        $op = $this->game->machine->instanciateOperation("2addDamage(2)", PCOLOR);
        $this->assertNotEquals(0, $op->getErrorCode(), "Should reject target at distance 1 when min is 2");
    }

    public function testMinDistAcceptsDistantTarget(): void {
        // Hero at hex_11_8, marker_attack 2 hexes away
        $this->game->tokens->moveToken("marker_attack", "hex_9_8");
        $op = $this->game->machine->instanciateOperation("2addDamage(2)", PCOLOR);
        $this->assertEquals(0, $op->getErrorCode(), "Should accept target at distance 2 when min is 2");
    }

    public function testMinDistRejectsNoMarker(): void {
        // marker_attack in limbo (no active attack)
        $op = $this->game->machine->instanciateOperation("2addDamage(2)", PCOLOR);
        $this->assertNotEquals(0, $op->getErrorCode(), "Should reject when no attack marker");
    }

    // --- Param: "dist" (damage = distance) ---

    public function testDistParamValid(): void {
        $this->game->tokens->moveToken("marker_attack", "hex_9_8");
        $op = $this->game->machine->instanciateOperation("addDamage(dist)", PCOLOR);
        $this->assertEquals(0, $op->getErrorCode(), "dist param should be valid when marker present");
    }

    public function testDistParamRejectsNoMarker(): void {
        $op = $this->game->machine->instanciateOperation("addDamage(dist)", PCOLOR);
        $this->assertNotEquals(0, $op->getErrorCode(), "dist param should reject when no attack marker");
    }

    public function testDistParamAddsDiceEqualToDistance(): void {
        // Hero at hex_11_8, marker 3 hexes away
        $this->game->tokens->moveToken("marker_attack", "hex_8_8");
        $op = $this->game->machine->instanciateOperation("addDamage(dist)", PCOLOR);
        $op->resolve();
        $dice = $this->getDiceOnBattle();
        $this->assertCount(3, $dice, "Should add 3 dice for distance 3");
    }
}
