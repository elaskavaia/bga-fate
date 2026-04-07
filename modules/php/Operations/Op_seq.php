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

use Bga\Games\Fate\OpCommon\ComplexOperation;

/** Sequence of operations, no user choice */
class Op_seq extends ComplexOperation {
    function expandOperation() {
        if (count($this->delegates) == 0) {
            return true;
        }

        if ($this->isRangedChoice()) {
            return false;
        }
        if (!$this->isSubTrancient()) {
            return false;
        }

        $c = $this->getCount();
        foreach ($this->delegates as $sub) {
            $sub->destroy();
            $max = $sub->getDataField("count", 1);
            $min = $sub->getDataField("mcount", 1);
            $sub->withData($this->getData(), true);
            $sub->withDataField("count", $max * $c);
            $sub->withDataField("mcount", $min * $c);
            $this->queueOp($sub);
        }

        return true;
    }

    function getPossibleMoves() {
        if (count($this->delegates) == 0) {
            return [];
        }
        // cannot look beyond first sub, world can change after its executed
        $sub = $this->delegates[0];
        $sub->withData($this->getData(), true);

        if ($sub->isVoid()) {
            return $sub->getErrorInfo();
        }

        if ($this->isRangedChoice()) {
            return parent::getRangeMoves();
        }

        return $sub->getPossibleMoves();
    }

    function getPrompt() {
        if ($this->isRangedChoice()) {
            $max = $this->getCount();
            if ($max > 1) {
                return clienttranslate('Select how many times to perform ${name}');
            }
            return clienttranslate('Perform ${name}');
        }
        return parent::getPrompt();
    }

    function getOpName() {
        return $this->getRecName(" ");
    }

    public function resolve(): void {
        if ($this->isRangedChoice()) {
            $c = $this->getCheckedArg();
            $this->withDataField("count", $c);
            $this->withDataField("mcount", $c);

            $this->saveToDb();
            return;
        }
    }

    function getOperator() {
        return ",";
    }
}
