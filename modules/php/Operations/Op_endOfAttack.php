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

use Bga\Games\Fate\Model\Trigger;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * endOfAttack: Cleanup after the attack pipeline completes and trigger events.
 *
 */
class Op_endOfAttack extends Operation {
    function resolve(): void {
        $trigger = Trigger::AfterActionAttack;
        $this->queueTrigger($trigger);
        $this->game->tokens->dbSetTokenLocation("marker_attack", "limbo", 0, "");
        $this->game->tokens->dbSetTokenLocation("marker_instigator", "limbo", 0, "");
        // Reset the rune HWM used by countNewRunes (Windbite et al.).
        $this->game->tokens->setTokenState("marker_runes", 0);
    }
}
