<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Tests for Op_in — location-gate predicate used inside r expressions.
 * Param matches the acting hero's hex against either named loc (e.g. "Grimheim")
 * or terrain (e.g. "forest"). Voids with ERR_PREREQ on mismatch.
 */
final class Op_inTest extends AbstractOpTestCase {
    public function testGatePassesWhenHeroInNamedLocation(): void {
        // Heroes start in Grimheim during init.
        $this->createOp("in(Grimheim)");
        $this->assertFalse($this->op->isVoid());
        $this->assertFalse($this->op->noValidTargets());
    }

    public function testGatePassesWhenHeroOnMatchingTerrain(): void {
        // hex_9_1 is a forest hex (DarkForest).
        $this->game->tokens->moveToken("hero_1", "hex_9_1");
        $this->createOp("in(forest)");
        $this->assertFalse($this->op->isVoid());
        $this->assertFalse($this->op->noValidTargets());
    }

    public function testGateVoidsWhenHeroElsewhere(): void {
        // Hero starts in Grimheim, gate expects forest.
        $this->createOp("in(forest)");
        $this->assertNoValidTargetsAndError(Material::ERR_PREREQ);
    }

    public function testGateVoidsWhenWrongNamedLocation(): void {
        $this->game->tokens->moveToken("hero_1", "hex_9_1");
        $this->createOp("in(Grimheim)");
        $this->assertNoValidTargetsAndError(Material::ERR_PREREQ);
    }

    public function testGateVoidsWhenParamMissing(): void {
        // Without a param there's nothing to match against.
        $this->createOp("in");
        $this->assertNoValidTargetsAndError(Material::ERR_PREREQ);
    }

    public function testResolveIsNoOp(): void {
        $this->createOp("in(Grimheim)");
        $before = $this->countGreenCrystals("supply_crystal_green");
        $this->op->action_resolve([Operation::ARG_TARGET => "confirm"]);
        $after = $this->countGreenCrystals("supply_crystal_green");
        $this->assertEquals($before, $after);
    }

    public function testOnTheRoad(): void {
        $this->game->tokens->moveToken("hero_1", "hex_11_7");
        $this->createOp("in(road)");
        $this->assertFalse($this->op->noValidTargets());
    }

    // -------------------------------------------------------------------------
    // Composition: gate chains with other costs/effects via paygain
    // -------------------------------------------------------------------------

    public function testChainWithGatePassingRunsEffect(): void {
        // in(Grimheim):spendMana:gainMana — when hero is in Grimheim, should:
        //   1) pass the gate (no-op)
        //   2) spend 1 mana from the source card
        //   3) regain 1 mana on the same card
        // Net effect: 0 mana change. Hero starts in Grimheim.
        $cardId = "card_ability_1_3";
        $manaBefore = $this->countGreenCrystals($cardId);
        $this->game->machine->push("in(Grimheim):spendMana:gainMana", $this->owner, ["card" => $cardId]);
        $this->game->machine->dispatchAll();

        $this->assertEquals($manaBefore, $this->countGreenCrystals($cardId), "mana spent then regained");
    }

    public function testChainVoidsWhenGateFails(): void {
        // Same chain but hero is on forest, gate expects Grimheim → whole chain voids.
        $cardId = "card_ability_1_3";
        $this->game->tokens->moveToken("hero_1", "hex_9_1");

        $manaBefore = $this->countGreenCrystals($cardId);
        $this->game->machine->push("in(Grimheim):spendMana:gainMana", $this->owner, ["card" => $cardId]);
        $this->game->machine->dispatchAll();

        $this->assertEquals($manaBefore, $this->countGreenCrystals($cardId), "no mana movement when chain voids");
    }
}
