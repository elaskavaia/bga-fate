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

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Mend action: remove 2 damage from hero (5 if in Grimheim).
 */
class Op_actionMend extends Operation {
    function getPossibleMoves() {
        // check if can heal at least 1 damage
        return $this->instanciateOperation("heal")->getPossibleMoves();
    }

    function resolve(): void {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $currentHex = $this->game->tokens->getTokenLocation($heroId);
        $inGrimheim = $this->game->hexMap->isInGrimheim($currentHex);
        $amount = $inGrimheim ? 5 : 2;

        $this->queue("{$amount}heal");
    }
}
