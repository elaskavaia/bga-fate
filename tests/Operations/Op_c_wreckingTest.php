<?php

declare(strict_types=1);

/**
 * Op_c_wrecking — Wrecking Ball pendulum loop.
 *
 * Two phases per iteration:
 *   - destination phase (no `displaced` data field): list adjacent non-impassable
 *     hexes plus an "endOfMove" sentinel. Picking a hex queues an Op_step. If the
 *     hex was occupied, queues a follow-up c_wrecking with `displaced` set.
 *   - push phase (`displaced` set): list adjacent hexes the displaced character
 *     can enter. Picking one moves the character + queues dealDamage(1).
 *
 * Hero (default Bjorn) parked at hex_5_9 — clean non-Grimheim ring:
 *   NW hex_4_9, NE hex_5_8, E hex_6_8, SE hex_6_9, SW hex_5_10, W hex_4_10.
 */
final class Op_c_wreckingTest extends AbstractOpTestCase {
    private string $heroHex = "hex_5_9";

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", $this->heroHex);
    }

    public function testDestinationListsEndOfMoveSentinel(): void {
        $this->createOp("c_wrecking", ["budget" => 3]);
        $this->assertValidTarget("endOfMove");
    }

    public function testDestinationListsAdjacentHexesWithBudget(): void {
        $this->createOp("c_wrecking", ["budget" => 3]);
        $this->assertValidTarget("hex_4_9");
        $this->assertValidTarget("hex_5_8");
        $this->assertValidTarget("hex_6_8");
        $this->assertValidTarget("hex_6_9");
        $this->assertValidTarget("hex_5_10");
        $this->assertValidTarget("hex_4_10");
    }

    public function testDestinationOnlyEndOfMoveWhenBudgetZero(): void {
        $this->createOp("c_wrecking", ["budget" => 0]);
        $this->assertValidTargetCount(1);
        $this->assertValidTarget("endOfMove");
    }

    public function testEndOfMoveQueuesActionMoveTrigger(): void {
        // Mirror the production wiring: Op_move forwards reason=Op_actionMove,
        // which is what triggers TActionMove (vs. plain TMove for free-move sources).
        $this->createOp("c_wrecking", ["budget" => 3, "reason" => "Op_actionMove"]);
        $this->call_resolve("endOfMove");

        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $foundActionMoveTrigger = false;
        foreach ($opTypes as $t) {
            if (str_contains($t, "trigger") && str_contains($t, "TActionMove")) {
                $foundActionMoveTrigger = true;
                break;
            }
        }
        $this->assertTrue($foundActionMoveTrigger, "endOfMove should queue TActionMove trigger");
    }

    public function testStepIntoEmptyHexQueuesStepAndContinues(): void {
        $this->createOp("c_wrecking", ["budget" => 3]);
        $this->call_resolve("hex_4_9");

        // An Op_step was queued for the destination.
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("step", $opTypes, "Op_step should be queued for the chosen hex");
        $this->assertContains("c_wrecking", $opTypes, "Loop should continue with another c_wrecking");
    }

    public function testStepIntoOccupiedHexQueuesPushPhase(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_4_9");
        $this->createOp("c_wrecking", ["budget" => 3]);
        $this->call_resolve("hex_4_9");

        // Push-phase c_wrecking should be queued with displaced=monster_goblin_1.
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $foundPush = false;
        foreach ($ops as $op) {
            if ($op["type"] === "c_wrecking") {
                $data = json_decode($op["data"] ?? "{}", true) ?? [];
                if (($data["displaced"] ?? "") === "monster_goblin_1") {
                    $foundPush = true;
                    break;
                }
            }
        }
        $this->assertTrue($foundPush, "Push-phase c_wrecking should be queued with displaced=monster_goblin_1");
    }

    public function testPushPhaseListsAdjacentValidHexes(): void {
        // Boldur and goblin both on hex_5_9 (transient overlap state). Push phase
        // looks at hexes adjacent to Boldur for the goblin's destination.
        $this->game->tokens->moveToken("monster_goblin_1", $this->heroHex);
        $this->createOp("c_wrecking", ["budget" => 2, "displaced" => "monster_goblin_1"]);
        // All 6 ring hexes are valid plains, monster can enter them.
        $this->assertValidTarget("hex_4_9");
        $this->assertValidTarget("hex_5_8");
        $this->assertValidTargetCount(6);
    }

    public function testPushPhaseExcludesMountainForHeroDisplacement(): void {
        // hex_13_3 ring includes hex_13_4 (unnamed mountain) — heroes can't enter unnamed mountains.
        // (Named mountains like Troll Caves ARE passable to heroes per RULES.md:55.)
        $this->game->tokens->moveToken("hero_1", "hex_13_3");
        $this->game->tokens->moveToken("hero_2", "hex_13_3");
        $this->createOp("c_wrecking", ["budget" => 2, "displaced" => "hero_2"]);
        $this->assertNotValidTarget("hex_13_4", "hero cannot be pushed onto an unnamed mountain");
        $this->assertValidTargetCount(5);
    }

    public function testPushPhaseAllowsMountainForMonsterDisplacement(): void {
        // Same ring as above but with a monster — monsters can enter mountains.
        $this->game->tokens->moveToken("hero_1", "hex_13_3");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_3");
        $this->createOp("c_wrecking", ["budget" => 2, "displaced" => "monster_goblin_1"]);
        $this->assertValidTarget("hex_13_4", "monster can be pushed onto a mountain");
        $this->assertValidTargetCount(6);
    }

    public function testPushResolveMovesDisplacedCharacter(): void {
        $this->game->tokens->moveToken("monster_goblin_1", $this->heroHex);
        $this->createOp("c_wrecking", ["budget" => 2, "displaced" => "monster_goblin_1"]);

        $this->call_resolve("hex_4_9");
        $this->assertEquals("hex_4_9", $this->game->tokens->getTokenLocation("monster_goblin_1"));
    }

    public function testPushResolveQueuesDealDamage(): void {
        $this->game->tokens->moveToken("monster_goblin_1", $this->heroHex);
        $this->createOp("c_wrecking", ["budget" => 2, "displaced" => "monster_goblin_1"]);

        $this->call_resolve("hex_4_9");

        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("dealDamage", $opTypes, "dealDamage should be queued after push");
    }

    public function testPushResolveLoopsBackToDestination(): void {
        $this->game->tokens->moveToken("monster_goblin_1", $this->heroHex);
        $this->createOp("c_wrecking", ["budget" => 2, "displaced" => "monster_goblin_1"]);

        $this->call_resolve("hex_4_9");

        // Next c_wrecking should be in destination phase (no displaced field) with budget=2.
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $foundDest = false;
        foreach ($ops as $op) {
            if ($op["type"] !== "c_wrecking") continue;
            $data = json_decode($op["data"] ?? "{}", true) ?? [];
            if (!isset($data["displaced"]) || $data["displaced"] === "") {
                $this->assertEquals(2, $data["budget"] ?? 0, "Loop should preserve budget after push");
                $foundDest = true;
            }
        }
        $this->assertTrue($foundDest, "After push, c_wrecking should re-queue in destination phase");
    }

    public function testHeroDisplacedRecognizedAsHeroType(): void {
        // 2-player setup: hero_2 (Alva) on Boldur's hex; push phase targets must be hero-enterable.
        // Use solo-hero stub: place hero_2 token directly (still works for type check via getPart).
        $this->game->tokens->moveToken("hero_2", $this->heroHex);
        $this->createOp("c_wrecking", ["budget" => 2, "displaced" => "hero_2"]);
        // Hero target hexes exclude mountains/lakes — at hex_5_9 ring, all plains, so all valid.
        $this->assertValidTargetCount(6);
    }
}
