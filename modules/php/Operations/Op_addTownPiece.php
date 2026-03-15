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
 * addTownPiece: Add 1 Town Piece to Grimheim.
 * Automated operation — no user choice needed.
 * Used by: Inspire Defense (2spendMana(grimheim):addTownPiece).
 */
class Op_addTownPiece extends Operation {
    function resolve(): void {
        // TODO: implement
    }
}
