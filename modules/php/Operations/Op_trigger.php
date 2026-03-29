<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\Operation;

/**
 * Trigger operation — fires automatically in response to a game event.
 *
 * Params:
 * - param(0): the trigger type (e.g. "actionAttack", "monsterAttack", "roll", "monsterMove", "turnEnd")
 *
 * Behaviour:
 * - Delegates to useEquipment, useAbility, playEvent with "on" data field
 * - Each sub-operation filters its own cards by the "on" material field
 * - Collects their possible moves, annotated with "action", and presents as own targets
 */
class Op_trigger extends Operation {
    private function getTriggerType(): string {
        return $this->getParam(0, "");
    }

    function getPrompt() {
        return clienttranslate("Choose a card to play in response");
    }

    function canSkip() {
        return true;
    }

    function getPossibleMoves() {
        $triggerType = $this->getTriggerType();
        if ($triggerType === "") {
            return [];
        }
        $owner = $this->getOwner();
        $targets = [];
        foreach (["useEquipment", "useAbility", "playEvent"] as $action) {
            $op = $this->game->machine->instanciateOperation($action, $owner, ["on" => $triggerType]);
            $delegateMoves = $op->getArgs()["info"] ?? [];
            foreach ($delegateMoves as $cardId => $info) {
                $info["action"] = $action;
                $targets[$cardId] = $info;
            }
        }
        return $targets;
    }

    function resolve(): void {
        $cardId = $this->getCheckedArg();
        $owner = $this->getOwner();
        $argInfo = $this->getArgs()["info"][$cardId];
        $action = $argInfo["action"] ?? "";
        $this->game->systemAssert("ERR:trigger:noAction:$cardId", $action !== "");
        $this->queue($action, $owner, ["target" => $cardId]);
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
