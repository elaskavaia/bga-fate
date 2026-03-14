<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_gainXp;
use Bga\Games\Fate\Tests\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_gainXpTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function createOp(string $expr = "gainXp"): Op_gainXp {
        /** @var Op_gainXp */
        $op = $this->game->machine->instanciateOperation($expr, PCOLOR);
        return $op;
    }

    private function getXp(): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
    }

    // -------------------------------------------------------------------------
    // resolve: basic XP gain
    // -------------------------------------------------------------------------

    public function testResolveGains1Xp(): void {
        $op = $this->createOp();
        $op->action_resolve([]);
        $this->assertEquals(1, $this->getXp());
    }

    public function testResolveGains2Xp(): void {
        $op = $this->createOp("2gainXp");
        $op->action_resolve([]);
        $this->assertEquals(2, $this->getXp());
    }

    public function testResolveTakesFromSupply(): void {
        $supplyBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "supply_crystal_yellow"));
        $op = $this->createOp("2gainXp");
        $op->action_resolve([]);
        $supplyAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "supply_crystal_yellow"));
        $this->assertEquals($supplyBefore - 2, $supplyAfter);
    }

    public function testXpStacks(): void {
        $op = $this->createOp();
        $op->action_resolve([]);
        $op2 = $this->createOp("2gainXp");
        $op2->action_resolve([]);
        $this->assertEquals(3, $this->getXp());
    }

    // -------------------------------------------------------------------------
    // condition: grimheim
    // -------------------------------------------------------------------------

    public function testGrimheimConditionPassesInGrimheim(): void {
        $this->game->tokens->moveToken("hero_1", "hex_9_9"); // Grimheim
        $this->game->hexMap->invalidateOccupancy();
        $op = $this->createOp("2gainXp(grimheim)");
        $this->assertEquals(0, $op->getErrorCode());
    }

    public function testGrimheimConditionFailsOutsideGrimheim(): void {
        // hero_1 is on hex_11_8 (plains, not Grimheim)
        $op = $this->createOp("2gainXp(grimheim)");
        $this->assertNotEquals(0, $op->getErrorCode());
    }

    public function testGrimheimConditionResolves(): void {
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
        $this->game->hexMap->invalidateOccupancy();
        $op = $this->createOp("2gainXp(grimheim)");
        $op->action_resolve([]);
        $this->assertEquals(2, $this->getXp());
    }

    // -------------------------------------------------------------------------
    // condition: adjMountain
    // -------------------------------------------------------------------------

    public function testAdjMountainConditionPassesWhenAdjacent(): void {
        $this->game->tokens->moveToken("hero_1", "hex_14_2"); // adjacent to hex_14_1 (mountain)
        $this->game->hexMap->invalidateOccupancy();
        $op = $this->createOp("2gainXp(adjMountain)");
        $this->assertEquals(0, $op->getErrorCode());
    }

    public function testAdjMountainConditionFailsWhenNotAdjacent(): void {
        // hero_1 is on hex_11_8 — no adjacent mountains
        $op = $this->createOp("2gainXp(adjMountain)");
        $this->assertNotEquals(0, $op->getErrorCode());
    }

    public function testAdjMountainConditionResolves(): void {
        $this->game->tokens->moveToken("hero_1", "hex_14_2");
        $this->game->hexMap->invalidateOccupancy();
        $op = $this->createOp("2gainXp(adjMountain)");
        $op->action_resolve([]);
        $this->assertEquals(2, $this->getXp());
    }

    // -------------------------------------------------------------------------
    // no condition: always works
    // -------------------------------------------------------------------------

    public function testNoConditionAlwaysValid(): void {
        $op = $this->createOp("2gainXp");
        $this->assertEquals(0, $op->getErrorCode());
    }
}
