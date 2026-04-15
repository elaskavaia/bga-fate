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
use Bga\Games\Fate\OpCommon\Operation;

/**
 * on(EventXxx): runtime event guard, used inside r expressions to restrict a
 * clause to a specific trigger context. Voids (ERR_PREREQ) if the current
 * useCard invocation's event does not match the param.
 *
 * Example (Flexibility I mid-attack branches):
 *   (1spendMana:move) / (2spendMana:on(EventActionAttack):1gainAtt(range))
 *
 * Reads getDataField("event") seeded by Card::useCard() at queue time.
 */
class Op_on extends Operation {
    private function getExpectedEvent(): string {
        return $this->getParam(0, "");
    }

    function getPossibleMoves() {
        $expected = $this->getExpectedEvent();
        $current = (string) $this->getDataField("event", "");
        if ($expected === "" || $current !== $expected) {
            return ["q" => Material::ERR_PREREQ];
        }
        return parent::getPossibleMoves();
    }

    function resolve(): void {
        // no-op gate
    }
}
