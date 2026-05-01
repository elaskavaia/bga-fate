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
 * applyDamage: when damage is deal - apply effect, possibly trigger other effects
 *
 */
class Op_applyDamage extends Operation {
    public function getPossibleMoves() {
        $target = $this->getDataField("target");
        return $target ? [$target] : [];
    }

    function resolve(): void {
        $defenderId = $this->getDataField("target");
        $attackerId = $this->getDataField("attacker");
        if ($attackerId === null) {
            $attackerId = $this->game->getHeroTokenId($this->getOwner());
        }

        $defender = $this->game->getCharacter($defenderId);
        $targetHex = $defender->getHex();
        $amount = (int) $this->getDataField("amount", $defender->getDamage());

        $remaining = $defender->applyDamageEffects($amount, $attackerId);
        // Store overkill on marker_attack state
        $this->game->tokens->dbSetTokenLocation("marker_attack", $targetHex, -$remaining, "");

        if ($remaining <= 0 && str_starts_with($defenderId, "monster_")) {
            $this->queueTrigger(Trigger::MonsterKilled);
        }
    }

    public function canSkip() {
        if ($this->noValidTargets()) {
            return parent::canSkip();
        }
        return false; // mandatory if possible
    }
}
