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
 * Prepare action: hero prepares (charges a skill or readies equipment).
 */
class Op_actionPrepare extends Operation {
    function resolve(): void {
        $this->queue($this->getDelegateOperation());
    }
    function getDelegateOperation(): string {
        return "drawEvent";
    }
    function getPrompt() {
        return $this->instantiateOperation($this->getDelegateOperation())->getPrompt();
    }
}
