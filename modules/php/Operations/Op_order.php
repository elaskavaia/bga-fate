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
 * fate.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\ComplexOperation;

class Op_order extends ComplexOperation {
    function resolve(): void {
        // this suppose to pick selected operation and push on top of stack, remaing choice if any stored back
        $target = $this->getCheckedArg();
        foreach ($this->delegates as $i => $arg) {
            if ($arg->getOpId() == $target) {
                $this->queue($arg->getTypeFullExpr(), $arg->getOwner(), $arg->getDataForDb());
                $arg->destroy();
                unset($this->delegates[$i]);
                break;
            }
        }
        if (count($this->delegates) > 0) {
            $this->queueRank++;
            $this->saveToDb($this->queueRank, true);
        }

        return;
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
