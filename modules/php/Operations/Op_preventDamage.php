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
 * preventDamage: Prevent up to X incoming damage.
 * Count = max damage to prevent (default 1).
 * Used by: Dodge, Stoneskin, Riposte, Dreadnought (1spendMana:1preventDamage).
 */
class Op_preventDamage extends CountableOperation {
    function resolve(): void {
        // TODO: implement
    }
}
