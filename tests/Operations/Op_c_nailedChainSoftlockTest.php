<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;

/**
 * BGA #234580 - Boldur "Nailed Together II" piercing-damage softlock.
 *
 * Op_c_nailed::resolve() re-queues c_nailed(chain) on any kill ($willKill), so an
 * EXACT kill re-queues a chain step with 0 overkill left, which is void. It used to
 * be queued without l_skip (unlike the card-driven op, which gets l_skip=true from
 * Card::createOperationForCardEffect), making it void yet non-skippable: a mandatory,
 * unsatisfiable prompt ("Choose a monster to deal 0 piercing damage to").
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
     * Chain pierces into a health-2 goblin behind the first kill with overkill=2:
     * an exact kill leaving 0 overkill. The chain still re-queues c_nailed(chain),
     * which is void, so it must be able to skip itself out of the way.
     */
    public function testZeroOverkillChainStepSkipsItselfAfterExactKill(): void {
        // Hero at hex_8_9, first kill at hex_7_9, goblin (health=2) behind at hex_6_9.
        $this->game->tokens->moveToken("monster_goblin_1", "hex_6_9");
        // overkill carried from the first kill = 2, exactly lethal to the goblin.
        $this->game->tokens->moveToken("marker_attack", "hex_7_9", 2);

        $this->createOp("c_nailed(chain)");
        $this->call_resolve("hex_6_9");

        // Runs the queued applyDamage/finishKill, then the void chain step.
        $this->dispatchAll();

        // Exact kill: 0 overkill remains.
        $this->assertEquals(0, $this->game->tokens->getTokenState("marker_attack"), "exact kill leaves 0 overkill");

        // The void chain step skipped itself away instead of prompting for 0 piercing damage.
        $this->assertNull($this->game->machine->createTopOperationFromDbForOwner(null), "no pending op left, no softlock");
    }

    /** The other void reason, and the more common one: the chain killed the last monster in the line. */
    public function testChainStepWithNoMonsterBehindSkipsItself(): void {
        // Nothing at hex_5_9 behind the goblin.
        $this->game->tokens->moveToken("monster_goblin_1", "hex_6_9");
        // overkill 3 vs health 2: kills with 1 to spare, but there is nowhere to send it.
        $this->game->tokens->moveToken("marker_attack", "hex_7_9", 3);

        $this->createOp("c_nailed(chain)");
        $this->call_resolve("hex_6_9");
        $this->dispatchAll();

        $this->assertNull($this->game->machine->createTopOperationFromDbForOwner(null), "no pending op left, no softlock");
    }

    /** A chain step that still has overkill and a monster behind must NOT skip itself. */
    public function testChainStepWithOverkillStillPierces(): void {
        $this->game->tokens->moveToken("monster_brute_1", "hex_6_9");
        // overkill 2 vs brute health 3: survives with 2 damage, so the chain ends there.
        $this->game->tokens->moveToken("marker_attack", "hex_7_9", 2);

        $this->createOp("c_nailed(chain)");
        $this->call_resolve("hex_6_9");
        $this->dispatchAll();

        $this->assertEquals(2, $this->game->getCharacter("monster_brute_1")->getDamage(), "brute took the pierce damage");
    }

    /** The void case reports the error players saw, rather than silently having no targets. */
    public function testZeroOverkillChainStepIsVoidWithNoOverkillError(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_6_9");
        $this->game->tokens->moveToken("marker_attack", "hex_7_9", 0);

        $op = $this->createOp("c_nailed(chain)");

        $this->assertTrue($op->noValidTargets(), "0-overkill chain op has no valid targets");
        $this->assertEquals(Material::ERR_NOT_APPLICABLE, $op->getErrorCode(), "reports 'No overkill damage'");
        $this->assertTrue($op->canSkip(), "void chain op is skippable");
        $this->assertTrue($op->canResolveAutomatically(), "void chain op auto-resolves, no player prompt");
    }
}
