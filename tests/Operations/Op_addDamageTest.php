<?php

declare(strict_types=1);

use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_addDamageTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function getDiceOnBattle(): array {
        return $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
    }

    public function testAdds1DiceToBattle(): void {
        $op = $this->createOp("1addDamage");
        $op->resolve();
        $dice = $this->getDiceOnBattle();
        $this->assertCount(1, $dice);
    }

    public function testAdds3DiceToBattle(): void {
        $op = $this->createOp("3addDamage");
        $op->resolve();
        $dice = $this->getDiceOnBattle();
        $this->assertCount(3, $dice);
    }

    public function testDiceHaveHitState(): void {
        $op = $this->createOp("2addDamage");
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

        $op = $this->createOp("2addDamage");
        $op->resolve();
        $dice = $this->getDiceOnBattle();
        $this->assertCount(4, $dice);
    }

    public function testUsesCustomAttacker(): void {
        $op = $this->createOp("1addDamage", ["attacker" => "monster_goblin_1"]);
        $op->resolve();
        // Just verify it doesn't crash and dice are placed
        $dice = $this->getDiceOnBattle();
        $this->assertCount(1, $dice);
    }

    // --- Param: no param (unconditional) ---

    public function testNoParamAlwaysValid(): void {
        $op = $this->createOp("2addDamage");
        $this->assertEquals(0, $op->getErrorCode());
    }

    // --- Param: numeric minimum distance ---

    public function testMinDistRejectsCloseTarget(): void {
        // Hero at hex_11_8, marker_attack at adjacent hex (distance 1)
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        $this->createOp("2addDamage(2)");
        $this->assertNoValidTargets("Should reject target at distance 1 when min is 2");
    }

    public function testMinDistAcceptsDistantTarget(): void {
        // Hero at hex_11_8, marker_attack 2 hexes away
        $this->game->tokens->moveToken("marker_attack", "hex_9_8");
        $op = $this->createOp("2addDamage(2)");
        $this->assertEquals(0, $op->getErrorCode(), "Should accept target at distance 2 when min is 2");
    }

    public function testMinDistRejectsNoMarker(): void {
        // marker_attack in limbo (no active attack)
        $this->createOp("2addDamage(2)");
        $this->assertNoValidTargets("Should reject when no attack marker");
    }

    // --- Param: "dist" (damage = distance) ---

    public function testDistParamValid(): void {
        $this->game->tokens->moveToken("marker_attack", "hex_9_8");
        $op = $this->createOp("addDamage(dist)");
        $this->assertEquals(0, $op->getErrorCode(), "dist param should be valid when marker present");
    }

    public function testDistParamRejectsNoMarker(): void {
        $this->createOp("addDamage(dist)");
        $this->assertNoValidTargets("dist param should reject when no attack marker");
    }

    public function testDistParamAddsDiceEqualToDistance(): void {
        // Hero at hex_11_8, marker 3 hexes away
        $this->game->tokens->moveToken("marker_attack", "hex_8_8");
        $op = $this->createOp("addDamage(dist)");
        $op->resolve();
        $dice = $this->getDiceOnBattle();
        $this->assertCount(3, $dice, "Should add 3 dice for distance 3");
    }

    // --- Param: filter expression (param 1) ---

    public function testFilterMatchesFaction(): void {
        // Trollbane: add 1 damage when attacking trollkin
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        $op = $this->createOp("1addDamage(true,trollkin)");
        $this->assertEquals(0, $op->getErrorCode(), "Filter should pass for trollkin target");
    }

    public function testFilterRejectsWrongFaction(): void {
        // Target is firehorde, filter requires trollkin
        $this->game->tokens->moveToken("monster_sprite_1", "hex_12_8");
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        $this->createOp("1addDamage(true,trollkin)");
        $this->assertNoValidTargets("Filter should reject non-trollkin target");
    }

    public function testFilterRejectsNoAttackMarker(): void {
        // marker_attack in limbo, filter requires trollkin
        $this->createOp("1addDamage(true,trollkin)");
        $this->assertNoValidTargets("Filter should reject when no attack marker");
    }

    public function testFilterRejectsNoMonsterOnHex(): void {
        // Marker on empty hex, filter set
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        $this->createOp("1addDamage(true,trollkin)");
        $this->assertNoValidTargets("Filter should reject when no character on marked hex");
    }

    public function testFilterRankExpression(): void {
        // monster_goblin is rank 1, filter requires rank<=2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        $op = $this->createOp("1addDamage(true,'rank<=2')");
        $this->assertEquals(0, $op->getErrorCode(), "rank<=2 should pass for rank 1 goblin");
    }

    public function testFilterRankExpressionFails(): void {
        // monster_troll is rank 3, filter requires rank<=2
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        $this->createOp("1addDamage(true,'rank<=2')");
        $this->assertNoValidTargets("rank<=2 should reject rank 3 troll");
    }

    public function testFilterCombinedWithMinRange(): void {
        // Both range and filter: monster at distance 2, trollkin
        $this->game->tokens->moveToken("monster_goblin_1", "hex_9_8");
        $this->game->tokens->moveToken("marker_attack", "hex_9_8");
        $op = $this->createOp("1addDamage(2,trollkin)");
        $this->assertEquals(0, $op->getErrorCode(), "Range and filter should both pass");
    }

    public function testFilterResolveAddsDice(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        $op = $this->createOp("1addDamage(true,trollkin)");
        $op->resolve();
        $this->assertCount(1, $this->getDiceOnBattle(), "Should add 1 die when filter passes");
    }
}
