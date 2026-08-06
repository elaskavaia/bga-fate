<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Cards;

use Bga\Games\Fate\Model\CardGeneric;
use Bga\Games\Fate\Model\Trigger;

/**
 * Sweeping Strike I (card_ability_4_5).
 *
 *   "Add 1 damage to each attack action. If an adjacent monster is killed in
 *    your attack action, any remaining damage may be dealt to a second monster
 *    in clockwise order."
 *
 * Listens on two distinct trigger families (TActionAttack + TMonsterKilled),
 * which CardGeneric's single `on=` field cannot express.
 *
 * The damage bullet has no "may" in its card text, so it is applied without
 * asking. Only the sweep is offered, via the standard useCard prompt (r=c_sweep).
 */
class CardAbility_SweepingStrikeI extends CardGeneric {
    /** Level I adds a flat 1; level II scales with adjacent monsters. */
    protected function getDamageEffect(): string {
        return "addDamage";
    }

    public function onActionAttack(Trigger $event): void {
        $this->queue($this->getDamageEffect());
    }

    public function onMonsterKilled(Trigger $event): void {
        // One sweep per attack action (2 enemies max, DESIGN.md §"Sweeping Strike"). The
        // sweep's own kill re-fires MonsterKilled, and with damage still left over and another
        // monster on the ring it would otherwise offer itself again, forever.
        if ($this->op->getDataField("spendsExcess")) {
            return;
        }
        // Guard like CardGeneric::onTriggerDefault: without overkill c_sweep is void and the
        // card must not re-prompt with an empty target list (BGA #233927).
        if (!$this->canBePlayed($event)) {
            return;
        }
        $this->promptUseCard($event);
    }
}
