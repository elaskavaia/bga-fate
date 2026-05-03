<?php

declare(strict_types=1);

/**
 * Tests for Op_blockXp — sets noXp flag on the queued Op_finishKill so the
 * subsequent kill cleanup skips the base + bonus XP award.
 *
 * Op_blockXp normally lives in a TMonsterKilled handler chain, between
 * Op_killed (filter) and the kill's finishKill cleanup. These tests set up
 * that arrangement directly: queue an Op_finishKill with target=$monsterId
 * and marker_attack pointing at the monster's hex, then dispatch a chain
 * containing blockXp and verify finishKill runs without awarding XP.
 */
final class Op_blockXpTest extends AbstractOpTestCase {
    private string $monsterHex = "hex_12_8";

    protected function setUp(): void {
        parent::setUp();
        $this->game->clearMachine();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    /** Mimic post-applyDamage state. Caller owns crystal placement. */
    private function setKilledMonster(string $monsterId): void {
        $this->game->tokens->moveToken($monsterId, $this->monsterHex);
        $this->game->tokens->moveToken("marker_attack", $this->monsterHex);
    }

    private function queueFinishKill(string $monsterId, int $amount = 0): void {
        $this->game->machine->queue("finishKill", $this->owner, [
            "target" => $monsterId,
            "attacker" => "hero_1",
            "amount" => $amount,
        ]);
    }

    public function testBlockXpPatchesQueuedFinishKill(): void {
        $monster = "monster_goblin_1";
        $this->setKilledMonster($monster);
        $this->queueFinishKill($monster);

        $this->createOp("blockXp");
        $this->call_resolve("confirm");

        // Re-read the patched op
        $row = $this->game->machine->findOperation(null, "finishKill");
        $patched = $this->game->machine->instantiateOperationFromDbRow($row);
        $this->assertTrue((bool) $patched->getDataField("noXp"), "noXp flag should be set on Op_finishKill");
        $this->assertEquals($monster, $patched->getDataField("target"), "Patch should preserve other data fields");
    }

    public function testBlockXpNoOpWhenNoFinishKillQueued(): void {
        $this->setKilledMonster("monster_goblin_1");
        // No finishKill in queue.
        $this->createOp("blockXp");
        // Should not throw, just silently no-op.
        $this->call_resolve("confirm");
        $this->assertNull($this->game->machine->findOperation(null, "finishKill"));
    }

    public function testBlockXpNoOpWhenNoMarkerAttack(): void {
        // marker_attack stays in limbo
        $monster = "monster_goblin_1";
        $this->game->tokens->moveToken($monster, $this->monsterHex);
        $this->queueFinishKill($monster);

        $this->createOp("blockXp");
        $this->call_resolve("confirm");

        $row = $this->game->machine->findOperation(null, "finishKill");
        $data = is_string($row["data"]) ? json_decode($row["data"], true) : $row["data"];
        $this->assertFalse((bool) ($data["noXp"] ?? false), "Without marker_attack, no patch");
    }

    // -------------------------------------------------------------------------
    // End-to-end: chain run through dispatch
    // -------------------------------------------------------------------------

    public function testFullChainSuppressesXpFromKill(): void {
        // Goblin has 2 health, 2 damage already on it (so applyDamage(0) detects kill).
        $monster = "monster_goblin_1";
        $this->setKilledMonster($monster);
        $this->game->effect_moveCrystals("hero_1", "red", 2, $monster, ["message" => ""]);

        $xpBefore = $this->countYellowCrystals($this->getPlayersTableau());

        // Mimic a TMonsterKilled-handler chain: blockXp runs, then finishKill cleans up.
        // Order in queue: blockXp first (rank 1), finishKill second (rank 2).
        // blockXp resolves first, patches finishKill's data; finishKill then skips XP.
        $this->game->machine->queue("finishKill", $this->owner, [
            "target" => $monster,
            "attacker" => "hero_1",
            "amount" => 2,
        ]);
        $this->game->machine->push("blockXp", $this->owner);
        $this->game->machine->dispatchAll();

        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation($monster));
        $this->assertEquals($xpBefore, $this->countYellowCrystals($this->getPlayersTableau()), "No XP awarded when blockXp is in chain");
    }

    public function testWithoutBlockXpKillStillGrantsXp(): void {
        // Sanity check: same setup without blockXp should grant XP.
        $monster = "monster_goblin_1";
        $this->setKilledMonster($monster);
        $this->game->effect_moveCrystals("hero_1", "red", 2, $monster, ["message" => ""]);

        $xpBefore = $this->countYellowCrystals($this->getPlayersTableau());

        $this->game->machine->queue("finishKill", $this->owner, [
            "target" => $monster,
            "attacker" => "hero_1",
            "amount" => 2,
        ]);
        $this->game->machine->dispatchAll();

        $this->assertEquals($xpBefore + 1, $this->countYellowCrystals($this->getPlayersTableau()), "Goblin grants 1 XP normally");
    }

    public function testBlockXpAlsoSuppressesBonusXp(): void {
        // Troll with 2 yellow bonus markers (Prey-style). Pre-place damage so applyDamage(0) detects kill.
        $monster = "monster_troll_1";
        $this->setKilledMonster($monster);
        $this->game->effect_moveCrystals("hero_1", "red", 7, $monster, ["message" => ""]);
        $this->game->effect_moveCrystals("hero_1", "yellow", 2, $monster, ["message" => ""]);

        $xpBefore = $this->countYellowCrystals($this->getPlayersTableau());

        $this->game->machine->queue("finishKill", $this->owner, [
            "target" => $monster,
            "attacker" => "hero_1",
            "amount" => 7,
        ]);
        $this->game->machine->push("blockXp", $this->owner);
        $this->game->machine->dispatchAll();

        // Troll grants 3 base XP + 2 bonus normally; blockXp suppresses both.
        $this->assertEquals($xpBefore, $this->countYellowCrystals($this->getPlayersTableau()), "blockXp suppresses base + bonus XP");
    }
}
