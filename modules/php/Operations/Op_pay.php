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

use Bga\Games\Fate\OpCommon\CountableOperation;
use Bga\Games\Fate\Material;

class Op_pay extends CountableOperation {
    function getResType() {
        $type = $this->getType();
        return substr($type, 2); // n_XYZ -> XYZ
    }

    function getLimitCount() {
        $owner = $this->getOwner();
        $current = $this->game->tokens->getTrackerValue($owner, $this->getResType());
        return $current;
    }

    function getPossibleMoves() {
        $owner = $this->getOwner();
        $current = $this->game->tokens->getTrackerValue($owner, $this->getResType());
        if ($current < $this->getCount()) {
            return ["q" => Material::ERR_COST];
        }
        return parent::getPossibleMoves();
    }

    public function auto(): bool {
        if ($this->getCount() == 0) {
            $this->game->effect_incCount($this->getOwner(), $this->getResType(), 0, $this->getReason());
            return true;
        }
        return parent::auto();
    }

    function resolve(): void {
        $this->checkVoid(); //validation
        $count = $this->getCount();
        $this->game->effect_incCount($this->getOwner(), $this->getResType(), -$count, $this->getReason());
        return;
    }

    function getIconicName() {
        $count = $this->getCount();
        $type = $this->getResType();
        if ($count == 1) {
            return "[wicon_$type]";
        } elseif ($count == 2) {
            return "[wicon_$type][wicon_$type]";
        } elseif ($count == 3) {
            return "[wicon_$type][wicon_$type][wicon_$type]";
        }
        return clienttranslate('${count} x ' . "[wicon_$type]");
    }

    public function getPrompt() {
        $type = $this->getResType();
        return clienttranslate('Pay ${count} ' . "[wicon_$type]");
    }
}
