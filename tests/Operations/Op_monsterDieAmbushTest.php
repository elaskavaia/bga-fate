<?php

declare(strict_types=1);

/**
 * Tests for Op_monsterDieAmbush — Phase D4.
 * For each hero not in Grimheim, queues a spawn(goblin) op (owner = hero
 * color). Heroes in Grimheim are silently skipped per A6.
 */
final class Op_monsterDieAmbushTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_12_7");
        $this->game->hexMap->invalidateOccupancy();
    }

    public function testQueuesSpawnGoblinForActiveHero(): void {
        $this->call_resolve();
        $queued = $this->game->machine->getTopOperations($this->owner);
        $types = array_map(fn($op) => $op["type"], $queued);
        $this->assertContains("spawn(goblin)", $types, "ambush must queue a spawn(goblin) op for the live hero");
    }

    public function testGoblinLandsOnAdjacentHexAfterDispatch(): void {
        $heroHex = $this->game->tokens->getTokenLocation("hero_1");
        $adjacent = $this->game->hexMap->getAdjacentHexes($heroHex);

        $this->call_resolve();
        $this->dispatchAll();

        $goblinsAdjacent = 0;
        foreach ($adjacent as $hex) {
            $goblinsAdjacent += count($this->game->tokens->getTokensOfTypeInLocation("monster_goblin", $hex));
        }
        $this->assertEquals(1, $goblinsAdjacent, "exactly one goblin should land on a hex adjacent to the hero");
    }

    public function testHeroInGrimheimIsSkipped(): void {
        $this->game->tokens->moveToken("hero_1", "hex_10_9"); // Grimheim
        $this->game->hexMap->invalidateOccupancy();

        $this->call_resolve();
        $queued = $this->game->machine->getTopOperations($this->owner);
        $types = array_map(fn($op) => $op["type"], $queued);
        $this->assertNotContains("spawn(goblin)", $types, "no spawn op queued for a hero standing in Grimheim");
    }
}
