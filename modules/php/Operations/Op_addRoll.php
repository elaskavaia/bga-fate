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

/**
 * addRoll: Like roll, but ADDS dice to an existing attack in progress instead of
 * starting a fresh roll. Op_roll's `effect_rollAttackDice` normally sweeps leftover
 * dice off display_battle before rolling; addRoll skips that sweep via isAddition().
 *
 * Used by cards that say "Add N [DIE_ATTACK] to this attack action" (e.g. Mastery).
 * Only valid after another roll — getPossibleMoves() gates on dice already being on
 * display_battle so the op is void outside an attack.
 */
class Op_addRoll extends Op_roll {
    function isAddition() {
        return true;
    }
    function shouldEmitTrigger() {
        // Added dice must not re-fire TRoll/TActionAttack, otherwise cards that respond to
        // those triggers by calling addRoll (e.g. Windbite) would loop on their own dice.
        return false;
    }
}
