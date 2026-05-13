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
use Override;

/**
 *  Spend X gold/XP (move yellow crystals from supply to player tableau).
 */
class Op_spendXp extends CountableOperation {
    #[Override]
    public function getPossibleMoves() {
        $owner = $this->getOwner();
        $cost = (int) $this->getCount();
        $xp = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_$owner"));
        if ($xp < $cost) {
            return ["q" => Material::ERR_COST];
        }
        return parent::getPossibleMoves();
    }
    function resolve(): void {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $cost = (int) $this->getCount();
        $this->game->effect_moveCrystals($heroId, "yellow", -$cost, "tableau_$owner", [
            "message" => clienttranslate('${char_name} spends ${count} [XP] ${reason}'),
            "reason" => $this->getReason(),
        ]);
    }
}
