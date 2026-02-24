<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Game;
use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

use function Bga\Games\Fate\getPart;

/**
 * Main player turn operation.
 *
 * Turn structure:
 * - Player must select 2 different actions from: move, attack, prepare, focus, mend, practice
 * - Cannot pick the same action twice
 * - Free actions (use equipment, use ability, play event, share gold) can be done between/after actions
 * - After 2 actions are taken, proceeds to end-of-turn sequence
 *
 * Data fields:
 * - "actions_taken": array of action types already taken this turn (e.g. ["actionMove", "actionAttack"])
 * - "actions_remaining": int, number of main actions still available (starts at 2)
 */
class Op_turn extends Operation {
    const ACTIONS_PER_TURN = 2;

    public function auto(): bool {
        $this->game->switchActivePlayer($this->getPlayerId(), true);
        $this->game->customUndoSavepoint($this->getPlayerId(), 1);
        return parent::auto();
    }

    function canSkip() {
        // Player can end turn early (skip remaining actions)
        $remaining = $this->getActionsRemaining();
        return $remaining > 0 && $remaining < self::ACTIONS_PER_TURN;
    }

    function getSkipName() {
        return clienttranslate("End Turn");
    }

    function skip() {
        parent::skip();
        $this->queueEndOfTurn();
    }

    private function getActionKind(string $action): string {
        return $this->game->getRulesFor("Op_$action", "kind", "");
    }

    private function getActionsByKind(string $kind): array {
        $result = [];
        $token_types = $this->game->material->get();
        foreach ($token_types as $key => $data) {
            if (($data["kind"] ?? "") === $kind && str_starts_with($key, "Op_")) {
                $optype = str_replace("Op_", "", $key);
                $result[] = $optype;
            }
        }
        return $result;
    }

    public function getPossibleMoves() {
        $res = [];
        $actionsTaken = $this->getActionsTaken();
        $remaining = $this->getActionsRemaining();

        // Offer main actions if the player still has actions remaining
        if ($remaining > 0) {
            foreach ($this->getActionsByKind("main") as $action) {
                if (in_array($action, $actionsTaken)) {
                    // Cannot pick the same action twice
                    $res[$action] = ["q" => Material::ERR_NOT_APPLICABLE, "name" => $this->game->getTokenName("Op_$action")];
                } else {
                    $res[$action] = ["q" => 0, "name" => $this->game->getTokenName("Op_$action")];
                }
            }
        }

        // Always offer free actions (they don't consume main action slots)
        // TODO: check actual availability of each free action (has equipment, has ability, has event cards, etc.)
        foreach ($this->getActionsByKind("free") as $action) {
            $res[$action] = ["q" => 0, "sec" => true, "name" => $this->game->getTokenName("Op_$action")];
        }

        return $res;
    }

    function resolve(): void {
        $optype = $this->getCheckedArg();
        $actionsTaken = $this->getActionsTaken();
        $remaining = $this->getActionsRemaining();
        $kind = $this->getActionKind($optype);

        if ($kind === "main") {
            // Enforce: cannot pick same action twice
            $this->game->userAssert(clienttranslate("You already performed this action this turn"), !in_array($optype, $actionsTaken));
            $this->game->userAssert(clienttranslate("No actions remaining"), $remaining > 0);

            // Set the marker
            $owner = $this->getOwner();
            $x = 3 - $remaining;
            $this->dbSetTokenLocation("marker_{$owner}_{$x}", "aslot_{$owner}_{$optype}", 0);

            // Queue the selected action operation
            $this->queue($optype);

            // Update tracking: record action taken and decrement remaining
            $actionsTaken[] = $optype;
            $remaining--;

            // Re-queue this turn operation with updated state for the next action pick, even there is no remaining to pick free actions
            $this->withDataField("actions_taken", $actionsTaken);
            $this->withDataField("actions_remaining", $remaining);
            $this->saveToDb(2, true);
        } elseif ($kind === "free") {
            // Queue the free action, then come back to this turn operation
            $this->queue($optype);
            // Re-queue turn (same state, free actions don't consume main actions)
            $this->saveToDb(2, true);
        } else {
            $this->game->userAssert(clienttranslate("Invalid action selection"));
        }
    }

    private function queueEndOfTurn(): void {
        $this->queue("endOfTurn");
        $this->queue("turnconf");
        $this->game->queueNextTurnOrEnd($this->getPlayerId());
    }

    private function getActionsTaken(): array {
        return $this->getDataField("actions_taken", []);
    }

    private function getActionsRemaining(): int {
        return (int) $this->getDataField("actions_remaining", self::ACTIONS_PER_TURN);
    }

    public function getPrompt() {
        $remaining = $this->getActionsRemaining();
        if ($remaining == self::ACTIONS_PER_TURN) {
            return clienttranslate("Select your first action");
        } elseif ($remaining == 1) {
            return clienttranslate("Select your second action");
        }
        return clienttranslate("Select a free action or end your turn");
    }

    public function getDescription() {
        return clienttranslate('${actplayer} must select an action');
    }

    public function getExtraArgs() {
        return [
            "actions_taken" => $this->getActionsTaken(),
            "actions_remaining" => $this->getActionsRemaining(),
        ];
    }
}
