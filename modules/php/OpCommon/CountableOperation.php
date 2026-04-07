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

namespace Bga\Games\Fate\OpCommon;

abstract class CountableOperation extends Operation {
    const RES_INFINITE = 1000;
    function getRangeMoves() {
        $res = [];
        $count = $this->getCount();
        $mcount = $this->getMinCount();
        if ($mcount == 0) {
            $mcount++;
        }
        if ($mcount == $count) {
            return [
                "$count" => [
                    "name" => "Confirm",
                    "q" => 0,
                ],
            ];
        }
        for ($i = $mcount; $i <= $count; $i++) {
            $res["$i"] = ["q" => 0];
        }
        return $res;
    }

    function getIconicName() {
        if ($this->getCount() == 1) {
            return '${name}';
        }
        return clienttranslate('${name} x ${count}');
    }
    function getPossibleMoves() {
        return $this->getRangeMoves();
    }

    public function getExtraArgs() {
        return ["count" => $this->getCount(), "mcount" => $this->getMinCount(), "name" => $this->getOpName()];
    }

    function getCount() {
        return $this->getDataField("count", 1);
    }

    function getLimitCount() {
        return self::RES_INFINITE;
    }

    function getMinCount() {
        return $this->getDataField("mcount", 1);
    }

    function incMinCount($inc = 1) {
        $v = $this->getDataField("mcount", 1);
        $v += $inc;
        if ($v < 0) {
            $v = 0;
        }
        $this->withDataField("mcount", $v);

        return $v;
    }

    function incCount($inc = 1) {
        $v = $this->getDataField("count", 1);
        $v += $inc;
        if ($v < 0) {
            $v = 0;
        }
        $this->withDataField("count", $v);
        return $v;
    }

    function incCounts($inc = 1) {
        $this->incMinCount($inc);
        return $this->incCount($inc);
    }

    function mulCounts(int $coeff) {
        $max = $this->getDataField("count", 1);
        $min = $this->getDataField("mcount", 1);
        $this->withDataField("count", $max * $coeff);
        $this->withDataField("mcount", $min * $coeff);
    }

    function isRanged() {
        $count = $this->getCount();
        $mcount = $this->getMinCount();
        if ($count == 1 && $mcount == 1) {
            return false;
        }
        return true;
    }

    function isRangedChoice() {
        return $this->getCount() != $this->getMinCount();
    }

    function canSkip() {
        return $this->isOptional();
    }

    function isOptional() {
        return $this->getMinCount() == 0;
    }

    function getTypeFullExpr() {
        $base = parent::getTypeFullExpr();
        if ($this->isRanged()) {
            $min = $this->getMinCount();
            $max = $this->getCount();
            if ($min == $max) {
                return "$min($base)";
            }
            return "[$min,$max]($base)";
        }
        return $base;
    }
}
