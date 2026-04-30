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
 * drawEvent: Draw event card(s) from deck to hand.
 *
 * Params:
 * - param(0) "max": draw until hand is full (handLimit - handSize draws); skips discard prompt
 *
 * Behaviour:
 * - Deck empty: returns error, auto-skips
 * - Hand under limit: offers confirm target, draws getCount() cards on resolve
 * - Hand at limit (non-max): delegates to discardEvent for card selection, then draws and re-queues remaining
 * - Max mode: draws (handLimit - handSize) cards in one resolve, no discard prompt
 *
 * Used by: actionPrepare (drawEvent), Starsong (2drawEvent), Preparations (drawEvent(max)).
 */
class Op_drawEvent extends CountableOperation {
    private function isMax(): bool {
        return $this->getParam(0) === "max";
    }

    function getPrompt() {
        return clienttranslate("Choose a card to discard to make room, or skip the draw");
    }

    function getPossibleMoves() {
        $hero = $this->game->getHero($this->getOwner());
        if ($hero->getCountOfCardsInEventDeck() == 0) {
            return [
                "q" => Material::ERR_NOT_APPLICABLE,
                "err" => clienttranslate("No cards left to draw"),
            ];
        }
        if (!$this->isMax() && $hero->getHandSize() >= $hero->getHandLimit()) {
            return $this->instantiateOperation("discardEvent")->getPossibleMoves();
        }
        return [
            "prompt" => clienttranslate("Confirm to draw event card, this cannot be undone"),
            "confirm" => [
                "q" => 0,
            ],
        ];
    }

    function resolve(): void {
        $target = $this->getCheckedArg();
        $hero = $this->game->getHero($this->getOwner());
        $max = $this->isMax();
        $requeue = null;
        if ($target == "confirm") {
            $count = $max ? $hero->getHandLimit() - $hero->getHandSize() : $this->getCount();
            $drawn = 0;
            for ($i = 0; $i < $count; $i++) {
                if (!$hero->drawEventCard()) {
                    break;
                }
                $drawn++;
            }
            if (!$max) {
                $remaining = $count - $drawn;
                if ($remaining > 0) {
                    $requeue = "{$remaining}drawEvent";
                }
            }
        } else {
            $hero->discardEventCard($target);
            $hero->drawEventCard();
            $remaining = $this->getCount() - 1;
            if ($remaining > 0) {
                $requeue = "{$remaining}drawEvent";
            }
        }
        if ($requeue !== null) {
            $this->queue($requeue);
        }
    }

    function canSkip() {
        return true;
    }

    function getSkipName() {
        return clienttranslate("Skip Draw");
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }

    function requireConfirmation() {
        return !$this->noValidTargets();
    }
}
