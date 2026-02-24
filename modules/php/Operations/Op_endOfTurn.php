<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
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
 * End of turn sequence.
 * Runs automatically after a player has taken their 2 actions (or ended turn early).
 */
class Op_endOfTurn extends Operation {
    function getPossibleMoves() {
        return ["confirm"];
    }

    function resolve(): void {
        // TODO: implement end-of-turn sequence:
        // 1. Reset action markers to empty slots
        // 2. Check for upgrade eligibility (spend experience to upgrade hero/abilities)
        // 3. Add mana to cards with mana generation (green icon)
        // 4. Draw 1 event card (if hand < 4, otherwise allow discard first)
        // 5. Allow cycling top equipment or top ability card
    }
}
