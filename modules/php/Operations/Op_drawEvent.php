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

use Bga\Games\Fate\OpCommon\Operation;

/**
 * Draw 1 event card from deck to hand.
 * - Hand < 4: auto-draws immediately.
 * - Hand >= 4: asks player to discard a card or skip the draw.
 * - Deck empty: does nothing.
 */
class Op_drawEvent extends Operation {
    function auto(): bool {
        $hero = $this->game->getHero($this->getOwner());
        if ($hero->getHandSize() >= $hero->getHandLimit()) {
            return false; // enter player state to choose discard or skip
        }
        if (!$hero->drawEventCard()) {
            $this->game->notifyMessage(clienttranslate('${char_name} has no cards left to draw'), [
                "char_name" => $hero->getId(),
            ]);
        }
        $this->destroy();
        return true;
    }

    function getPrompt() {
        return clienttranslate("Choose a card to discard to make room, or skip the draw");
    }

    function getPossibleMoves() {
        return $this->instanciateOperation("discardEvent")->getPossibleMoves();
    }

    function canSkip() {
        return true;
    }

    function getSkipName() {
        return clienttranslate("Skip Draw");
    }

    function resolve(): void {
        $target = $this->getCheckedArg();
        $hero = $this->game->getHero($this->getOwner());
        $hero->discardEventCard($target);
        $hero->drawEventCard();
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
