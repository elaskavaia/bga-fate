<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * 2-player turn handoff: player1 → player2 → monsterTurn → player1.
 *
 * Suspected bug: after end of player2's turn, the monster turn runs but
 * does not schedule the next player's turn — game hangs bouncing between
 * GameDispatch and PlayerTurn with no op for the active player.
 */
class Campaign_TwoPlayerTurnTest extends CampaignBaseTest {
    private string $color1; // orange / Embla
    private string $color2; // red / Boldur

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([3, 4]); // Embla (orange) first, Boldur (red) second — Victoria's prod setup

        $players = array_values($this->game->loadPlayersBasicInfos());
        $this->color1 = $players[0]["player_color"];
        $this->color2 = $players[1]["player_color"];

        // Keep the monster turn deterministic and harmless.
        $this->seedDeck("deck_monster_yellow", [
            "card_monster_7",
            "card_monster_8",
            "card_monster_9",
            "card_monster_10",
        ]);
        $this->seedDeck("deck_event_" . $this->color1, ["card_event_1_27_1"]);
        $this->seedDeck("deck_event_" . $this->color2, ["card_event_4_35_1"]);
        $this->clearHand($this->color1);
        $this->clearHand($this->color2);
        // Monsters from the initial reinforcement remain on the map — that's the
        // production state when end-of-turn rolls into the monster turn.
    }

    /**
     * Suspected production hang: an interactive op exists on the machine
     * owned by the NON-active player. Active player's PlayerTurn finds no
     * op for their color, returns to GameDispatch; GameDispatch picks up
     * the foreign-owned op, returns PlayerTurn; loop.
     *
     * Repro the *shape*: directly push a useCard op for the non-active
     * player (mirrors what a TResolveHits trigger would queue during the
     * monster turn).
     */
    public function testInteractiveOpForNonActivePlayerDoesNotHang(): void {
        // After setup, Embla is active. Push a useCard op for Boldur (non-active),
        // putting it ahead of Embla's `turn` op (which stays on the stack at higher rank).
        $this->assertEquals($this->color1, $this->getActiveOwner(), "Embla should be active at start");

        $this->game->machine->push("useCard", $this->color2, ["card" => "card_ability_4_11", "l_confirm" => true]);

        // Enter GameDispatch so the fix point (OpMachine::dispatchOne) actually runs.
        $this->game->gamestate->jumpToState(\Bga\Games\Fate\StateConstants::STATE_GAME_DISPATCH);
        $this->driver->runDispatchLoop();

        // After dispatch: either active player switched to Boldur, OR the foreign-owned
        // op was deferred / dropped. Either way, active player must match top op's owner,
        // otherwise prod would infinite-loop here.
        $owner = $this->getActiveOwner();
        $allOps = $this->game->machine->db->getOperations();
        $topOpOwner = $allOps ? reset($allOps)["owner"] : $owner;
        $this->assertSame(
            $owner,
            $topOpOwner,
            "active player ($owner) must match top op's owner ($topOpOwner) — " .
                "mismatch causes infinite GameDispatch ⇄ PlayerTurn loop"
        );
    }

    public function testEndOfPlayer2TurnHandsControlBackToPlayer1(): void {
        // ── Player 1's turn ─────────────────────────────────────────────────
        $this->assertEquals(
            $this->color1,
            $this->getActiveOwner(),
            "player1 should be active at start"
        );
        $this->burnTwoActions();
        $this->skipTurnEndHousekeeping();

        // ── Player 2's turn ─────────────────────────────────────────────────
        $this->assertEquals(
            $this->color2,
            $this->getActiveOwner(),
            "player2 should be active after player1's turn"
        );
        $this->burnTwoActions();
        $this->skipTurnEndHousekeeping();

        // ── After monster turn, control must return to player 1 ─────────────
        $this->assertEquals(
            $this->color1,
            $this->getActiveOwner(),
            "player1 should be active again after monster turn (round 2)"
        );

        // And the next op on the stack should be a normal `turn` op (or its prefix).
        $this->syncCurrentPlayerToActive();
        $args = $this->getOpArgs();
        $this->assertContains(
            $args["type"] ?? "",
            ["turn", "turnStart"],
            "round 2 should start with turn/turnStart op, got: " . ($args["type"] ?? "<none>")
        );
    }

    /** Color of the player whose op is currently on top of the machine. */
    private function getActiveOwner(): string {
        return $this->game->custom_getPlayerColorById((int) $this->game->getActivePlayerId());
    }

    /** Spend both action slots on free no-cost actions to push to turn end. */
    private function burnTwoActions(): void {
        $this->syncCurrentPlayerToActive();
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skipOp("turn"); // skip the "use 3rd free action / end turn" prompt
    }

    /** Harness has a sticky `currentPlayerId`; sync it to active so action_resolve targets the right player. */
    private function syncCurrentPlayerToActive(): void {
        $this->game->_setCurrentPlayerId((int) $this->game->getActivePlayerId());
    }

    /** Skip optional end-of-turn ops (upgrade, drawEvent, demote). */
    private function skipTurnEndHousekeeping(): void {
        $this->skipIfOp("upgrade");
        $this->skipIfOp("drawEvent");
        $this->skipIfOp("demote");
    }
}
