<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_gainXp;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_gainXpTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // setupGameTables seeds 2 starting XP on tableau — drain so tests start from 0
        foreach (array_keys($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", $this->getPlayersTableau())) as $key) {
            $this->game->tokens->moveToken($key, "supply_crystal_yellow");
        }
    }

    private function getXp(): int {
        return $this->countYellowCrystals($this->getPlayersTableau());
    }

    // -------------------------------------------------------------------------
    // resolve: basic XP gain
    // -------------------------------------------------------------------------

    public function testResolveGains1Xp(): void {
        $op = $this->op;
        $this->call_resolve();
        $this->assertEquals(1, $this->getXp());
    }

    public function testResolveGains2Xp(): void {
        $this->op = $this->createOp("2gainXp");
        $this->call_resolve();
        $this->assertEquals(2, $this->getXp());
    }

    public function testResolveTakesFromSupply(): void {
        $supplyBefore = $this->countYellowCrystals("supply_crystal_yellow");
        $this->op = $this->createOp("2gainXp");
        $this->call_resolve();
        $supplyAfter = $this->countYellowCrystals("supply_crystal_yellow");
        $this->assertEquals($supplyBefore - 2, $supplyAfter);
    }

    public function testXpStacks(): void {
        $op = $this->op;
        $this->call_resolve();
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
        $this->op = $this->createOp("2gainXp(grimheim)");
        $this->assertEquals(0, $this->op->getErrorCode());
    }

    public function testGrimheimConditionFailsOutsideGrimheim(): void {
        // hero_1 is on hex_11_8 (plains, not Grimheim)
        $this->op = $this->createOp("2gainXp(grimheim)");
        $this->assertNoValidTargets();
    }

    public function testGrimheimConditionResolves(): void {
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
        $this->game->hexMap->invalidateOccupancy();
        $this->op = $this->createOp("2gainXp(grimheim)");
        $this->call_resolve();
        $this->assertEquals(2, $this->getXp());
    }

    // -------------------------------------------------------------------------
    // condition: adjMountain
    // -------------------------------------------------------------------------

    public function testAdjMountainConditionPassesWhenAdjacent(): void {
        $this->game->tokens->moveToken("hero_1", "hex_14_2"); // adjacent to hex_14_1 (mountain)
        $this->game->hexMap->invalidateOccupancy();
        $this->op = $this->createOp("2gainXp(adjMountain)");
        $this->assertEquals(0, $this->op->getErrorCode());
    }

    public function testAdjMountainConditionFailsWhenNotAdjacent(): void {
        // hero_1 is on hex_11_8 — no adjacent mountains
        $this->op = $this->createOp("2gainXp(adjMountain)");
        $this->assertNoValidTargets();
    }

    public function testAdjMountainConditionResolves(): void {
        $this->game->tokens->moveToken("hero_1", "hex_14_2");
        $this->game->hexMap->invalidateOccupancy();
        $this->op = $this->createOp("2gainXp(adjMountain)");
        $this->call_resolve();
        $this->assertEquals(2, $this->getXp());
    }

    // -------------------------------------------------------------------------
    // no condition: always works
    // -------------------------------------------------------------------------

    public function testNoConditionAlwaysValid(): void {
        $this->op = $this->createOp("2gainXp");
        $this->assertEquals(0, $this->op->getErrorCode());
    }
}
