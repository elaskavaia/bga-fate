<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\Operations\Op_dealDamage;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Tests\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_dealDamageTest extends TestCase {
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

    private function createOp(string $expr = "dealDamage"): Op_dealDamage {
        /** @var Op_dealDamage */
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
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    public function testAdjacentMonsterIsTarget(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    public function testNonAdjacentMonsterNotTarget(): void {
        // hex_13_7 is 2 hexes away from hex_11_8 — out of range 1
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    public function testMultipleAdjacentMonsters(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_11_7");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertCount(2, $moves);
    }

    public function testInRangeUsesHeroAttackRange(): void {
        // Bjorn has First Bow (attack_range=2), hex_13_7 is 2 hexes away
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        /** @var Op_dealDamage */
        $op = $this->game->machine->instanciateOperation("dealDamage(inRange)", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_13_7", $moves);
    }

    public function testInRange3ReachesDistance3(): void {
        // hex_14_6 is 3 hexes from hex_11_8
        $this->game->tokens->moveToken("monster_goblin_1", "hex_14_6");
        /** @var Op_dealDamage */
        $op = $this->game->machine->instanciateOperation("dealDamage(inRange3)", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_14_6", $moves);
    }

    public function testAdjDoesNotReachDistance2(): void {
        // hex_13_7 is 2 hexes away — adj should not reach it
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        /** @var Op_dealDamage */
        $op = $this->game->machine->instanciateOperation("dealDamage(adj)", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    // -------------------------------------------------------------------------
    // matchesFilter
    // -------------------------------------------------------------------------

    public function testFilterTrueMatchesAll(): void {
        // Default filter is "true" — should match any monster
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->createOp(); // dealDamage with no filter param
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    public function testFilterNotLegendExcludesLegend(): void {
        $this->game->tokens->moveToken("monster_legend_1_1", "hex_12_8");
        /** @var Op_dealDamage */
        $op = $this->game->machine->instanciateOperation("dealDamage(adj,'not_legend')", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    public function testFilterNotLegendIncludesNonLegend(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        /** @var Op_dealDamage */
        $op = $this->game->machine->instanciateOperation("dealDamage(adj,'not_legend')", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    public function testFilterRank3OrLegendMatchesRank3(): void {
        // Troll is rank 3
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        /** @var Op_dealDamage */
        $op = $this->game->machine->instanciateOperation("dealDamage(adj,'rank==3 or legend')", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    public function testFilterRank3OrLegendMatchesLegend(): void {
        $this->game->tokens->moveToken("monster_legend_1_1", "hex_12_8");
        /** @var Op_dealDamage */
        $op = $this->game->machine->instanciateOperation("dealDamage(adj,'rank==3 or legend')", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    public function testFilterRank3OrLegendExcludesRank1(): void {
        // Goblin is rank 1
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        /** @var Op_dealDamage */
        $op = $this->game->machine->instanciateOperation("dealDamage(adj,'rank==3 or legend')", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    public function testAdjacentHeroNotTarget(): void {
        $this->game->tokens->moveToken("hero_2", "hex_12_8");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayNotHasKey("hex_12_8", $moves);
    }

    // -------------------------------------------------------------------------
    // resolve
    // -------------------------------------------------------------------------

    public function testDeal1DamageToMonster(): void {
        // Goblin health=2 — 1 damage shouldn't kill it
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $this->assertEquals(1, $this->getDamage("monster_goblin_1"));
        $this->assertEquals("hex_12_8", $this->game->tokens->getTokenLocation("monster_goblin_1"));
    }

    public function testDeal2DamageKillsGoblin(): void {
        // Goblin health=2 — 2 damage kills it
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->createOp("2dealDamage");
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));
        // Red crystals should be cleaned up after kill
        $this->assertEquals(0, $this->getDamage("monster_goblin_1"));
    }

    public function testKillGrantsXp(): void {
        // Goblin xp=1 — killing it should add 1 yellow crystal to tableau
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $xpBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $op = $this->createOp("2dealDamage");
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $xpAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $this->assertEquals($xpBefore + 1, $xpAfter);
    }

    public function testNoXpIfNotKilled(): void {
        // Troll health=7 — 1 damage doesn't kill
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $xpBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $xpAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $this->assertEquals($xpBefore, $xpAfter);
    }

    public function testDamageStacksAcrossMultipleCalls(): void {
        // Brute health=3 — 1+1=2, should survive
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $op2 = $this->createOp();
        $op2->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $this->assertEquals(2, $this->getDamage("monster_brute_1"));
        $this->assertEquals("hex_12_8", $this->game->tokens->getTokenLocation("monster_brute_1"));
    }

    public function testDamageStacksAndKills(): void {
        // Brute health=3 — 1+2=3, should die
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $op2 = $this->createOp("2dealDamage");
        $op2->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_brute_1"));
    }

    public function testPresetTargetReturnsOnlyThatTarget(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_11_7");
        /** @var Op_dealDamage */
        $op = $this->game->machine->instanciateOperation("2dealDamage", PCOLOR, ["target" => "hex_12_8"]);
        $moves = $op->getPossibleMoves();
        $this->assertCount(1, $moves);
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    public function testNoDiceRolled(): void {
        // dealDamage is direct damage — no dice should appear on display_battle
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $diceOnDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        $this->assertCount(0, $diceOnDisplay);
    }

    public function testCrystalsTakenFromSupply(): void {
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $supplyBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", "supply_crystal_red"));
        $op = $this->createOp("2dealDamage");
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
        $supplyAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", "supply_crystal_red"));
        $this->assertEquals($supplyBefore - 2, $supplyAfter);
    }
}
