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
 * dealDamageUnprev: dealDamage that ignores armor. Prevention cards cannot stop it either,
 * since Op_preventDamage only matches type "dealDamage".
 *
 * Rules (RULES.md): "When a character is dealt unpreventable damage, it cannot use effects to prevent it."
 *
 * Used by: Precision Axes.
 */
class Op_dealDamageUnprev extends Op_dealDamage {
    protected function isArmorApplicable(): bool {
        return false;
    }
}
