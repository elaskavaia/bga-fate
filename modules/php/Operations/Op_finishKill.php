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

use Bga\Games\Fate\OpCommon\Operation;

/**
 * finishKill: Cleanup step queued by Op_applyDamage when a kill or knockout is
 * detected. Runs AFTER the matching trigger op (TMonsterKilled / THeroKnockedOut),
 * so handlers see the dying character on its pre-cleanup hex with its bonus
 * crystals intact.
 *
 * Dispatches via polymorphism: each Character subclass implements
 * finalizeDamage() — Monster removes from map and awards XP, Hero is knocked
 * back to Grimheim, GoldVein extracts gold for the attacker.
 *
 * Data Fields:
 *  - target: defender token id
 *  - attacker: attacker token id (hero id or monster id)
 *  - amount: damage amount that triggered the kill (used by GoldVein for XP)
 */
class Op_finishKill extends Operation {
    function resolve(): void {
        $defenderId = $this->getDataField("target");
        $attackerId = $this->getDataField("attacker");
        $amount = (int) $this->getDataField("amount", 0);
        $noXp = (bool) $this->getDataField("noXp", false);

        $defender = $this->game->getCharacter($defenderId);
        $defender->finalizeDamage($amount, $attackerId, $noXp);
    }

    public function canSkip() {
        return false;
    }
}
