<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Model\Trigger;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Trigger operation — fires automatically in response to a published game trigger.
 *
 * Params:
 * - param(0): the Trigger wire value (e.g. "TActionAttack", "TRoll", "TMonsterMove")
 *
 * Behaviour:
 * - Converts the wire string back into a Trigger case at the boundary.
 * - Walks every card in the owner's tableau + hand and dispatches onTrigger($event).
 * - Also dispatches onTriggerQuest($event) on the top card of deck_equip_{owner}.
 *   Default Card behavior reads quest_on / quest_r from material; bespoke quest
 *   cards (e.g. Shield-Boldur, Elven Arrows) override onTriggerQuest directly.
 */
class Op_trigger extends Operation {
    function resolve(): void {
        $wire = $this->getParam(0, "");
        $this->game->systemAssert("trigger required", $wire !== "");
        $event = Trigger::from($wire);
        $owner = $this->getOwner();
        $cards = array_merge(
            $this->game->tokens->getTokensOfTypeInLocation("card", "tableau_$owner"),
            $this->game->tokens->getTokensOfTypeInLocation("card", "hand_$owner")
        );

        foreach ($cards as $cardId => $card) {
            $cardObj = $this->game->instantiateCard($card, $this);
            $cardObj->onTrigger($event);
        }

        $card = $this->game->tokens->getTokenOnTop("deck_equip_$owner");
        if ($card) {
            $cardObj = $this->game->instantiateCard($card, $this);
            $cardObj->onTriggerQuest($event);
        }
    }
}
