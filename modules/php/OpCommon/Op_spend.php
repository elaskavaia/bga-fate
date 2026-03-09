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
 * Fate.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate\OpCommon;

use Bga\Games\Fate\OpCommon\CountableOperation;
use Bga\Games\Fate\Material;

abstract class Op_spend extends CountableOperation {
    function getResType() {
        $type = $this->getType();
        return substr($type, 5); // spendXYZ -> XYZ
    }

    function getLimitCount() {
        $current = $this->getCurrentValue($this->getResType());
        return $current;
    }

    function getPossibleMoves() {
        $current = $this->getCurrentValue($this->getResType());
        if ($current < $this->getCount()) {
            return ["q" => Material::ERR_COST];
        }
        return parent::getPossibleMoves();
    }

    abstract function getCurrentValue(string $restype);
    abstract function effectSpend(int $count);

    public function auto(): bool {
        if ($this->getCount() == 0) {
            $this->effectSpend(0);
            return true;
        }
        return parent::auto();
    }

    function resolve(): void {
        $this->checkVoid(); //validation
        $count = $this->getCount();
        $this->effectSpend($count);
    }

    function getIconicName() {
        $count = $this->getCount();
        $type = $this->getResType();
        if ($count <= 3) {
            return str_repeat("[wicon_$type]", $count);
        }
        return "\${count} x [wicon_$type]";
    }

    public function getExtraArgs() {
        $type = $this->getResType();
        return parent::getExtraArgs() + ["token_div" => "[wicon_$type]"];
    }

    public function getPrompt() {
        return clienttranslate('Spend ${count} ${token_div}');
    }
}
