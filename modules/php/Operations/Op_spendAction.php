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
use Bga\Games\Fate\OpCommon\Operation;

/**
 * spendAction: Consume a main action slot without performing the action itself.
 * Used by event cards that cost an action (e.g. Preparations: spend prepare to draw to hand limit).
 *
 * param(0): action type to spend (e.g. "actionPrepare")
 */
class Op_spendAction extends Operation {
    function getActionType(): string {
        return $this->getParam(0) ?? "actionPrepare";
    }

    function getPossibleMoves(): array {
        $actionType = $this->getActionType();
        $hero = $this->game->getHero($this->getOwner());
        if ($hero->getActionsRemaining() <= 0) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No actions remaining")];
        }
        if (in_array($actionType, $hero->getActionsTaken())) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("Action already taken this turn")];
        }
        return ["q" => Material::RET_OK];
    }

    function resolve(): void {
        $this->game->getHero($this->getOwner())->placeActionMarker($this->getActionType());
    }
}
