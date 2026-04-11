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
use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 * addDamage: Add X hit dice to display_battle (guaranteed damage, no roll needed).
 * Count = number of damage dice to add (default 1).
 *
 * Params:
 * - param(0): range specifier — "" or "true" (no range check), "dist" (amount = distance to target),
 *             or numeric min-range (e.g. "2" means range 2+)
 * - param(1): optional filter expression evaluated against the current attack target monster.
 *             Uses the MathExpression bareword terms defined in Game::evaluateTerm
 *             (e.g. "trollkin", "legend", "rank<=2"). Defaults to no filter.
 *
 * Behaviour:
 * - Automated: places count dice on display_battle with state 6 (hit/damage)
 * - Used by cards that say "add X damage to this attack action"
 *
 * Used by: Master Shot, Berserk, hero card effects, Trollbane (1addDamage(true,trollkin)), etc.
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

    private function matchesFilter(): bool {
        $filter = $this->getParam(1, "");
        if ($filter === "" || $filter === "true") {
            return true;
        }
        $hex = $this->game->getAttackHex();
        if ($hex === null) {
            return false;
        }
        $defenderId = $this->game->hexMap->getCharacterOnHex($hex, null);
        if ($defenderId === null) {
            return false;
        }
        return !!$this->game->evaluateExpression($filter, $this->getOwner(), $defenderId);
    }

    public function getPossibleMoves() {
        $param = $this->getRangeParam();
        if ($param === "dist") {
            $dist = $this->getDistanceToTarget();
            if ($dist === null || $dist < 1) {
                return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No attack target")];
            }
        } elseif ($param !== "" && $param !== "true") {
            $minRange = (int) $param;
            $dist = $this->getDistanceToTarget();
            if ($dist === null || $dist < $minRange) {
                return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("Target not in range")];
            }
        }
        if (!$this->matchesFilter()) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("Target does not match filter")];
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
