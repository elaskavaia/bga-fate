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

use Bga\Games\Fate\OpCommon\Operation;

/**
 * Focus action: Add 1 mana (green) to one of your cards.
 * Delegates to gainMana operation.
 */
class Op_actionFocus extends Operation {
    function getDelegateOperation(): string {
        return "gainMana";
    }

    function getPossibleMoves() {
        return $this->getPossibleMovesDelegate($this->getDelegateOperation());
    }

    function resolve(): void {
        $this->queue($this->getDelegateOperation());
    }
}
