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

use Bga\Games\Fate\OpCommon\ComplexOperation;

class Op_order extends ComplexOperation {
    public function auto(): bool {
        $this->game->systemAssert("Op_order does not support counts", $this->getCount() == 1 && $this->getMinCount() == 1);
        // Auto-queue trivial delegates (order doesn't matter for them)
        // If 0 or 1 delegates, queue and auto-resolve
        if ($this->isTrivial() || count($this->delegates) <= 1) {
            foreach ($this->delegates as $sub) {
                $this->queueOp($sub);
            }
            return true;
        }

        return false;
    }

    function isTrivial(): bool {
        foreach ($this->delegates as $sub) {
            if (!$sub->isTrivial()) {
                return false;
            }
        }
        return true;
    }

    function resolve(): void {
        // this suppose to pick selected operation and push on top of stack, remaing choice if any stored back
        $target = $this->getCheckedArg();
        foreach ($this->delegates as $i => $arg) {
            if ("choice_$i" == $target) {
                $this->queueOp($arg);
                $arg->destroy();
                unset($this->delegates[$i]);
                break;
            }
        }
        if (count($this->delegates) > 0) {
            $this->queueOp($this);
        }

        return;
    }

    function getPossibleMoves() {
        $res = [];
        foreach ($this->delegates as $i => $sub) {
            $res["choice_$i"] = $this->paramInfo($sub);
        }
        return $res;
    }

    function getPrompt() {
        return clienttranslate("Choose order of operations");
    }
    function getDescription() {
        return clienttranslate('${actplayer} chooses order');
    }
    function getOperator() {
        return "+";
    }

    function getOpName() {
        return $this->getRecName(" + ");
    }
}
