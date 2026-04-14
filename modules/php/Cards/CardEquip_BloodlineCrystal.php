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
 * Bloodline Crystal (card_equip_2_25)
 *
 * mana=2, r=(3spendMana:2addDamage)/(3spendMana:drawEvent), on=custom
 *
 * Effect: "3[MANA]: Add 2 damage to this attack action.
 *          3[MANA]: Draw 1 card."
 *
 * The `or` rule in the CSV presents both branches to the player. The add-damage
 * branch is automatically filtered out when not in an attack action — Op_addDamage
 * reports ERR_NOT_APPLICABLE when there are no dice on display_battle — so outside
 * an attack only the draw branch is selectable.
 *
 * This card listens on two triggers (both route through the generic useCard flow):
 *  - actionAttack: damage branch becomes viable mid-attack
 *  - manual (empty trigger): player activates as a free action at any time
 */
class CardEquip_BloodlineCrystal extends CardGeneric {
    public function onActionAttack(): void {
        $this->onTriggerDefault("actionAttack");
    }

    public function onManual(): void {
        $this->onTriggerDefault("");
    }
}
