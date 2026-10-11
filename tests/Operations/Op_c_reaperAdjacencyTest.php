<?php

declare(strict_types=1);

final class Op_c_reaperAdjacencyTest extends AbstractOpTestCase {
    function getOperationType(): string {
        return "c_reaper";
    }

    protected function setUp(): void {
        $this->init(3);
    }

    public function testSecondaryAdjacentToPrimaryButNotToHeroIsOfferedBug241160(): void {
        $this->game->tokens->moveToken("hero_3", "hex_7_9");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_7_8");
        $this->game->tokens->moveToken("monster_goblin_2", "hex_6_8");
        $this->game->tokens->moveToken("marker_attack", "hex_7_8", 0);
        $this->createOp();

        $heroAdjacent = $this->game->hexMap->getAdjacentHexes("hex_7_9");
        $this->assertContains("hex_7_8", $heroAdjacent);
        $this->assertNotContains("hex_6_8", $heroAdjacent);

        // Documents buggy behavior for BGA #241160; flip the assertion when fixed
        $this->assertValidTarget("hex_6_8");
    }
}
