<?php

declare(strict_types=1);

final class Op_c_nailedTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->clearMachine();
        // Hero at hex_8_9 (Grimheim edge)
        $this->game->tokens->moveToken("hero_1", "hex_8_9");
    }

    /** Place marker_attack on a hex with given overkill state */
    private function setAttackMarker(string $hex, int $overkill): void {
        $this->game->tokens->moveToken("marker_attack", $hex, $overkill);
    }

    public function testNoOverkillReturnsError(): void {
        $this->setAttackMarker("hex_7_9", 0);
        $this->createOp("c_nailed");
        $this->assertNoValidTargets();
    }

    public function testNoMarkerReturnsError(): void {
        // marker_attack in limbo (no active attack)
        $this->createOp("c_nailed");
        $this->assertNoValidTargets();
    }

    public function testNoMonsterBehindReturnsError(): void {
        $this->setAttackMarker("hex_7_9", 2);
        $this->createOp("c_nailed");
        $this->assertNoValidTargets();
    }

    public function testFindsMonsterBehindTarget(): void {
        // Hero at hex_8_9, killed at hex_7_9, monster behind at hex_6_9 (distance 2 from hero)
        $this->game->tokens->moveToken("monster_goblin_1", "hex_6_9");

        $this->setAttackMarker("hex_7_9", 2);
        $this->assertValidTarget("hex_6_9");
    }

    public function testResolveDealsDamage(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_6_9");

        $this->setAttackMarker("hex_7_9", 1);
        $this->call_resolve("hex_6_9");
        $this->dispatchAll();

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_goblin_1");
        $this->assertCount(1, $crystals);
    }

    public function testResolveKillsMonsterWithEnoughOverkill(): void {
        // Goblin (health=2), pre-place 1 damage, overkill=3 → total 4 >= 2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_6_9");

        $this->game->effect_moveCrystals("hero_1", "red", 1, "monster_goblin_1", ["message" => ""]);
        $this->setAttackMarker("hex_7_9", 3);
        $this->call_resolve("hex_6_9");
        $this->dispatchAll();

        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));
    }

    public function testChainQueuesNextNailedTogether(): void {
        // Goblin (health=2), overkill=3 → kills with 1 overkill, chains
        $this->game->tokens->moveToken("monster_goblin_1", "hex_6_9");

        $this->setAttackMarker("hex_7_9", 3);

        $this->createOp("c_nailed(chain)");
        $this->call_resolve("hex_6_9");

        // Op_c_nailed queued applyDamage (the kill) and c_nailed(chain) (the next pierce).
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("applyDamage", $opTypes);
        $this->assertContains("c_nailed(chain)", $opTypes);

        // Dispatch the kill chain — Op_applyDamage moves marker_attack to the new
        // killed hex with the leftover overkill, which is what c_nailed(chain) reads.
        $this->dispatchAll();

        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));
        $this->assertEquals("hex_6_9", $this->game->tokens->getTokenLocation("marker_attack"));
    }

    public function testNoChainForLevelI(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_6_9");

        $this->setAttackMarker("hex_7_9", 3);

        $this->call_resolve("hex_6_9");

        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertNotContains("c_nailed(chain)", $opTypes);
        $this->assertNotContains("c_nailed", $opTypes);
    }
}
