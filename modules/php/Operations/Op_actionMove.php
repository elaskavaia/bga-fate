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
 * Move action: hero moves up to 3 areas (some abilities may change this).
 * Delegates to moveHero operation.
 */
class Op_actionMove extends Operation {
    function getNumberOfMoves(): int {
        // Default 3 moves; abilities can override this later
        // TODO: find cards that change this
        return 3;
    }

    function getDelegateOperation() {
        $steps = $this->getNumberOfMoves();
        return "[1,{$steps}]moveHero";
    }

    function getPossibleMoves(): array {
        return $this->getPossibleMovesDelegate($this->getDelegateOperation());
    }

    function resolve(): void {
        $this->queue($this->getDelegateOperation());
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }

    function getPrompt() {
        return clienttranslate("Select where to move");
    }
}
