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
 * Base class for the main turn actions (Op_action*).
 *
 * A main action consumes a turn slot only when queued with data "spend" => 1
 * (Op_turn does this); card-driven performs (Rapid Strike, Speedy Attack,
 * Sophisticated, performAction) omit the flag and stay free. Each subclass
 * calls spendTurnSlot() at the top of resolve().
 */
abstract class AbsOp_action extends Operation {
    protected function spendTurnSlot(): void {
        if ($this->getDataField("spend", 0)) {
            $this->game->getHero($this->getOwner())->placeActionMarker($this->getType());
        }
    }
}
