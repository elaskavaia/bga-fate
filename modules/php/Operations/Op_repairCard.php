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
 * repairCard: remove damage from cards on the player's tableau. Card-only specialization
 * of Op_removeDamage. All target/iteration/all-mode logic lives in the parent.
 *
 * Params (param 0):
 *  - ""    — pick one card, remove 1 damage from it (per unit; re-queues if count > 1)
 *  - "max" — pick one card, remove ALL damage from it (per unit)
 *  - "all" — apply to every damaged card at once (no per-card prompt)
 *
 * Used by: Mend in Grimheim (5repairCard), Stitching II (2repairCard),
 *  Durability (repairCard(max)), Sewing (repairCard(all)).
 */
class Op_repairCard extends Op_removeDamage {
    function getPrompt() {
        return clienttranslate("Choose a card to repair");
    }

    protected function allowsHeroes(): bool {
        return false;
    }

    protected function allowsCards(): bool {
        return true;
    }
}
