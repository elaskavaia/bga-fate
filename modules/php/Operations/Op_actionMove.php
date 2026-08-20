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

/**
 * Move action: hero moves up to 3 areas (some abilities may change this).
 * Delegates to Op_move, which decides itself between one-click and step mode.
 * See DESIGN.md "Step-by-step Move".
 */
class Op_actionMove extends AbsOp_action {
    function getNumberOfMoves(): int {
        $hero = $this->game->getHero($this->getOwner());
        return $hero->getNumberOfMoves();
    }

    private function getDelegateType(): string {
        $steps = $this->getNumberOfMoves();
        return "[1,{$steps}]move";
    }

    function getPossibleMoves(): array {
        $target = $this->getDataField("target", "");
        if ($target) {
            return [$target];
        }
        // If the hero's move tracker has been reduced to 0 (e.g. Seek Shelter played this turn),
        // the move action is not available. Return an error so the turn op filters it out
        // rather than constructing an invalid "[1,0]move" delegate.
        if ($this->getNumberOfMoves() <= 0) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No moves remaining this turn")];
        }
        return $this->getPossibleMovesDelegate($this->getDelegateType());
    }

    function resolve(): void {
        $this->spendTurnSlot();
        // The delegate reads getReason() == "Op_actionMove" and emits Trigger::ActionMove
        // on completion (chains through Trigger::Move). One trigger per move.
        $this->queue($this->getDelegateType(), null, ["target" => $this->getDataField("target", "")]);
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }

    function getPrompt() {
        return clienttranslate("Choose where to move");
    }

    public function getIconicName(): string {
        return clienttranslate("Move [Op_actionMove]");
    }
}
