<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

/**
 * BGA #234817 "Move next to enemy costs all movement points" - reporter clarified:
 * in step-by-step move mode (Op_moveStep, driven by a TStep quest / onStep card) the
 * move "automatically ENDS" with budget remaining as soon as (1) the hero steps next
 * to a monster, or (2) a step completes a quest.
 *
 * Unlike the prior guard HeroMoveAdjacentMonsterBug234817Test (which only probes the
 * isolated helper HexMap::getReachableHexes in an open plains field), this test drives
 * the ACTUAL Op_moveStep loop end-to-end and inspects the re-queued follow-up prompt.
 *
 * The loop ends prematurely only if the follow-up moveStep collapses to a single choice
 * ("End Move"), which happens only when getPossibleMoves offers no reachable hex.
 *
 * RESULT: neither sub-claim reproduces server-side. Both tests are GREEN and assert the
 * CORRECT (non-buggy) behavior: the loop keeps offering destinations. They are regression
 * guards, not a captured bug. If the reported symptom is real it lives in the TypeScript
 * client (the server never auto-ends here while free hexes remain). Should a future change
 * ever make the server end early with budget + free hexes left, these assertions flip red.
 */
final class Op_moveStepPrematureEndBug234817Test extends TestCase {
    private GameUT $game;
    private string $owner;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(2); // Alva (hero_2) - owns Belt of Youth forest quest
        $this->game->clearHand();
        $this->game->clearMachine();
        $this->game->clearEquipDecks();
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());

        // Isolate the map: clear all characters, tests place what they need.
        foreach (["hero_1", "hero_2", "hero_3", "hero_4"] as $hId) {
            $this->game->tokens->moveToken($hId, "limbo");
        }
        foreach ($this->game->tokens->getTokensOfTypeInLocation(null, "hex%") as $token) {
            if (str_starts_with($token["key"], "monster")) {
                $this->game->tokens->moveToken($token["key"], "limbo");
            }
        }
        $this->game->hexMap->invalidateOccupancy();
    }

    private function heroId(): string {
        return $this->game->getHeroTokenId($this->owner);
    }

    private function createMoveStep(array $data): Operation {
        return $this->game->machine->instantiateOperation("moveStep", $this->owner, $data);
    }

    /** The follow-up moveStep the loop re-queued, or null if the loop ended. */
    private function findQueuedMoveStep(): ?Operation {
        foreach ($this->game->machine->getTopOperations($this->owner) as $row) {
            if (($row["type"] ?? "") === "moveStep") {
                return $this->game->machine->instantiateOperationFromDbRow($row);
            }
        }
        return null;
    }

    private function allQueuedTypes(): array {
        return array_map(fn($row) => $row["type"] ?? "", $this->game->machine->getAllOperations($this->owner));
    }

    // -------------------------------------------------------------------------
    // Sub-claim 1: stepping next to a monster (with free hexes still in budget).
    // Open plains x=11..15,y=7..9. Hero starts hex_12_8, monster parked at
    // hex_14_8. Stepping east to hex_13_8 lands the hero directly adjacent to the
    // monster while budget (2) and free hexes remain all around.
    // -------------------------------------------------------------------------
    public function testStepNextToMonsterDoesNotEndMovePrematurely(): void {
        $this->game->tokens->moveToken($this->heroId(), "hex_12_8");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_14_8");
        $this->game->hexMap->invalidateOccupancy();

        $op = $this->createMoveStep(["budget" => 3, "moved" => 0, "reason" => "Op_actionMove"]);
        $op->action_resolve([Operation::ARG_TARGET => "hex_13_8"]); // step adjacent to the monster
        $this->game->machine->dispatchAll();

        $this->assertEquals("hex_13_8", $this->game->tokens->getTokenLocation($this->heroId()), "hero took the single step next to the monster");

        // What does getReachableHexes report from hex_13_8 with the 2 remaining budget,
        // while the monster sits on hex_14_8 (east)?
        $hero = $this->game->getHero($this->owner);
        $reachable = $this->game->hexMap->getReachableHexes("hex_13_8", 2, $hero);
        $this->assertNotEmpty($reachable, "reachable hexes remain around the adjacent monster");
        $this->assertArrayNotHasKey("hex_14_8", $reachable, "cannot stop on the monster's hex");

        // CURRENT behavior: the loop keeps going - the re-queued moveStep still offers
        // free destinations plus End Move, so it is NOT auto-resolved to End Move.
        $follow = $this->findQueuedMoveStep();
        $this->assertNotNull($follow, "the loop re-queues a follow-up moveStep (move did NOT end)");
        $targets = $follow->getArgsTarget();
        $this->assertGreaterThan(1, count($targets), "more than just End Move is offered -> no premature auto-end");
        $this->assertContains("endOfMove", $targets, "End Move is offered");
        $freeTargets = array_values(array_filter($targets, fn($t) => $t !== "endOfMove"));
        $this->assertNotEmpty($freeTargets, "at least one free destination hex is still offered next to the monster");
    }

    // -------------------------------------------------------------------------
    // Sub-claim 2: a step completes a quest (Belt of Youth: enter 8 forest areas).
    // Deck-top = card_equip_2_22 seeded with 7 trackers; hero on forest hex_8_3;
    // stepping into the adjacent forest hex_9_3 is the 8th forest -> gainEquip.
    // -------------------------------------------------------------------------
    public function testStepCompletingQuestDoesNotEndMovePrematurely(): void {
        $belt = "card_equip_2_22"; // Belt of Youth - in(forest):gainTracker,check('countTracker>=8'):gainEquip
        $this->game->tokens->moveToken($belt, "deck_equip_" . $this->owner, 9999); // force to deck top
        // Seed 7 trackers so the next forest entry (the 8th) completes the quest.
        $this->game->effect_moveCrystals($this->heroId(), "red", 7, $belt);
        $this->assertEquals(7, $this->game->evaluateExpression("countTracker", $this->owner), "quest primed one step short");

        $this->game->tokens->moveToken($this->heroId(), "hex_8_3"); // forest
        $this->game->hexMap->invalidateOccupancy();

        $op = $this->createMoveStep(["budget" => 3, "moved" => 0, "reason" => "Op_actionMove"]);
        $op->action_resolve([Operation::ARG_TARGET => "hex_9_3"]); // adjacent forest -> 8th forest -> quest completes
        $this->game->machine->dispatchAll();

        $this->assertEquals("hex_9_3", $this->game->tokens->getTokenLocation($this->heroId()), "hero took the completing step");
        // Quest completed: Belt of Youth landed on the tableau via gainEquip.
        $this->assertEquals("tableau_" . $this->owner, $this->game->tokens->getTokenLocation($belt), "quest completed mid-step (Belt gained)");

        // CURRENT behavior: completing the quest does NOT end the move - the loop's
        // re-queued moveStep survives and still offers destinations (budget 2 left).
        $follow = $this->findQueuedMoveStep();
        $this->assertNotNull($follow, "the loop continues after quest completion (move did NOT end): " . implode(",", $this->allQueuedTypes()));
        $targets = $follow->getArgsTarget();
        $this->assertGreaterThan(1, count($targets), "destinations remain after quest completion -> no premature auto-end");
        $this->assertContains("endOfMove", $targets, "End Move is offered");
    }
}
