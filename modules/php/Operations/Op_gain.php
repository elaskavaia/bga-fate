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

use Bga\Games\Fate\Game;
use Bga\Games\Fate\OpCommon\CountableOperation;

class Op_gain extends CountableOperation {
    function resolve(): void {
        $count = $this->getCheckedArg();
        //$this->game->systemAssert("missing reason", $this->getReason());
        $this->game->effect_incCount($this->getOwner(), $this->getType(), (int) $count, $this->getReason());

        return;
    }

    function getPrompt() {
        if ($this->isRangedChoice()) {
            $max = $this->getCount();
            if ($max > 1) {
                return clienttranslate('Select how many times to gain ${token_div}');
            }
            return clienttranslate('Gain ${token_div}');
        }
        return parent::getPrompt();
    }

    public function getExtraArgs() {
        $tracker_id = $this->game->tokens->getTrackerId($this->getOwner(), $this->getType());
        return parent::getExtraArgs() + ["token_div" => $tracker_id, "token_id" => $tracker_id];
    }

    function getIconicName() {
        $count = $this->getCount();
        if ($count == 1) {
            return '${token_div}';
        } elseif ($count == 2) {
            return '${token_div} ${token_div}';
        } elseif ($count == 3) {
            return '${token_div} ${token_div} ${token_div}';
        }
        return clienttranslate('${count} ${token_div}');
    }
}
