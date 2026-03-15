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
 * Add 1 Town Piece to Grimheim: move the first destroyed house (in limbo) back to its home hex.
 *
 * Behaviour:
 * - Normal case: moves first house in limbo back to its material-defined home hex.
 * - No destroyed houses: ERR_NOT_APPLICABLE (should not happen — caller must guard).
 *
 * Used by: Inspire Defense (2spendMana(grimheim):addTownPiece).
 */
class Op_addTownPiece extends Operation {
    function getPossibleMoves() {
        $houses = $this->game->tokens->getTokensOfTypeInLocation("house", "limbo");
        if (empty($houses)) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No destroyed town pieces to restore")];
        }
        $houseId = array_key_first($houses);
        return [$houseId];
    }

    function resolve(): void {
        $houseId = $this->getCheckedArg();
        $homeHex = $this->game->material->getRulesFor($houseId, "location");
        $this->game->systemAssert("ERR:addTownPiece:noHomeHex:$houseId", $homeHex !== null);
        $this->game->tokens->dbSetTokenLocation($houseId, $homeHex, 0, clienttranslate('${token_name} is added to Grimheim'), []);
    }
}
