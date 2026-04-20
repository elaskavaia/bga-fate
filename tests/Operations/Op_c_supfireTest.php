<?php

declare(strict_types=1);

final class Op_c_supfireTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        // Place Bjorn on hex_11_8 and move others out of the way
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    // -------------------------------------------------------------------------
    // Testing possible moves — Level II (no rank filter)
    // -------------------------------------------------------------------------

    public function testOffersMonsterInRange(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8"); // range 1 from hero
        $this->assertValidTarget("hex_12_8");
    }

    public function testExcludesMonsterOutOfRange(): void {
        // Place monster far away (range > 3 from hex_11_8)
        $this->game->tokens->moveToken("monster_goblin_1", "hex_5_5");
        $this->assertNoValidTargets();
    }

    public function testOffersMultipleMonstersInRange(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8"); // range 1
        $this->game->tokens->moveToken("monster_brute_1", "hex_13_7"); // range 2

        $this->assertValidTarget("hex_12_8");
        $this->assertValidTarget("hex_13_7");
    }

    public function testVoidWhenNoMonstersInRange(): void {
        // No monsters on map
        $this->assertNoValidTargets();
    }

    // -------------------------------------------------------------------------
    // Testing possible moves — Level I (rank<=2 filter)
    // -------------------------------------------------------------------------

    public function testLevelIOffersRank1Monster(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8"); // rank 1
        $this->createOp("c_supfire(inRange3,'rank<=2')");
        $this->assertValidTarget("hex_12_8");
    }

    public function testLevelIOffersRank2Monster(): void {
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8"); // rank 2
        $this->createOp("c_supfire(inRange3,'rank<=2')");
        $this->assertValidTarget("hex_12_8");
    }

    public function testLevelIExcludesRank3Monster(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8"); // rank 3
        $this->createOp("c_supfire(inRange3,'rank<=2')");
        $this->assertNoValidTargets();
    }

    public function testLevelIIOffersRank3Monster(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8"); // rank 3
        $this->createOp("c_supfire(inRange3)");
        $this->assertValidTarget("hex_12_8");
    }

    // -------------------------------------------------------------------------
    // "Cannot choose same monster next turn" — stun marker exclusion
    // -------------------------------------------------------------------------

    private function placeStunMarker(string $monsterId): void {
        $this->game->tokens->createTokenIfNot("stunmarker_c", $monsterId);
        $this->game->tokens->moveToken("stunmarker_c", $monsterId);
    }

    public function testExcludesMonsterWithStunMarker(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        // Simulate previous suppression: place stun marker on monster
        $this->placeStunMarker("monster_goblin_1");
        $this->assertNotValidTarget("hex_12_8", "Previously suppressed monster should be excluded");
    }

    public function testAllowsDifferentMonsterWhenOneHasStunMarker(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_13_7");
        // Suppress goblin_1 last turn
        $this->placeStunMarker("monster_goblin_1");
        $this->assertNotValidTarget("hex_12_8", "Goblin should be excluded");
        $this->assertValidTarget("hex_13_7", "Brute should still be available");
    }

    // -------------------------------------------------------------------------
    // resolve — places stun marker, moves existing marker
    // -------------------------------------------------------------------------

    public function testResolvePlacesStunMarker(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->call_resolve("hex_12_8");

        $markers = $this->game->tokens->getTokensOfTypeInLocation("stunmarker", "monster_goblin_1");
        $this->assertCount(1, $markers, "Stun marker should be placed on the monster");
    }

    public function testResolveMovesExistingMarkerToNewMonster(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_13_7");
        // Suppress goblin last turn
        $this->placeStunMarker("monster_goblin_1");

        $this->call_resolve("hex_13_7");

        $goblinMarkers = $this->game->tokens->getTokensOfTypeInLocation("stunmarker", "monster_goblin_1");
        $this->assertCount(0, $goblinMarkers, "Old marker should be removed from goblin");

        $bruteMarkers = $this->game->tokens->getTokensOfTypeInLocation("stunmarker", "monster_brute_1");
        $this->assertCount(1, $bruteMarkers, "Marker should be moved to brute");
    }

    public function testCanSkip(): void {
        $op = $this->op;
        $this->assertTrue($op->canSkip(), "Suppressive Fire should be skippable");
    }

    public function testSkipRemovesMarkerFromMonster(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->placeStunMarker("monster_goblin_1");

        $op = $this->op;
        $op->action_skip();

        $markers = $this->game->tokens->getTokensOfTypeInLocation("stunmarker", "monster_goblin_1");
        $this->assertCount(0, $markers, "Marker should be removed when skipping");
    }

    // -------------------------------------------------------------------------
    // Integration with Op_monsterMoveAll — marker prevents movement and stays
    // -------------------------------------------------------------------------

    public function testSuppressedMonsterDoesNotMoveDuringMonsterTurn(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        // Suppress the monster
        $this->call_resolve("hex_12_8");

        // Now run monster movement
        $this->createOp("monsterMoveAll");
        $this->op->resolve();

        $loc = $this->game->tokens->getTokenLocation("monster_goblin_1");
        $this->assertEquals("hex_12_8", $loc, "Suppressed monster should not move");

        // Marker stays on the monster (removed next turn by c_supfire skip/resolve)
        $markers = $this->game->tokens->getTokensOfTypeInLocation("stunmarker", "monster_goblin_1");
        $this->assertCount(1, $markers, "Marker should stay on monster after movement phase");
    }
}
