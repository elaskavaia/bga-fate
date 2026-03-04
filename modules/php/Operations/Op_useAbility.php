<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com>
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
 * Use Ability free action: hero activates a special ability.
 */
class Op_useAbility extends Operation {
    public function getPossibleMoves() {
        return ["err" => "Not impl"];
    }
    function resolve(): void {
        $this->game->systemAssert("Op_useAbility is not implemented");
    }
}
