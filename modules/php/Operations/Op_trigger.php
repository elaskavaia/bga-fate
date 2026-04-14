<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Model\Event;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Trigger operation — fires automatically in response to a published game event.
 *
 * Params:
 * - param(0): the Event wire value (e.g. "EventActionAttack", "EventRoll", "EventMonsterMove")
 *
 * Behaviour:
 * - Converts the wire string back into an Event case at the boundary, then
 *   walks every card in the owner's tableau + hand and dispatches onTrigger($event).
 */
class Op_trigger extends Operation {
    function resolve(): void {
        $wire = $this->getParam(0, "");
        $this->game->systemAssert("trigger required", $wire !== "");
        $event = Event::from($wire);
        $owner = $this->getOwner();
        $cards = array_merge(
            $this->game->tokens->getTokensOfTypeInLocation("card", "tableau_$owner"),
            $this->game->tokens->getTokensOfTypeInLocation("card", "hand_$owner")
        );
        foreach ($cards as $cardId => $card) {
            $cardObj = $this->game->instantiateCard($card, $this);
            $cardObj->onTrigger($event);
        }
    }
}
