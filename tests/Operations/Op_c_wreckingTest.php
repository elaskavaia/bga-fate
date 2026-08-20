<?php

declare(strict_types=1);

/**
 * Op_c_wrecking — Wrecking Ball push phase.
 *
 * Boldur has already stepped into the occupied hex (Op_move Wrecking Ball branch);
 * this op lists adjacent hexes the displaced character can enter. Picking one
 * moves the character + queues dealDamage(1). The move loop itself continues
 * in Op_move (queued by the Wrecking Ball branch, not by this op).
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

    public function testPushPhaseListsAdjacentValidHexes(): void {
        // Boldur and goblin both on hex_5_9 (transient overlap state). Push phase
        // looks at hexes adjacent to Boldur for the goblin's destination.
        $this->game->tokens->moveToken("monster_goblin_1", $this->heroHex);
        $this->createOp("c_wrecking", ["displaced" => "monster_goblin_1"]);
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
        $this->createOp("c_wrecking", ["displaced" => "hero_2"]);
        $this->assertNotValidTarget("hex_13_4", "hero cannot be pushed onto an unnamed mountain");
        $this->assertValidTargetCount(5);
    }

    public function testPushPhaseAllowsMountainForMonsterDisplacement(): void {
        // Same ring as above but with a monster — monsters can enter mountains.
        $this->game->tokens->moveToken("hero_1", "hex_13_3");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_3");
        $this->createOp("c_wrecking", ["displaced" => "monster_goblin_1"]);
        $this->assertValidTarget("hex_13_4", "monster can be pushed onto a mountain");
        $this->assertValidTargetCount(6);
    }

    public function testPushResolveMovesDisplacedCharacter(): void {
        $this->game->tokens->moveToken("monster_goblin_1", $this->heroHex);
        $this->createOp("c_wrecking", ["displaced" => "monster_goblin_1"]);

        $this->call_resolve("hex_4_9");
        $this->assertEquals("hex_4_9", $this->game->tokens->getTokenLocation("monster_goblin_1"));
    }

    public function testPushResolveQueuesDealDamage(): void {
        $this->game->tokens->moveToken("monster_goblin_1", $this->heroHex);
        $this->createOp("c_wrecking", ["displaced" => "monster_goblin_1"]);

        $this->call_resolve("hex_4_9");

        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertContains("dealDamage", $opTypes, "dealDamage should be queued after push");
    }

    public function testPushResolveDoesNotReQueueItself(): void {
        // The loop lives in Op_move now; the push phase must not spawn another c_wrecking.
        $this->game->tokens->moveToken("monster_goblin_1", $this->heroHex);
        $this->createOp("c_wrecking", ["displaced" => "monster_goblin_1"]);

        $this->call_resolve("hex_4_9");

        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        $this->assertNotContains("c_wrecking", $opTypes, "push phase is one-shot");
    }

    public function testHeroDisplacedRecognizedAsHeroType(): void {
        // 2-player setup: hero_2 (Alva) on Boldur's hex; push phase targets must be hero-enterable.
        // Use solo-hero stub: place hero_2 token directly (still works for type check via getPart).
        $this->game->tokens->moveToken("hero_2", $this->heroHex);
        $this->createOp("c_wrecking", ["displaced" => "hero_2"]);
        // Hero target hexes exclude mountains/lakes — at hex_5_9 ring, all plains, so all valid.
        $this->assertValidTargetCount(6);
    }
}
