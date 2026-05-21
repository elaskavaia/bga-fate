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

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Model\Trigger;

/**
 * addRoll: Like roll, but ADDS dice to an existing attack in progress instead of
 * starting a fresh roll. Op_roll's `effect_rollAttackDice` normally sweeps leftover
 * dice off display_battle before rolling; addRoll skips that sweep via isAddition().
 *
 * Used by cards that say "Add N [DIE_ATTACK] to this attack action" (e.g. Mastery).
 * Only valid after another roll — getPossibleMoves() gates on marker_attack being set
 * so the op is void outside an active attack.
 *
 * Trigger semantics: emits Trigger::Roll (not ActionAttack — added dice are not a new
 * attack action). Other on=TRoll cards can react to the new dice; cards that need to
 * avoid double-counting their own re-firing must guard themselves (e.g. Windbite uses
 * `counter(countNewRunes)` which tracks a high-water mark on the card itself).
 */
class Op_addRoll extends Op_roll {
    function getPrompt() {
        return clienttranslate('Roll ${count} dice');
    }
    function isAddition() {
        return true;
    }
    function getEmittedTrigger(): Trigger {
        // Added dice never start a new attack action — always Roll, never ActionAttack.
        return Trigger::Roll;
    }
    function getPossibleMoves(): array {
        $presetTarget = $this->getDataField("target");
        if ($presetTarget) {
            return [$presetTarget];
        }

        $attackHex = $this->game->getAttackHex();
        if (!$attackHex) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("Not possible at this moment")];
        }

        return [$attackHex => ["q" => 0, "name" => clienttranslate("Confirm")]];
    }

    function resolve(): void {
        // High-water-mark bump for any countNewRunes consumer (e.g. Windbite)
        $this->game->tokens->setTokenState("marker_runes", $this->game->countRunes());
        parent::resolve();
    }
}
