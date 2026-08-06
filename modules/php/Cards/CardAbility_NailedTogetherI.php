<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Cards;

use Bga\Games\Fate\Model\CardGeneric;
use Bga\Games\Fate\Model\Trigger;

/**
 * Nailed Together I (card_ability_1_13).
 *
 *   "If you kill a monster in an attack action, all remaining damage may be
 *    dealt to a second monster behind it (adjacent and further away)."
 *
 * One pierce: "a second monster", where level II says "and so on". Only the
 * trigger wiring is bespoke - the effect itself is the CSV r=c_nailed.
 */
class CardAbility_NailedTogetherI extends CardGeneric {
    public function onMonsterKilled(Trigger $event): void {
        // The pierce's own kill re-fires MonsterKilled and would offer the card again, so
        // level I would pierce twice and level II would chain twice per link (its real chain
        // is re-queued by Op_c_nailed itself).
        if ($this->op->getDataField("spendsExcess")) {
            return;
        }
        $this->onTriggerDefault($event);
    }
}
