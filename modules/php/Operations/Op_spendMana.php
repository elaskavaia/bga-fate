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
use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 * spendMana: Remove X mana (green crystals) from a card on tableau (cost).
 * Count = amount of mana to spend (default 1).
 * Rules: "Many abilities require you to spend 1 or more mana (green) to perform their effects.
 *         That mana must be taken from the same card; you may not pay with mana from other cards."
 * Used by: mana-activated abilities (e.g. Sure Shot, Dreadnought, Rapid Strike, Fire Spark).
 *
 * Location preconditions are expressed via the `in(Grimheim):` prefix in the rule, not
 * via a param on this op.
 */
class Op_spendMana extends CountableOperation {
    function getPossibleMoves() {
        $cardId = $this->getDataField("card");
        if (!$cardId) {
            return [];
        }
        $amount = (int) $this->getCount();
        $mana = count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", $cardId));
        return [$cardId => ["q" => $mana >= $amount ? Material::RET_OK : Material::ERR_COST]];
    }

    function resolve(): void {
        $cardId = $this->getCheckedArg();
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $amount = (int) $this->getCount();
        $this->game->effect_moveCrystals($heroId, "green", -$amount, $cardId, [
            "message" => clienttranslate('${char_name} spends ${count} [MANA] from ${place_name}'),
        ]);
    }
}
