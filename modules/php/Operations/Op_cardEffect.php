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

use Bga\Games\Fate\OpCommon\Operation;

/**
 * cardEffect - wrapper on card rules, only purpose is ability to verrife some presentation effects
 * not used not
         //   $op = $this->op->instantiateOperation("cardEffect", $this->getOwner(), [
        //         "r" => $r,
        //         "card" => $cardId,
        //         "reason" => $cardId,
        //         "event" => $event->value,
        //     ]);
 */
class Op_cardEffect extends Operation {
    public ?Operation $delegate = null;
    protected function getCard(): string {
        return $this->getDataField("card", "");
    }

    function getDelegateOp() {
        if ($this->delegate == null) {
            $r = $this->getRules();
            $op = $this->instantiateOperation($r, null, ["reason" => $this->getCard(), "card" => $this->getCard()]);
            //$this->withDelegate($op);
            $this->delegate = $op;
            $op->withData($this->getData(), true);
            $op->withDataField("confirm", true);
        }
        return $this->delegate;
    }

    public function getPossibleMoves() {
        // $op = $this->getDelegateOp();
        // return $op->getPossibleMoves();
        return ["confirm"];
    }

    protected function getRules(): string {
        return $this->getDataField("r", "nop");
    }

    public function getPrompt() {
        return $this->getOpName();
    }

    function requireConfirmation() {
        return false;
    }

    public function canSkip() {
        return false;
    }

    function resolve(): void {
        $op = $this->getDelegateOp();
        $this->queueOp($op);
    }

    public function getSkipName() {
        return clienttranslate("Cancel");
    }

    // public function isSimple() {
    //     return !($this->getDelegateOp() instanceof ComplexOperation);
    // }

    public function isInline() {
        $cardId = $this->getCard();
        $r = $this->getRules();
        // this mean its the only effect on the card
        if ($r == $this->game->getRulesFor($cardId, "r")) {
            return true;
        } else {
            return false;
        }
    }

    public function getOpName() {
        $cardId = $this->getCard();
        // this mean its the only effect on the card
        if ($this->isInline()) {
            $name = $this->game->getRulesFor($cardId, "effect");
        } else {
            $name = $this->getDelegateOp()->getOpName();
        }
        return $name;
    }
}
