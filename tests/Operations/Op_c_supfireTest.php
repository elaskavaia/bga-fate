<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;

final class Op_c_supfireTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        // Assign hero 1 (Bjorn) to player
        $this->game->tokens->moveToken("card_hero_1", $this->getPlayersTableau());
        // Place Bjorn on hex_11_8 and move others out of the way
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->tokens->moveToken("hero_2", "hex_1_1");
        $this->game->tokens->moveToken("hero_3", "hex_1_2");
        $this->game->tokens->moveToken("hero_4", "hex_2_1");
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
        $this->op = $this->createOp("c_supfire('rank<=2')");
        $this->assertValidTarget("hex_12_8");
    }

    public function testLevelIOffersRank2Monster(): void {
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8"); // rank 2
        $this->op = $this->createOp("c_supfire('rank<=2')");
        $this->assertValidTarget("hex_12_8");
    }

    public function testLevelIExcludesRank3Monster(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8"); // rank 3
        $this->op = $this->createOp("c_supfire('rank<=2')");
        $this->assertNoValidTargets();
    }

    public function testLevelIIOffersRank3Monster(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8"); // rank 3
        $this->op = $this->createOp("c_supfire");
        $this->assertValidTarget("hex_12_8");
    }

    // -------------------------------------------------------------------------
    // "Cannot choose same monster next turn" — green crystal exclusion
    // -------------------------------------------------------------------------

    public function testExcludesMonsterWithGreenCrystal(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        // Simulate previous suppression: place green crystal on monster
        $this->game->effect_moveCrystals("hero_1", "green", 1, "monster_goblin_1", ["message" => ""]);
        $this->assertNotValidTarget("hex_12_8", "Previously suppressed monster should be excluded");
    }

    public function testAllowsDifferentMonsterWhenOneHasCrystal(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_13_7");
        // Suppress goblin_1 last turn
        $this->game->effect_moveCrystals("hero_1", "green", 1, "monster_goblin_1", ["message" => ""]);
        $this->assertNotValidTarget("hex_12_8", "Goblin should be excluded");
        $this->assertValidTarget("hex_13_7", "Brute should still be available");
    }

    // -------------------------------------------------------------------------
    // resolve — places green crystal, moves existing crystal
    // -------------------------------------------------------------------------

    public function testResolvePlacesGreenCrystal(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->op;
        $this->call_resolve("hex_12_8");

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "monster_goblin_1");
        $this->assertCount(1, $crystals, "Green crystal should be placed on the monster");
    }

    public function testResolveMovesExistingCrystalToNewMonster(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_13_7");
        // Suppress goblin last turn
        $this->game->effect_moveCrystals("hero_1", "green", 1, "monster_goblin_1", ["message" => ""]);

        $op = $this->op;
        $this->call_resolve("hex_13_7");

        $goblinCrystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "monster_goblin_1");
        $this->assertCount(0, $goblinCrystals, "Old crystal should be removed from goblin");

        $bruteCrystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "monster_brute_1");
        $this->assertCount(1, $bruteCrystals, "Crystal should be moved to brute");
    }

    public function testCanSkip(): void {
        $op = $this->op;
        $this->assertTrue($op->canSkip(), "Suppressive Fire should be skippable");
    }

    public function testSkipRemovesCrystalFromMonster(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->effect_moveCrystals("hero_1", "green", 1, "monster_goblin_1", ["message" => ""]);

        $op = $this->op;
        $op->action_skip();

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "monster_goblin_1");
        $this->assertCount(0, $crystals, "Crystal should be removed when skipping");
    }

    // -------------------------------------------------------------------------
    // Integration with Op_monsterMoveAll — crystal prevents movement and stays
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

        // Crystal stays on the monster (removed next turn by c_supfire skip/resolve)
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "monster_goblin_1");
        $this->assertCount(1, $crystals, "Crystal should stay on monster after movement phase");
    }
}
