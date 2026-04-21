<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

/**
 * c_hailII: Hail of Arrows II — 1-4[MANA]: Deal 1 damage to that many different monsters within attack range.
 *
 * Data Fields:
 * - card: context card id (card_ability_2_4)
 *
 * Behaviour:
 * - Player selects 1..4 different monster hexes within attack range (multi-select).
 * - Spends mana equal to the number of monsters selected (1..4).
 *
 * Used by: Hail of Arrows II (card_ability_2_4).
 */
class Op_c_hailII extends Op_c_hail {
    protected function getMaxTargets(): int {
        return 4;
    }

    protected function getManaCost(int $selected): int {
        return $selected;
    }

    function getMinCount() {
        return 1;
    }

    function getPrompt() {
        return clienttranslate('Select up to ${count} monsters to deal 1 damage (cost: 1 mana each)');
    }
}
