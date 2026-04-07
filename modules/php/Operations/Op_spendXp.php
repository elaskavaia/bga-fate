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

use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 *  Spend X gold/XP (move yellow crystals from supply to player tableau).
 */
class Op_spendXp extends CountableOperation {
    function resolve(): void {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $cost = (int) $this->getCount();
        $this->game->effect_moveCrystals($heroId, "yellow", -$cost, "tableau_$owner", [
            "message" => clienttranslate('${char_name} spends ${count} XP ${reason}'),
            "reason" => $this->getReason(),
        ]);
    }
}
