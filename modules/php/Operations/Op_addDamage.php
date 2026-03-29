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
 * addDamage: Add X hit dice to display_battle (guaranteed damage, no roll needed).
 * Count = number of damage dice to add (default 1).
 *
 * Behaviour:
 * - Automated: places count dice on display_battle with state 6 (hit/damage)
 * - Used by cards that say "add X damage to this attack action"
 *
 * Used by: Master Shot, Berserk, hero card effects, etc.
 */
class Op_addDamage extends CountableOperation {
    function resolve(): void {
        $amount = (int) $this->getCount();
        $attackerId = $this->getDataField("attacker") ?? $this->game->getHeroTokenId($this->getOwner());
        $this->game->effect_addAttackDiceDamage($attackerId, $amount);
    }
}
