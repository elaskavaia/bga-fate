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
 * spendUseAny: spendUse that also works in trigger context, for once-per-turn
 * effects played inside another action, e.g. Quiver `spendUseAny:spendDurab:addDamage`.
 * Shares the card's used flag with spendUse.
 * Not for damage-prevention (TResolveHits) cards - RULES.md exempts them from once per turn.
 */
class Op_spendUseAny extends Op_spendUse {
    protected function isManualOnly(): bool {
        return false;
    }
}
