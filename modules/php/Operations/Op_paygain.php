<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * fate implementation : © Alena Laskavaia <laskava@gmail.com>
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

use Bga\Games\Fate\Operations\Op_seq;

/** Sequence of operations, no user choice. Usually pay/gain that is shown diffrently then sequence */
class Op_paygain extends Op_seq {
    public function requireConfirmation() {
        return true;
    }
    function getOpName() {
        return $this->getRecName(" ⤇ ");
    }
    function getOperator() {
        return ":";
    }
}
