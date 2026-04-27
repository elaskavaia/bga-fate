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
use Bga\Games\Fate\Model\Trigger;

/**
 * Eitri's Pick (card_equip_4_22).
 *
 *   "When you use Rapid Strike, add 2 [DIE_ATTACK] to that attack action."
 *
 * Auto-effect (no useCard prompt): when the running actionAttack op was
 * queued from Rapid Strike I or II (its data field card is one of those
 * card ids), queue 2addRoll. The +2 strength column handles the passive
 * weapon bonus generically; this hook only handles the conditional bonus.
 */
class CardEquip_EitrisPick extends CardGeneric {
    private const RAPID_STRIKE_I = "card_ability_4_3";
    private const RAPID_STRIKE_II = "card_ability_4_4";

    public function onActionAttack(Trigger $event): void {
        $sourceCard = $this->op->getDataField("card", "");
        if ($sourceCard !== self::RAPID_STRIKE_I && $sourceCard !== self::RAPID_STRIKE_II) {
            return;
        }
        $this->op->queue("2addRoll", null, ["card" => $this->id, "reason" => $this->id]);
    }
}
