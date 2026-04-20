<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Tests for Op_adj — adjacency-gate predicate used inside r expressions.
 * Param matches each of the acting hero's adjacent hexes against either named loc
 * or terrain. Voids with ERR_PREREQ on no match.
 */
final class Op_adjTest extends AbstractOpTestCase {
    public function testGatePassesWhenHeroAdjacentToTerrain(): void {
        // hex_14_2 is adjacent to a mountain hex (hex_14_1) per existing map data.
        $this->game->tokens->moveToken("hero_1", "hex_14_2");
        $this->createOp("adj(mountain)");
        $this->assertFalse($this->op->isVoid());
        $this->assertFalse($this->op->noValidTargets());
    }

    public function testGateVoidsWhenHeroNotAdjacent(): void {
        // Hero starts in Grimheim — no mountain neighbors.
        $this->createOp("adj(mountain)");
        $this->assertNoValidTargetsAndError(Material::ERR_PREREQ);
    }

    public function testGateVoidsOnUnknownLocation(): void {
        $this->createOp("adj(nonsense)");
        $this->assertNoValidTargetsAndError(Material::ERR_PREREQ);
    }

    public function testGateVoidsWhenParamMissing(): void {
        $this->createOp("adj");
        $this->assertNoValidTargetsAndError(Material::ERR_PREREQ);
    }

    public function testResolveIsNoOp(): void {
        $this->game->tokens->moveToken("hero_1", "hex_14_2");
        $this->createOp("adj(mountain)");
        $before = $this->countGreenCrystals("supply_crystal_green");
        $this->op->action_resolve([Operation::ARG_TARGET => "confirm"]);
        $after = $this->countGreenCrystals("supply_crystal_green");
        $this->assertEquals($before, $after);
    }

    // -------------------------------------------------------------------------
    // Composition: gate chains with gainXp via paygain (Miner pattern)
    // -------------------------------------------------------------------------

    public function testChainWithGatePassingRunsEffect(): void {
        // adj(mountain):2gainXp — when hero is adjacent to a mountain, gain 2 XP.
        $this->game->tokens->moveToken("hero_1", "hex_14_2");
        $xpBefore = $this->getXp();
        $this->game->machine->push("adj(mountain):2gainXp", $this->owner);
        $this->game->machine->dispatchAll();
        $this->assertEquals($xpBefore + 2, $this->getXp(), "2 XP gained when adjacent to mountain");
    }

    public function testChainVoidsWhenGateFails(): void {
        // Hero is in Grimheim (no adjacent mountains) — chain voids, no XP gained.
        $xpBefore = $this->getXp();
        $this->game->machine->push("adj(mountain):2gainXp", $this->owner);
        $this->game->machine->dispatchAll();
        $this->assertEquals($xpBefore, $this->getXp(), "no XP when chain voids");
    }

    private function getXp(): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_{$this->owner}"));
    }
}
