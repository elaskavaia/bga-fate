<?php

declare(strict_types=1);

final class Op_c_queenTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->clearMachine();
    }

    public function testNoAdjacentMonsterNoValidTargets(): void {
        $this->createOp("c_queen");
        $this->assertNoValidTargets();
    }

    public function testAdjacentMonsterIsValidTarget(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->createOp("c_queen");
        $this->assertValidTarget("hex_12_8");
    }

    public function testResolveDealsDamage(): void {
        // Goblin has health=2; deal 1 damage — survives
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        $this->createOp("c_queen");
        $this->call_resolve("hex_12_8");
        $this->dispatchAll();

        $this->assertCount(1, $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_goblin_1"));
    }

    public function testResolveSwapsWhenMonsterSurvives(): void {
        // 1 damage is not enough to kill goblin (health=2) — swap should happen
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        $this->createOp("c_queen");
        $this->call_resolve("hex_12_8");
        $this->dispatchAll(); // drain Op_step from the queue

        // Monster moved to hero's old hex
        $this->assertEquals("hex_11_8", $this->game->tokens->getTokenLocation("monster_goblin_1"));
        // Hero moved to monster's old hex
        $this->assertEquals("hex_12_8", $this->game->tokens->getTokenLocation("hero_1"));
    }

    public function testResolveMovesHeroEvenWhenMonsterKilled(): void {
        // 2 damage kills goblin (health=2); hero still moves into the vacated hex —
        // the movement is the tactic, the damage is its thematic side effect.
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        $this->createOp("2c_queen");
        $this->call_resolve("hex_12_8");
        $this->dispatchAll();

        // Monster dead → off the map
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));
        // Hero moved to where the monster was
        $this->assertEquals("hex_12_8", $this->game->tokens->getTokenLocation("hero_1"));
    }

    public function testLevelIIDealsMoreDamageAndHeroStillMoves(): void {
        // 4 damage overkills goblin (health=2); hero moves into the vacated hex
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        $this->createOp("4c_queen");
        $this->call_resolve("hex_12_8");
        $this->dispatchAll();

        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));
        $this->assertEquals("hex_12_8", $this->game->tokens->getTokenLocation("hero_1"));
    }

    public function testMountainMonsterFlaggedWithPrereqError(): void {
        // Move hero to a hex that has a mountain neighbor.
        $this->game->tokens->moveToken("hero_1", "hex_13_2");
        $mountainHex = $this->findAdjacentMountainHex("hex_13_2");
        $this->assertNotNull($mountainHex, "test map should have a mountain hex adjacent to hex_13_2");
        $this->game->tokens->moveToken("monster_goblin_1", $mountainHex);

        $this->createOp("c_queen");
        $this->assertTargetError($mountainHex, \Bga\Games\Fate\Material::ERR_PREREQ);
    }

    private function findAdjacentMountainHex(string $fromHex): ?string {
        foreach ($this->game->hexMap->getAdjacentHexes($fromHex) as $h) {
            if ($this->game->hexMap->isImpassable($h, "hero")) {
                return $h;
            }
        }
        return null;
    }
}
