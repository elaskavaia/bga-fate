<?php

declare(strict_types=1);

final class Op_c_nailedTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        // Hero at hex_8_9 (Grimheim edge)
        $this->game->tokens->moveToken("hero_1", "hex_8_9");
    }

    /** Place marker_attack on a hex with given overkill state */
    private function setAttackMarker(string $hex, int $overkill): void {
        $this->game->tokens->moveToken("marker_attack", $hex, $overkill);
    }

    public function testNoOverkillReturnsError(): void {
        $this->setAttackMarker("hex_7_9", 0);
        $op = $this->createOp("c_nailed");
        $this->assertNotEquals(0, $op->getErrorCode());
    }

    public function testNoMarkerReturnsError(): void {
        // marker_attack in limbo (no active attack)
        $op = $this->createOp("c_nailed");
        $this->assertNotEquals(0, $op->getErrorCode());
    }

    public function testNoMonsterBehindReturnsError(): void {
        $this->setAttackMarker("hex_7_9", 2);
        $op = $this->createOp("c_nailed");
        $this->assertNotEquals(0, $op->getErrorCode());
    }

    public function testFindsMonsterBehindTarget(): void {
        // Hero at hex_8_9, killed at hex_7_9, monster behind at hex_6_9 (distance 2 from hero)
        $this->game->tokens->moveToken("monster_goblin_1", "hex_6_9");
        $this->game->hexMap->invalidateOccupancy();
        $this->setAttackMarker("hex_7_9", 2);
        $this->assertValidTarget("hex_6_9");
    }

    public function testResolveDealsDamage(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_6_9");
        $this->game->hexMap->invalidateOccupancy();
        $this->setAttackMarker("hex_7_9", 1);
        $this->call_resolve("hex_6_9");

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_goblin_1");
        $this->assertCount(1, $crystals);
    }

    public function testResolveKillsMonsterWithEnoughOverkill(): void {
        // Goblin (health=2), pre-place 1 damage, overkill=3 → total 4 >= 2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_6_9");
        $this->game->hexMap->invalidateOccupancy();
        $this->game->effect_moveCrystals("hero_1", "red", 1, "monster_goblin_1", ["message" => ""]);
        $this->setAttackMarker("hex_7_9", 3);
        $this->call_resolve("hex_6_9");

        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));
    }

    public function testChainQueuesNextNailedTogether(): void {
        // Goblin (health=2), overkill=3 → kills with 1 overkill, chains
        $this->game->tokens->moveToken("monster_goblin_1", "hex_6_9");
        $this->game->hexMap->invalidateOccupancy();
        $this->setAttackMarker("hex_7_9", 3);

        $this->op = $this->createOp("c_nailed(chain)");
        $this->call_resolve("hex_6_9");

        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));

        // Should have queued another c_nailed(chain)
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("c_nailed(chain)", $opTypes);

        // marker_attack should be updated to hex_6_9 with new overkill
        $this->assertEquals("hex_6_9", $this->game->tokens->getTokenLocation("marker_attack"));
    }

    public function testNoChainForLevelI(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_6_9");
        $this->game->hexMap->invalidateOccupancy();
        $this->setAttackMarker("hex_7_9", 3);

        $this->call_resolve("hex_6_9");

        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertNotContains("c_nailed(chain)", $opTypes);
        $this->assertNotContains("c_nailed", $opTypes);
    }
}
