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

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 * Discard an event card from hand to the discard pile.
 *
 * Rules (Prepare action):
 * "You may never have more than 4 cards in your hand, but you may discard
 *  cards without effect at any time to make room for new cards."
 *
 * Rules (Play Event):
 * "Play an event card from your hand. Perform its effect, then place it
 *  in your discard pile."
 *
 * Multiplicity (`NdiscardEvent`): the player picks one card per prompt; on
 * resolve we re-queue `(N-1)discardEvent` until count is exhausted. Used by
 * paid quests like Bloodline Crystal (`2discardEvent`) and Heels.
 */
class Op_discardEvent extends CountableOperation {
    function getPrompt() {
        return clienttranslate("Choose an Event card to discard from hand");
    }

    function getPossibleMoves() {
        $hero = $this->game->getHero($this->getOwner());
        $cards = $hero->getHandCards();
        $targets = [];
        foreach ($cards as $card) {
            $targets[$card["key"]] = ["q" => Material::RET_OK];
        }
        return $targets;
    }

    function resolve(): void {
        $target = $this->getCheckedArg();
        $hero = $this->game->getHero($this->getOwner());
        $hero->discardEventCard($target);
        $remaining = $this->getCount() - 1;
        if ($remaining > 0) {
            $this->queue("{$remaining}discardEvent");
        }
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
