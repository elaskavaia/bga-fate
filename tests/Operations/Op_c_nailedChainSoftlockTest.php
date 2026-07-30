<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;

/**
 * BGA #234580 - Boldur "Nailed Together II" piercing-damage softlock.
 *
 * When the chain (Level II) kills the next monster with EXACTLY 0 overkill left,
 * Op_c_nailed::resolve() re-queues c_nailed(chain) on any kill ($willKill), but
 * the re-queued op is a plain queue() with no l_skip flag. The card-driven initial
 * op gets l_skip=true from Card::createOperationForCardEffect(), so when it is void
 * it auto-skips; the chain re-queue does NOT, so a 0-overkill chain op is void yet
 * non-skippable -> a mandatory, unsatisfiable player prompt
 * ("Choose a monster to deal 0 piercing damage to" / "No overkill damage").
 */
final class Op_c_nailedChainSoftlockTest extends AbstractOpTestCase {
    function getOperationType(): string {
        return "c_nailed";
    }

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_8_9");
    }

    /**
     * documents buggy behavior for BGA #234580; flip once fixed.
     *
     * Chain pierces into a health-2 goblin behind the first kill with overkill=2:
     * an exact kill leaving 0 overkill. The chain still re-queues c_nailed(chain),
     * which is void (0 overkill) but cannot skip -> softlock.
     */
    public function testChainReoffersZeroOverkillPierceAfterExactKill(): void {
        // Hero at hex_8_9, first kill at hex_7_9, goblin (health=2) behind at hex_6_9.
        $this->game->tokens->moveToken("monster_goblin_1", "hex_6_9");
        // overkill carried from the first kill = 2, exactly lethal to the goblin.
        $this->game->tokens->moveToken("marker_attack", "hex_7_9", 2);

        $this->createOp("c_nailed(chain)");
        $this->call_resolve("hex_6_9");

        // Run the queued applyDamage/finishKill; dispatch stops at the void, non-skippable
        // chain op (which asks for a player turn), leaving it as the top operation.
        $this->dispatchAll();

        // Exact kill: 0 overkill remains.
        $this->assertEquals(0, $this->game->tokens->getTokenState("marker_attack"), "exact kill leaves 0 overkill");

        // BUG: the chain op is still queued as a mandatory prompt (softlock), not skipped.
        $op = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertNotNull($op, "chain op still pending (bug: re-queued despite 0 overkill)");
        $this->assertStringContainsString("c_nailed", $op->getType(), "the pending softlock op is c_nailed(chain)");

        // It is void and reports the reported error, and would prompt for 0 piercing damage.
        $this->assertTrue($op->noValidTargets(), "0-overkill chain op has no valid targets");
        $this->assertEquals(Material::ERR_NOT_APPLICABLE, $op->getErrorCode(), "reports 'No overkill damage'");
        $this->assertEquals(0, $op->getExtraArgs()["overkill"], "prompt would render '0 piercing damage'");

        // The actual defect: the re-queued op is NOT skippable (no l_skip), so it cannot
        // auto-resolve and drops the player into an unsatisfiable prompt = softlock.
        $this->assertFalse($op->canSkip(), "BUG #234580: re-queued chain op is not skippable");
        $this->assertFalse($op->canResolveAutomatically(), "BUG #234580: void op cannot auto-resolve -> player softlock");
    }
}
