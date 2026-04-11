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
 * spendGold: Remove X yellow crystals (gold/arrows) from the context card as a cost.
 * Count = amount of gold to spend (default 1).
 * Parallel to spendMana, but uses yellow crystals taken from the same card.
 * Used by: Black Arrows (`1spendGold:3addDamage` — spend 1 arrow to add 3 damage),
 *          future "spend token from card" effects.
 */
class Op_spendGold extends CountableOperation {
    function getPossibleMoves() {
        $cardId = $this->getDataField("card");
        if (!$cardId) {
            return [];
        }
        $amount = (int) $this->getCount();
        $gold = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", $cardId));
        return [$cardId => ["q" => $gold >= $amount ? Material::RET_OK : Material::ERR_NOT_APPLICABLE]];
    }

    function resolve(): void {
        $cardId = $this->getCheckedArg();
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $amount = (int) $this->getCount();
        $this->game->effect_moveCrystals($heroId, "yellow", -$amount, $cardId, [
            "message" => clienttranslate('${char_name} spends ${count} gold from ${place_name}'),
        ]);
    }
}
