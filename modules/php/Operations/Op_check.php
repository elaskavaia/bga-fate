<?php

declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

/**
 * check(expr[,min[,max]]) — Same as counter but silent fail. I.e. if count is 0 we just remove next operation from stack
 */
class Op_check extends Op_counter {
    function getContext(): ?string {
        return $this->getDataField("card");
    }

    public function getPossibleMoves() {
        return ["confirm"];
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
        if ($count == 0 && $mincount == 0) {
            $this->game->machine->hide($top["id"]);
        } else {
            $this->game->machine->setCounts($top, $count, $mincount);
        }
    }
}
