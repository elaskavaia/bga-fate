<?php

declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\Operation;

/**
 * counter(expr[,min[,max]]) — Evaluate an expression and set the result as count/mcount
 * on the next operation on the stack. Used to dynamically compute operation counts
 * at resolve time (e.g. "spend 1-3 mana: deal that much damage").
 *
 * Params:
 * - param(0): expression to evaluate for count
 * - param(1): expression for minimum count (defaults to count if empty)
 * - param(2): hard max cap - number - not evaluated (optional)
 * Data field "card": context card ID passed to evaluateExpression
 */
class Op_counter extends Operation {
    function getContext(): ?string {
        return $this->getDataField("card");
    }

    function evaluate() {
        $owner = $this->getOwner();
        $expr = $this->getParam(0);
        $min = $this->getParam(1, "");
        $max = $this->getParam(2, "");
        if ($min === "null") {
            $min = "";
        }

        $count = $this->game->evaluateExpression(trim($expr), $owner, $this->getContext());
        $this->game->systemAssert("ERR:counter:notNumeric:$expr=$count", is_numeric($count));

        if ($max) {
            $maxcount = (int) $max;
            if ($count > $maxcount) {
                $count = $maxcount;
            }
        }

        $mincount = $min ? $this->game->evaluateExpression(trim($min), $owner, $this->getContext()) : $count;
        $this->game->systemAssert("ERR:counter:notNumeric:$min=$mincount", is_numeric($mincount));

        return [$count, $mincount];
    }

    function resolve(): void {
        // counter function, followed by expression
        // result of expression is set as counter for top rank operation
        list($count, $mincount) = $this->evaluate();
        //$this->game->debugLog("-evaluted to $count:$mincount");
        $this->destroy(); // cannot be part of top
        $tops = $this->game->machine->getTopOperations($this->getOwner());
        $top = array_shift($tops);
        $this->game->systemAssert("ERR:counter:noNextOp", $top);
        $this->game->machine->setCounts($top, $count, $mincount);
    }
}
