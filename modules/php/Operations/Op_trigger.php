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
 * - Instantiates card that can have triggers
 */
class Op_trigger extends Operation {
    private function getTriggerType(): string {
        return $this->getParam(0, "move");
    }

    function resolve(): void {
        $triggerType = $this->getTriggerType();
        $this->game->systemAssert("trigger required", $triggerType);
        $owner = $this->getOwner();
        $cards =
            $this->game->tokens->getTokensOfTypeInLocation("card", "tableau_$owner") +
            $this->game->tokens->getTokensOfTypeInLocation("card", "hand_$owner");
        foreach (array_keys($cards) as $cardId) {
            $cardObj = $this->game->instantiateCard($cardId, $this);
            $cardObj->onTrigger($triggerType);
        }
    }
}
