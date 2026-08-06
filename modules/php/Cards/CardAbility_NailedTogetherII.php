<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Cards;

/**
 * Nailed Together II (card_ability_1_14).
 *
 * Same trigger wiring as level I. The difference is in the card's `r`
 * expression: c_nailed(chain) re-queues itself while kills keep coming.
 */
class CardAbility_NailedTogetherII extends CardAbility_NailedTogetherI {
}
