<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Model\Trigger;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Player-initiated quest completion. Surfaces the deck-top equipment card if it
 * has a non-empty `quest_r` and the leading gates pass; on selection, queues
 * `quest_r` so its costs (spendAction, spendXp, discardEvent) prompt normally.
 *
 * Sibling of Op_useCard but scoped to the single deck-top equipment card.
 */
class Op_completeQuest extends Operation {
    function getPrompt() {
        return clienttranslate("You may complete a quest");
    }

    public function getSkipName() {
        return clienttranslate("Not now");
    }

    public function canSkip() {
        return true;
    }

    private function getDeckTopCardId(): ?string {
        $owner = $this->getOwner();
        $top = $this->game->tokens->getTokenOnTop("deck_equip_$owner");
        return $top["key"] ?? null;
    }

    function getPossibleMoves() {
        $cardId = $this->getDeckTopCardId();
        if (!$cardId) {
            return [];
        }
        $card = $this->game->tokens->getTokenInfo($cardId);
        $cardObj = $this->game->instantiateCard($card, $this);
        $info = ["q" => 0];
        $cardObj->canResolveQuest(Trigger::Manual, $info);
        return [$cardId => $info];
    }

    function resolve(): void {
        $cardId = $this->getCheckedArg();
        $card = $this->game->tokens->getTokenInfo($cardId);
        $cardObj = $this->game->instantiateCard($card, $this);
        $cardObj->triggerQuest(Trigger::Manual);
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
