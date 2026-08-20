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

/**
 * Backward compatibility shim. The step loop is Op_move step mode now, but a game already in
 * progress when that shipped can have a "moveStep" row sitting in its machine queue - without
 * this class it would fail to instantiate and the table would not load.
 *
 * Op_move drives everything; only the two things the old op did differently are restored here.
 * Resolving one hop re-queues a plain "move", so the type disappears from the queue after a
 * single step. Do not queue this op from new code.
 */
class Op_moveStep extends Op_move {
    /** The old op kept its remaining steps in "budget"; Op_move reads the CountableOperation count. */
    function getCount() {
        return $this->getDataField("budget", parent::getCount());
    }

    /** Reaching this op at all means the move was already routing step by step. */
    protected function isStepMode(): bool {
        return true;
    }
}
