<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Operations\Op_c_supfire;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_c_supfireTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        $this->game->setPlayersNumber(1);
        // Assign hero 1 (Bjorn) to player
        $this->game->tokens->moveToken("card_hero_1", "tableau_" . PCOLOR);
        // Place Bjorn on hex_11_8 and move others out of the way
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->tokens->moveToken("hero_2", "hex_1_1");
        $this->game->tokens->moveToken("hero_3", "hex_1_2");
        $this->game->tokens->moveToken("hero_4", "hex_2_1");
    }

    private function createOp(string $type = "c_supfire", ?string $cardId = null): Op_c_supfire {
        $data = [];
        if ($cardId !== null) {
            $data["card"] = $cardId;
        }
        /** @var Op_c_supfire */
        $op = $this->game->machine->instanciateOperation($type, PCOLOR, $data);
        return $op;
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves — Level II (no rank filter)
    // -------------------------------------------------------------------------

    public function testOffersMonsterInRange(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8"); // range 1 from hero
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    public function testExcludesMonsterOutOfRange(): void {
        // Place monster far away (range > 3 from hex_11_8)
        $this->game->tokens->moveToken("monster_goblin_1", "hex_5_5");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    public function testOffersMultipleMonstersInRange(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8"); // range 1
        $this->game->tokens->moveToken("monster_brute_1", "hex_13_7"); // range 2
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
        $this->assertArrayHasKey("hex_13_7", $moves);
    }

    public function testVoidWhenNoMonstersInRange(): void {
        // No monsters on map
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves — Level I (rank<=2 filter)
    // -------------------------------------------------------------------------

    public function testLevelIOffersRank1Monster(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8"); // rank 1
        $op = $this->createOp("c_supfire('rank<=2')");
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    public function testLevelIOffersRank2Monster(): void {
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8"); // rank 2
        $op = $this->createOp("c_supfire('rank<=2')");
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    public function testLevelIExcludesRank3Monster(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8"); // rank 3
        $op = $this->createOp("c_supfire('rank<=2')");
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    public function testLevelIIOffersRank3Monster(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8"); // rank 3
        $op = $this->createOp("c_supfire");
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    // -------------------------------------------------------------------------
    // "Cannot choose same monster next turn" — green crystal exclusion
    // -------------------------------------------------------------------------

    public function testExcludesMonsterWithGreenCrystal(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        // Simulate previous suppression: place green crystal on monster
        $this->game->effect_moveCrystals("hero_1", "green", 1, "monster_goblin_1", ["message" => ""]);

        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayNotHasKey("hex_12_8", $moves, "Previously suppressed monster should be excluded");
    }

    public function testAllowsDifferentMonsterWhenOneHasCrystal(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_13_7");
        // Suppress goblin_1 last turn
        $this->game->effect_moveCrystals("hero_1", "green", 1, "monster_goblin_1", ["message" => ""]);

        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayNotHasKey("hex_12_8", $moves, "Goblin should be excluded");
        $this->assertArrayHasKey("hex_13_7", $moves, "Brute should still be available");
    }

    // -------------------------------------------------------------------------
    // resolve — places green crystal, moves existing crystal
    // -------------------------------------------------------------------------

    public function testResolvePlacesGreenCrystal(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "monster_goblin_1");
        $this->assertCount(1, $crystals, "Green crystal should be placed on the monster");
    }

    public function testResolveMovesExistingCrystalToNewMonster(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_13_7");
        // Suppress goblin last turn
        $this->game->effect_moveCrystals("hero_1", "green", 1, "monster_goblin_1", ["message" => ""]);

        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_13_7"]);

        $goblinCrystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "monster_goblin_1");
        $this->assertCount(0, $goblinCrystals, "Old crystal should be removed from goblin");

        $bruteCrystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "monster_brute_1");
        $this->assertCount(1, $bruteCrystals, "Crystal should be moved to brute");
    }

    public function testCanSkip(): void {
        $op = $this->createOp();
        $this->assertTrue($op->canSkip(), "Suppressive Fire should be skippable");
    }

    public function testSkipRemovesCrystalFromMonster(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->effect_moveCrystals("hero_1", "green", 1, "monster_goblin_1", ["message" => ""]);

        $op = $this->createOp();
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
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);

        // Now run monster movement
        $moveOp = $this->game->machine->instanciateOperation("monsterMoveAll", ACOLOR);
        $moveOp->resolve();

        $loc = $this->game->tokens->getTokenLocation("monster_goblin_1");
        $this->assertEquals("hex_12_8", $loc, "Suppressed monster should not move");

        // Crystal stays on the monster (removed next turn by c_supfire skip/resolve)
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "monster_goblin_1");
        $this->assertCount(1, $crystals, "Crystal should stay on monster after movement phase");
    }
}
