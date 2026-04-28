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
 * Heal: remove X damage (red crystals) from a hero. Hero-only specialization of Op_removeDamage.
 *
 * Per-unit re-queue (inherited): when count > 1 and multiple damaged heroes are eligible,
 * the player picks one hero per unit ("Heal 2 damage from yourself or any adjacent hero"
 * may split between heroes).
 *
 * Param (param 0):
 *  - "self" (default) — acting hero only
 *  - "adj"            — heroes within range 1 (including self)
 *
 * Used by: Rest (2heal(self)), Stitching (heal(adj)), Belt of Youth (1heal(self)),
 *  Healing Potion (1heal(adj)), Leather Purse (2heal(adj)), etc.
 */
class Op_heal extends Op_removeDamage {
    function getPrompt() {
        if ($this->isOneChoice()) {
            return clienttranslate('Confirm heal ${count} damage');
        }
        return clienttranslate("Choose a hero to heal");
    }

    protected function getMode(): string {
        return $this->getParam(0, "self");
    }

    protected function allowsHeroes(): bool {
        return true;
    }

    protected function allowsCards(): bool {
        return false;
    }
}
