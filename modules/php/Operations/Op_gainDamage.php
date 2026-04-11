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
 * gainDamage: Add 1 damage (red crystal) to an equipment card as a durability cost.
 *
 * Data Fields:
 * - card - the equipment card to place damage on (set by useEquipment)
 *
 * Behaviour:
 * - Normal case: places 1 red crystal on card, automated (no user choice)
 * - Precondition failure: card already at max durability → ERR_NOT_APPLICABLE
 *
 * Used by: Helmet (gainDamage:1preventDamage), Leather Purse (gainDamage:2heal(adj)),
 *          Throwing Axes (gainDamage:3roll(adj)), Shield (gainDamage:2preventDamage), etc.
 */
class Op_gainDamage extends Operation {
    function getPossibleMoves() {
        $cardId = $this->getDataField("card");
        if (!$cardId) {
            return ["q" => Material::ERR_NOT_APPLICABLE];
        }
        $durability = (int) $this->game->material->getRulesFor($cardId, "durability", "0");
        $damage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $cardId));
        if ($damage >= $durability) {
            return [$cardId => ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("Max durability reached")]];
        }
        return [$cardId => ["q" => Material::RET_OK]];
    }

    function resolve(): void {
        $cardId = $this->getCheckedArg();
        $heroId = $this->game->getHeroTokenId($this->getOwner());
        $this->game->effect_moveCrystals($heroId, "red", 1, $cardId, [
            "message" => clienttranslate('${char_name} adds damage to ${place_name}'),
        ]);
    }
}
