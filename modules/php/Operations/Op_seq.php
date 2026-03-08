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
        $rank = 1;
        $this->game->machine->interrupt($rank, count($this->delegates));
        $c = $this->getCount();
        foreach ($this->delegates as $sub) {
            $sub->destroy();
            $sub->withDataField("reason", $this->getReason());
            $sub->withDataField("count", $sub->getDataField("count", 1) * $c);
            $sub->withDataField("mcount", $sub->getDataField("mcount", 1) * $c);
            $sub->saveToDb($rank, false);
            $rank++;
        }

        return true;
    }

    function getPossibleMoves() {
        foreach ($this->delegates as $sub) {
            if ($sub->isVoid()) {
                return ["err" => $sub->getError()];
            }
        }
        if ($this->isRangedChoice()) {
            return parent::getRangeMoves();
        }
        if (count($this->delegates) == 0) {
            return [];
        }
        $sub = $this->delegates[0];
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
