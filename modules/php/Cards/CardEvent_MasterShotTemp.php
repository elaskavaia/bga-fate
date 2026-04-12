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

namespace Bga\Games\Fate\Cards;

use Bga\Games\Fate\Model\CardGeneric;

/**
 * Master Shot (card_event_1_26)
 *
 * Effect: "Add 2 damage to this attack action."
 *
 * Only playable after a roll during an attack action.
 */
class CardEvent_MasterShotTemp extends CardGeneric {
    public function onRoll(): void {
        $this->checkPlayability("roll");
        $this->queue("2addDamage");
    }

    public function canTriggerEffectOn(string $triggerName): bool {
        if ($triggerName == "roll" && $this->isAttackAction()) {
            echo "+MasterShot can trigger $triggerName\n";
            return true;
        }
        echo "MasterShot cannot trigger on $triggerName\n";
        return false;
    }
}
