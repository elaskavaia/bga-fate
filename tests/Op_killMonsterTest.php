<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_killMonster;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_killMonsterTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        // Assign hero 1 (Bjorn) to PCOLOR: attack_range=2 (First Bow)
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_ability_1_3", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_equip_1_15", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function createOp(string $expr = "killMonster"): Op_killMonster {
        /** @var Op_killMonster */
        $op = $this->game->machine->instanciateOperation($expr, PCOLOR);
        return $op;
    }

    private function getDamage(string $monsterId): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $monsterId));
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves — same as dealDamage, range + filter
    // -------------------------------------------------------------------------

    public function testAdjacentMonsterIsTarget(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    public function testNoMonstersReturnsEmpty(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    public function testCanSkip(): void {
        $op = $this->createOp();
        $this->assertTrue($op->canSkip());
    }

    public function testInRangeFilter(): void {
        // Bjorn has attack_range=2; hex_13_7 is 2 hexes from hex_11_8
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        /** @var Op_killMonster */
        $op = $this->game->machine->instanciateOperation("killMonster(inRange,'rank<=2')", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_13_7", $moves);
    }

    public function testRankFilterExcludesHighRank(): void {
        // Troll is rank 3 — should be excluded by rank<=2
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        /** @var Op_killMonster */
        $op = $this->game->machine->instanciateOperation("killMonster(adj,'rank<=2')", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    public function testRankFilterIncludesLowRank(): void {
        // Goblin is rank 1 — should be included by rank<=2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        /** @var Op_killMonster */
        $op = $this->game->machine->instanciateOperation("killMonster(adj,'rank<=2')", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    // -------------------------------------------------------------------------
    // healthRem filter (Short Temper)
    // -------------------------------------------------------------------------

    public function testHealthRemFilterFullHealthExcluded(): void {
        // Brute health=3, no damage → healthRem=3, filter healthRem<=2 excludes it
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        /** @var Op_killMonster */
        $op = $this->game->machine->instanciateOperation("killMonster(adj,'healthRem<=2')", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    public function testHealthRemFilterDamagedIncluded(): void {
        // Brute health=3, 1 damage → healthRem=2, filter healthRem<=2 includes it
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $this->game->tokens->moveToken("crystal_red_1", "monster_brute_1");
        /** @var Op_killMonster */
        $op = $this->game->machine->instanciateOperation("killMonster(adj,'healthRem<=2')", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    public function testHealthRemGoblinFullHealthIncluded(): void {
        // Goblin health=2 → healthRem=2, filter healthRem<=2 includes it
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        /** @var Op_killMonster */
        $op = $this->game->machine->instanciateOperation("killMonster(adj,'healthRem<=2')", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    // -------------------------------------------------------------------------
    // resolve — kills monster, awards XP
    // -------------------------------------------------------------------------

    public function testKillsGoblin(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));
        $this->assertEquals(0, $this->getDamage("monster_goblin_1"));
    }

    public function testKillsBrute(): void {
        // Brute health=3 — killMonster should deal 3 damage, killing it
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_brute_1"));
    }

    public function testKillGrantsXp(): void {
        // Goblin xp=1
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $xpBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $xpAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $this->assertEquals($xpBefore + 1, $xpAfter);
    }

    public function testKillBruteGrantsMoreXp(): void {
        // Brute xp=2
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $xpBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $xpAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $this->assertEquals($xpBefore + 2, $xpAfter);
    }

    public function testKillAlreadyDamagedMonster(): void {
        // Brute health=3, already has 1 damage — kill should still work
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $this->game->tokens->moveToken("crystal_red_1", "monster_brute_1");
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_brute_1"));
        $this->assertEquals(0, $this->getDamage("monster_brute_1"));
    }

    public function testNoDiceRolled(): void {
        // killMonster is direct kill — no dice
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $diceOnDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        $this->assertCount(0, $diceOnDisplay);
    }
}
