<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Cards;

/**
 * Sweeping Strike II (card_ability_4_6).
 *
 * Identical trigger wiring to level I — the difference is that the passive
 * damage bullet scales with the number of adjacent monsters.
 */
class CardAbility_SweepingStrikeII extends CardAbility_SweepingStrikeI {
    protected function getDamageEffect(): string {
        return "counter(countAdjMonsters):addDamage";
    }
}
