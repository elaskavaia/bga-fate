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
use Bga\Games\Fate\OpCommon\ComplexOperation;
use Bga\Games\Fate\OpCommon\CountableOperation;
use Bga\Games\Fate\OpCommon\Operation;

/** User choses operation. If count is used it is shared and decreases for all choices */
class Op_or extends ComplexOperation {
    function resolve(): void {
        $res = $this->getCheckedArg();
        if (!is_array($res)) {
            $res = [$res => 1];
        }
        $total = 0;
        $count = $this->getCount();
        $minCount = $this->getMinCount();
        foreach ($this->delegates as $i => $sub) {
            $key = "choice_$i";
            $c = $res[$key] ?? 0; // user selects the count of sub operation
            $total += $c;
            if ($c > 0) {
                $max = $sub->getDataField("count", 1);
                $min = $sub->getDataField("mcount", 1);
                $sub->withData($this->getData(), true); // get all data from parent
                // now override count
                $sub->withDataField("count", $max * $c);
                $sub->withDataField("mcount", $min * $c);
                // save
                $this->queueOp($sub);

                // Reset delegate counts so serialization stays clean if saved again
                $sub->withDataField("count", $max);
                $sub->withDataField("mcount", $min);

                //$this->notifyMessage(clienttranslate('${player_name} selected ${opname}'), ["opname" => $arg->getOpName()]);
                $this->incMinCount(-$c);
                $this->incCount(-$c);
            }
            $sub->destroy(); // this destroys this in db, but it will saved again when parent saves its state
        }

        if ($total > $count) {
            $this->game->userAssert(clienttranslate("Cannot use this action because superfluous amount of elements selected"));
        }

        if ($this->getCount() > 0) {
            $this->queueOp($this);
        }
        return;
    }

    function getPossibleMoves() {
        $res = [];
        $totalLimit = 0;
        foreach ($this->delegates as $i => $sub) {
            $sub->withData($this->getData(), true); // propagate parent data to sub
            $arg = $this->paramInfo($sub);
            $totalLimit += $arg["max"] ?? 0;
            $res["choice_$i"] = $arg;
        }
        if ($totalLimit < $this->getMinCount()) {
            return ["q" => Material::ERR_COST];
        }
        return $res;
    }

    function getArgType() {
        if ($this->getCount() > 1) {
            return Operation::TTYPE_TOKEN_COUNT;
        }
        return Operation::TTYPE_TOKEN;
    }

    function getPrompt() {
        if ($this->getCount() > 1) {
            return clienttranslate('Choose one of the options (count: ${count})');
        }
        return clienttranslate("Choose one of the options");
    }

    function getDescription() {
        return clienttranslate('${actplayer} chooses one of the options');
    }
    function getOpName() {
        return $this->getRecName(" / ");
    }

    function getOperator() {
        return "/";
    }
}
