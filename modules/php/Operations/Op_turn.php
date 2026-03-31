<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;
use Exception;

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
        $remaining = $this->getActionsRemaining();
        return $remaining === 0; //only can skip if no mandatory actions
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

    private function getTurnOperations(): array {
        $result = [];
        $token_types = $this->game->material->get();
        foreach ($token_types as $key => $data) {
            $kind = $data["kind"] ?? "";
            if (str_starts_with($key, "Op_")) {
                if ($kind == "main" || $kind == "free") {
                    $optype = str_replace("Op_", "", $key);
                    $result[$optype] = $data + [
                        "name" => $this->game->getTokenName($key),
                        "key" => $key,
                    ];
                }
            }
        }
        return $result;
    }

    public function requireConfirmation() {
        return true;
    }
    public function getPossibleMoves() {
        $res = [];
        $hero = $this->game->getHero($this->getOwner());
        $actionsTaken = $hero->getActionsTaken();
        $remaining = $hero->getActionsRemaining();
        $allops = $this->getTurnOperations();

        // Offer main actions if the player still has actions remaining

        foreach ($allops as $action => $actionInfo) {
            $res[$action] = ["q" => 0, "name" => $actionInfo["name"]];
            $inline = $actionInfo["inline"] ?? 0;
            $kind = $actionInfo["kind"] ?? "main";
            if (in_array($action, $actionsTaken)) {
                $res[$action]["q"] = Material::ERR_NOT_APPLICABLE;
            } else {
                $op = $this->instanciateOperation($action);
                $res[$action] = array_merge($res[$action], $op->getErrorInfo());
                $res[$action]["replicate"] = true;
                if ($res[$action]["q"] == 0 && $inline && ($kind == "free" || $remaining > 0)) {
                    // action is available, shortcut - send action parameters also
                    $info = $op->getArgs()["info"];
                    foreach ($info as $key => $value) {
                        $res[$key] = $value;
                        $res[$key]["action"] = $action;
                    }
                }
            }
            if ($kind == "free") {
                // free action only offered via its inlined possible moves
                unset($res[$action]);
            } elseif ($remaining == 0) {
                unset($res[$action]);
            }
        }

        return $res;
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }

    function resolve(): void {
        $optype = $this->getCheckedArg();
        $hero = $this->game->getHero($this->getOwner());
        $kind = $this->getActionKind($optype);

        if ($kind === "main") {
            $hero->placeActionMarker($optype);
            $this->queue($optype);
            $this->queue("turn");
        } elseif ($kind === "free") {
            $this->queue($optype);
            $this->queue("turn");
        } elseif ($kind === "") {
            // delegate: user picked a sub-target directly (e.g. a hex), resolve via parent action
            $argInfo = $this->getArgs()["info"][$optype];
            $action = $argInfo["action"] ?? "";
            $this->game->systemAssert("ERR:turn:invalidDelegateAction", $action !== "");
            $kind = $this->getActionKind($action);

            if ($kind == "main") {
                $hero->placeActionMarker($action);
            }
            $this->queue($action, null, ["target" => $optype]);
            $this->queue("turn");
        } else {
            $this->game->systemAssert("ERR:turn:invalidDelegateAction3");
        }
    }

    private function queueEndOfTurn(): void {
        $this->queue("turnEnd");
        $this->game->queueNextTurnOrEnd($this->getPlayerId());
    }

    public function getActionsTaken(): array {
        return $this->game->getHero($this->getOwner())->getActionsTaken();
    }

    public function getActionsRemaining(): int {
        return $this->game->getHero($this->getOwner())->getActionsRemaining();
    }

    public function getPrompt() {
        $remaining = $this->getActionsRemaining();
        if ($remaining == self::ACTIONS_PER_TURN) {
            return clienttranslate("Select your first action or free action");
        } elseif ($remaining == 1) {
            return clienttranslate("Select your second action or free action");
        } elseif ($this->noValidTargets()) {
            return clienttranslate("Confirm end of turn");
        }
        return clienttranslate("Select a free action or end your turn");
    }

    public function getSubTitle() {
        if ($this->noValidTargets()) {
            return clienttranslate("No valid actions remain");
        }
        return "";
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
