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
 * which CardGeneric's single `on=` field cannot express. Each hook just queues
 * the standard useCard prompt — the OR-split inside the card's `r` expression
 * uses `on(TXxx):` gates to pick the matching branch, and Op_or::isTrivial
 * auto-resolves to the single non-void branch.
 */
class CardAbility_SweepingStrikeI extends CardGeneric {
    public function onActionAttack(Trigger $event): void {
        $this->promptUseCard($event);
    }

    public function onMonsterKilled(Trigger $event): void {
        // Guard like CardGeneric::onTriggerDefault: a cleave kill re-fires MonsterKilled,
        // but by then the sweep is spent (no overkill left), so c_sweep is void and the
        // card must not re-prompt with an empty target list (BGA #233927).
        if (!$this->canBePlayed($event)) {
            return;
        }
        $this->promptUseCard($event);
    }
}
