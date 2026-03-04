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

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Mend action: remove 2 damage from hero (5 if in Grimheim).
 */
class Op_actionMend extends Operation {
    function getPossibleMoves() {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $currentDamage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $heroId));
        if ($currentDamage == 0) {
            return ["q" => Material::ERR_PREREQ];
        }
        return parent::getPossibleMoves();
    }

    function resolve(): void {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $currentHex = $this->game->tokens->getTokenLocation($heroId);
        $inGrimheim = $this->game->hexMap->isInGrimheim($currentHex);
        $amount = $inGrimheim ? 5 : 2;

        // Cap at actual damage on hero
        $currentDamage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $heroId));
        $amount = min($amount, $currentDamage);

        if ($amount > 0) {
            $this->game->effect_moveCrystals($heroId, "red", -$amount, $heroId, [
                "message" => clienttranslate('${char_name} mends ${count} damage'),
            ]);
        }
    }
}
