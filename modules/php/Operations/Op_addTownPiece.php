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
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Add 1 Town Piece to Grimheim: move the first house in limbo back to its home hex.
 * Limbo also holds pieces unused at setup, so the count may exceed the starting count, up to all 10.
 *
 * Used by: Inspire Defense, Boldur's Gate.
 */
class Op_addTownPiece extends Operation {
    function getPossibleMoves() {
        $houses = $this->game->tokens->getTokensOfTypeInLocation("house", "limbo");
        if (empty($houses)) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("All Town Pieces are already in Grimheim")];
        }
        $houseId = array_key_first($houses);
        return [$houseId];
    }

    function resolve(): void {
        $houseId = $this->getCheckedArg();
        $homeHex = $this->game->material->getRulesFor($houseId, "location");
        $this->game->systemAssert("ERR:addTownPiece:noHomeHex:$houseId", $homeHex !== null);
        $this->game->tokens->dbSetTokenLocation($houseId, $homeHex, 0, clienttranslate('${token_name} is added to Grimheim'), []);
        $this->game->notifyHousesCounter();
    }
}
