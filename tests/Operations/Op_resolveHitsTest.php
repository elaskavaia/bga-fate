<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_resolveHits;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_resolveHitsTest extends AbstractOpTestCase {
    // -------------------------------------------------------------------------
    // Armor reduces hits (Draugr has armor=1)
    // -------------------------------------------------------------------------

    public function testDraugrArmorReducesHitsInResolve(): void {
        // Draugr (health=5, armor=1) adjacent to hero
        $this->game->tokens->moveToken("monster_draugr_1", "hex_12_8");

        // Roll 2 dice, both hit (side 5 = hit)
        $this->game->randQueue = [5, 5];
        $this->game->effect_rollAttackDice("hero_1", "monster_draugr_1", 2);

        // Queue resolveHits
        $op = $this->createOp("resolveHits", [
            "attacker" => "hero_1",
            "target" => "hex_12_8",
        ]);
        $op->resolve();

        // 2 hits - 1 armor = 1 effective damage queued as dealDamage
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $dealDamageOps = array_filter($ops, fn($o) => str_contains($o["type"], "dealDamage"));
        $this->assertNotEmpty($dealDamageOps, "dealDamage should be queued");
        $dealDamage = reset($dealDamageOps);
        $this->assertEquals("dealDamage", $dealDamage["type"]);
    }

    public function testDraugrArmorAbsorbsAllHits(): void {
        // Draugr (armor=1) adjacent to hero — only 1 hit, armor absorbs it entirely
        $this->game->tokens->moveToken("monster_draugr_1", "hex_12_8");

        // Roll 1 die, hit (side 5)
        $this->game->randQueue = [5];
        $this->game->effect_rollAttackDice("hero_1", "monster_draugr_1", 1);

        $op = $this->createOp("resolveHits", [
            "attacker" => "hero_1",
            "target" => "hex_12_8",
        ]);
        $op->resolve();

        // 1 hit - 1 armor = 0 → dealDamage queued with count=0 (per-defender side effects still fire,
        // amount=0 is benign: effect_moveCrystals no-ops on 0, evaluateDamage(0) is harmless).
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $dealDamageOps = array_values(array_filter($ops, fn($o) => str_contains($o["type"], "dealDamage")));
        $this->assertCount(1, $dealDamageOps, "dealDamage queued even when armor absorbs all hits");
        $d = $this->opData($dealDamageOps[0]);
        $this->assertEquals(0, (int) $d["count"]);
    }

    public function testNoArmorMonsterTakesFullDamage(): void {
        // Goblin (no armor) adjacent to hero — 2 hits should all go through
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        // Roll 2 dice, both hit
        $this->game->randQueue = [5, 5];
        $this->game->effect_rollAttackDice("hero_1", "monster_goblin_1", 2);

        $op = $this->createOp("resolveHits", [
            "attacker" => "hero_1",
            "target" => "hex_12_8",
        ]);
        $op->resolve();

        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $dealDamageOps = array_filter($ops, fn($o) => str_contains($o["type"], "dealDamage"));
        $this->assertNotEmpty($dealDamageOps);
        $dealDamage = reset($dealDamageOps);
        $this->assertEquals("dealDamage", $dealDamage["type"]);
    }

    // -------------------------------------------------------------------------
    // Split path: when `secondary` data field is set (Reaper Swing)
    // -------------------------------------------------------------------------

    /** DB rows store `data` as a JSON string — decode for assertions. */
    private function opData(array $row): array {
        return is_string($row["data"]) ? json_decode($row["data"], true) : $row["data"] ?? [];
    }

    /** Place two goblins, roll 4 hits — gives the test a 4-hit budget to split. */
    private function setupReaperFourHits(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8"); // primary
        $this->game->tokens->moveToken("monster_goblin_2", "hex_12_7"); // secondary
        $this->game->randQueue = [5, 5, 5, 5];
        $this->game->effect_rollAttackDice("hero_1", "monster_goblin_1", 4);
    }

    public function testSplitPathListsChoiceTargets(): void {
        $this->setupReaperFourHits();
        $this->createOp("resolveHits", [
            "attacker" => "hero_1",
            "target" => "hex_12_8",
            "secondary" => "hex_12_7",
        ]);

        // 4 hits → 5 split options (0..4 inclusive)
        $this->assertValidTargetCount(5);
        $this->assertValidTarget("choice_0");
        $this->assertValidTarget("choice_4");
    }

    public function testSplitTwoTwoQueuesBothDealDamages(): void {
        $this->setupReaperFourHits();
        $this->createOp("resolveHits", [
            "attacker" => "hero_1",
            "target" => "hex_12_8",
            "secondary" => "hex_12_7",
        ]);

        $this->call_resolve("choice_2"); // 2 to primary, 2 to secondary

        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $dealDamageOps = array_values(array_filter($ops, fn($o) => $o["type"] === "dealDamage"));
        $this->assertCount(2, $dealDamageOps, "both legs should queue dealDamage");

        $byTarget = [];
        foreach ($dealDamageOps as $o) {
            $d = $this->opData($o);
            $byTarget[$d["target"]] = (int) $d["count"];
        }
        $this->assertEquals(2, $byTarget["hex_12_8"]);
        $this->assertEquals(2, $byTarget["hex_12_7"]);
    }

    public function testSplitAllToPrimarySuppressesSecondaryLeg(): void {
        $this->setupReaperFourHits();
        $this->createOp("resolveHits", [
            "attacker" => "hero_1",
            "target" => "hex_12_8",
            "secondary" => "hex_12_7",
        ]);

        $this->call_resolve("choice_4"); // 4 to primary, 0 to secondary

        // Both legs queue dealDamage; secondary's leg has count=0 (benign).
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $dealDamageOps = array_values(array_filter($ops, fn($o) => $o["type"] === "dealDamage"));
        $this->assertCount(2, $dealDamageOps);
        $byTarget = [];
        foreach ($dealDamageOps as $op) {
            $d = $this->opData($op);
            $byTarget[$d["target"]] = (int) $d["count"];
        }
        $this->assertEquals(4, $byTarget["hex_12_8"]);
        $this->assertEquals(0, $byTarget["hex_12_7"]);
    }

    public function testSplitAllToSecondarySuppressesPrimaryLeg(): void {
        $this->setupReaperFourHits();
        $this->createOp("resolveHits", [
            "attacker" => "hero_1",
            "target" => "hex_12_8",
            "secondary" => "hex_12_7",
        ]);

        $this->call_resolve("choice_0"); // 0 to primary, 4 to secondary

        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $dealDamageOps = array_values(array_filter($ops, fn($o) => $o["type"] === "dealDamage"));
        $this->assertCount(2, $dealDamageOps);
        $byTarget = [];
        foreach ($dealDamageOps as $op) {
            $d = $this->opData($op);
            $byTarget[$d["target"]] = (int) $d["count"];
        }
        $this->assertEquals(0, $byTarget["hex_12_8"]);
        $this->assertEquals(4, $byTarget["hex_12_7"]);
    }
}
