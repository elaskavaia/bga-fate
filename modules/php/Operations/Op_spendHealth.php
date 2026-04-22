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

use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 * spendHealth: Take X unpreventable damage to the acting hero as a cost.
 * Count = amount of damage taken (default 1).
 *
 * Rules (RULES.md): "When a character is dealt unpreventable damage, it cannot use
 * effects to prevent it." So this op bypasses the dealDamage → preventDamage pipeline
 * and applies red crystals directly to the hero.
 *
 * Used by: Berserk — `r=spendHealth:3addDamage`
 */
class Op_spendHealth extends CountableOperation {
    function resolve(): void {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $amount = (int) $this->getCount();

        $this->game->effect_moveCrystals($heroId, "red", $amount, $heroId, ["message" => ""]);

        $this->game->getHero($owner)->applyDamageEffects($amount, $heroId);
    }
}
