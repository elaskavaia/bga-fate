<?php

declare(strict_types=1);

/**
 * Op_addRoll: rolls dice without sweeping existing dice off display_battle.
 * Used by "add N attack dice to this attack" effects (e.g. Mastery).
 * Gated on dice already being present — void outside an active attack.
 */
final class Op_addRollTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
    }

    private function diceOnBattle(): array {
        return $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
    }

    // -------------------------------------------------------------------------
    // Gating: addRoll is only valid when dice are already on display_battle
    // -------------------------------------------------------------------------

    public function testVoidWhenNoExistingDice(): void {
        $op = $this->createOp("4addRoll");
        $this->assertTrue($op->isVoid(), "addRoll should be void with no dice on display_battle");
    }

    public function testValidWhenDicePresent(): void {
        $this->game->tokens->moveToken("die_attack_1", "display_battle", 6);
        $this->createOp("4addRoll");
        $this->assertValidTarget("hex_12_8");
    }

    // -------------------------------------------------------------------------
    // resolve: adds dice to existing pool without sweeping
    // -------------------------------------------------------------------------

    public function testResolveAddsDiceWithoutSweeping(): void {
        // Seed 2 existing dice on display_battle
        $this->game->tokens->moveToken("die_attack_1", "display_battle", 6);
        $this->game->tokens->moveToken("die_attack_2", "display_battle", 6);

        $this->createOp("3addRoll");
        $this->call_resolve("hex_12_8");

        // Should now have 2 + 3 = 5 dice
        $this->assertCount(5, $this->diceOnBattle());
    }

    public function testResolveQueuesResolveHits(): void {
        $this->game->tokens->moveToken("die_attack_1", "display_battle", 6);
        $this->createOp("2addRoll");
        $this->call_resolve("hex_12_8");
        $opTypes = array_map(fn($o) => $o["type"], $this->game->machine->getAllOperations(PCOLOR));
        $this->assertContains("resolveHits", $opTypes);
    }

    public function testOriginalDicePreserved(): void {
        // Seed one die with a known state (rune side = 3)
        $this->game->tokens->moveToken("die_attack_1", "display_battle", 3);

        $this->createOp("2addRoll");
        $this->call_resolve("hex_12_8");

        // die_attack_1 should still be on display_battle (not swept to supply)
        $this->assertEquals("display_battle", $this->game->tokens->getTokenLocation("die_attack_1"));
        $this->assertCount(3, $this->diceOnBattle());
    }
}
