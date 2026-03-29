<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 * addDamage: Add X hit dice to display_battle (guaranteed damage, no roll needed).
 * Count = number of damage dice to add (default 1).
 *
 * Behaviour:
 * - Automated: places count dice on display_battle with state 6 (hit/damage)
 * - Used by cards that say "add X damage to this attack action"
 *
 * Used by: Master Shot, Berserk, hero card effects, etc.
 */
class Op_addDamage extends CountableOperation {
    private function getRangeParam(): string {
        return $this->getParam(0, "");
    }

    private function getDistanceToTarget(): ?int {
        $hex = $this->game->getAttackHex();
        if ($hex === null) {
            return null;
        }
        $hero = $this->game->getHero($this->getOwner());
        return $this->game->hexMap->getHexDistance($hero->getHex(), $hex);
    }

    public function getPossibleMoves() {
        $param = $this->getRangeParam();
        if ($param === "") {
            return parent::getPossibleMoves();
        }
        if ($param === "dist") {
            $dist = $this->getDistanceToTarget();
            if ($dist === null || $dist < 1) {
                return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No attack target")];
            }
            return parent::getPossibleMoves();
        }
        // Numeric param: minimum distance required (e.g. "2" means range 2+)
        $minRange = (int) $param;
        $dist = $this->getDistanceToTarget();
        if ($dist === null || $dist < $minRange) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("Target not in range")];
        }
        return parent::getPossibleMoves();
    }

    function resolve(): void {
        $param = $this->getRangeParam();
        $amount = (int) $this->getCount();
        if ($param === "dist") {
            $amount = $this->getDistanceToTarget() ?? 0;
        }
        $attackerId = $this->game->getHeroTokenId($this->getOwner());
        $this->game->effect_addAttackDiceDamage($attackerId, $amount);
    }
}
