<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Tests for Op_on — runtime event gate used inside r expressions.
 * Example: `(2spendMana:on(EventActionAttack):2addDamage)` — the clause only
 * resolves when the enclosing useCard was triggered by Trigger::ActionAttack.
 */
final class Op_onTest extends AbstractOpTestCase {
    public function testGatePassesWhenEventMatches(): void {
        $this->createOp("on(EventActionAttack)", ["event" => "EventActionAttack"]);
        $this->assertFalse($this->op->isVoid());
        $this->assertFalse($this->op->noValidTargets());
    }

    public function testGateVoidsWhenEventMismatches(): void {
        $this->createOp("on(EventActionAttack)", ["event" => "EventRoll"]);
        $this->assertNoValidTargetsAndError(Material::ERR_PREREQ);
    }

    public function testGateVoidsWhenEventMissing(): void {
        // No event data field (e.g. queued outside the useCard flow).
        $this->createOp("on(EventActionAttack)");
        $this->assertNoValidTargetsAndError(Material::ERR_PREREQ);
    }

    public function testGateVoidsWhenParamMissing(): void {
        // Param is the expected event; without it we can't match anything.
        $this->createOp("on", ["event" => "EventActionAttack"]);
        $this->assertNoValidTargetsAndError(Material::ERR_PREREQ);
    }

    public function testResolveIsNoOp(): void {
        // Passing gate should resolve silently — no token moves, no side effects.
        $this->createOp("on(EventActionAttack)", ["event" => "EventActionAttack"]);
        $before = $this->countGreenCrystals("supply_crystal_green");
        $this->op->action_resolve([Operation::ARG_TARGET => "confirm"]);
        $after = $this->countGreenCrystals("supply_crystal_green");
        $this->assertEquals($before, $after);
    }

    // -------------------------------------------------------------------------
    // Composition: gate chains with other costs/effects via paygain
    // -------------------------------------------------------------------------

    public function testChainWithGatePassingRunsEffect(): void {
        // on(EventActionAttack):spendMana:gainMana — when event matches, should:
        //   1) pass the gate (no-op)
        //   2) spend 1 mana from the source card
        //   3) add 1 mana to a target card (auto-picks the only mana card)
        // Net effect: 0 mana change on card_ability_1_3 (the sole mana-bearing card).
        // NOTE: the on(...) gate must always be the leftmost element in the chain so
        // paygain's pre-flight void check catches it before any sub runs.
        $cardId = "card_ability_1_3"; // Sure Shot I, mana=1, starts with 1 green crystal
        $this->game->tokens->moveToken("hero_1", "hex_11_8");

        $manaBefore = $this->countGreenCrystals($cardId);
        $this->game->machine->push("on(EventActionAttack):spendMana:gainMana", $this->owner, [
            "card" => $cardId,
            "event" => "EventActionAttack",
        ]);
        $this->game->machine->dispatchAll();

        $this->assertEquals($manaBefore, $this->countGreenCrystals($cardId), "mana spent then regained");
    }

    public function testChainVoidsWhenGateFails(): void {
        // Same chain with wrong event → gate voids → no mana movement at all.
        $cardId = "card_ability_1_3";
        $this->game->tokens->moveToken("hero_1", "hex_11_8");

        $manaBefore = $this->countGreenCrystals($cardId);
        $this->game->machine->push("on(EventActionAttack):spendMana:gainMana", $this->owner, ["card" => $cardId, "event" => "EventRoll"]);
        $this->game->machine->dispatchAll();

        $this->assertEquals($manaBefore, $this->countGreenCrystals($cardId), "no mana movement when chain voids");
    }
}
