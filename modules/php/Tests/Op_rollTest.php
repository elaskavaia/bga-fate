<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\Operations\Op_roll;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Tests\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_rollTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        // Assign hero 1 (Bjorn) to PCOLOR
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_ability_1_3", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_equip_1_15", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function createOp(string $expr = "roll"): Op_roll {
        /** @var Op_roll */
        $op = $this->game->machine->instanciateOperation($expr, PCOLOR);
        return $op;
    }

    private function getDamage(string $monsterId): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $monsterId));
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves
    // -------------------------------------------------------------------------

    public function testNoMonstersAdjacentReturnsEmpty(): void {
        $op = $this->createOp();
        $moves = $op->getArgs()["info"];
        $this->assertEmpty($moves);
    }

    public function testAdjacentMonsterIsTarget(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->createOp();
        $moves = $op->getArgs()["info"];
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    public function testNonAdjacentMonsterNotTarget(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $op = $this->createOp("roll(adj)");
        $moves = $op->getArgs()["info"];
        $this->assertEmpty($moves);
    }

    public function testMultipleAdjacentMonsters(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_11_7");
        $op = $this->createOp();
        $moves = $op->getArgs()["info"];
        $this->assertCount(2, $moves);
    }

    public function testInRangeUsesHeroAttackRange(): void {
        // Bjorn has First Bow (attack_range=2), hex_13_7 is 2 hexes away
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        /** @var Op_roll */
        $op = $this->game->machine->instanciateOperation("roll(inRange)", PCOLOR);
        $moves = $op->getArgs()["info"];
        $this->assertArrayHasKey("hex_13_7", $moves);
    }

    public function testInRange3ReachesDistance3(): void {
        // hex_14_6 is 3 hexes from hex_11_8
        $this->game->tokens->moveToken("monster_goblin_1", "hex_14_6");
        /** @var Op_roll */
        $op = $this->game->machine->instanciateOperation("roll(inRange3)", PCOLOR);
        $moves = $op->getArgs()["info"];
        $this->assertArrayHasKey("hex_14_6", $moves);
    }

    public function testAdjDoesNotReachDistance2(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        /** @var Op_roll */
        $op = $this->game->machine->instanciateOperation("roll(adj)", PCOLOR);
        $moves = $op->getArgs()["info"];
        $this->assertEmpty($moves);
    }

    public function testAdjacentHeroNotTarget(): void {
        $this->game->tokens->moveToken("hero_2", "hex_12_8");
        $op = $this->createOp();
        $moves = $op->getArgs()["info"];
        $this->assertArrayNotHasKey("hex_12_8", $moves);
    }

    // -------------------------------------------------------------------------
    // resolve — rolls dice and queues resolveHits
    // -------------------------------------------------------------------------

    public function testResolveRollsDice(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $op = $this->createOp("3roll");
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        // 3 dice should be on display_battle
        $diceOnDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        $this->assertCount(3, $diceOnDisplay);
    }

    public function testResolveQueuesResolveHits(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $op = $this->createOp("3roll");
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        // resolveHits should be queued in the machine
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("resolveHits", $opTypes);
    }

    public function testResolveDoesNotApplyDamageDirectly(): void {
        // Roll only puts dice on display — damage comes from resolveHits → dealDamage
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $op = $this->createOp("3roll");
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        // No red crystals on the monster yet (resolveHits hasn't run)
        $this->assertEquals(0, $this->getDamage("monster_troll_1"));
    }

    public function testCountDeterminesDiceRolled(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $op = $this->createOp("5roll");
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $diceOnDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        $this->assertCount(5, $diceOnDisplay);
    }
}
