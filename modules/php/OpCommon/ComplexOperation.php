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

use Bga\Games\Fate\Material;

abstract class ComplexOperation extends CountableOperation {
    /** @var Operation[] */
    public array $delegates = [];

    function withData(mixed $data, bool $merge = false) {
        parent::withData($data, $merge);
        foreach ($this->delegates as $sub) {
            $sub->withData($data, true); // always preserve sub-op counts
        }
        return $this;
    }

    function getDataForDb() {
        $data = $this->getData() ?? [];
        $data["args"] = [];
        foreach ($this->delegates as $i => $sub) {
            $data["args"][$i] = ["type" => $sub->getType(), "data" => $sub->getDataForDb(), "owner" => $sub->getOwner()];
        }
        return $data;
    }
    function getDataForDbWithCounts(mixed $count = 1, mixed $mcount = null) {
        if ($mcount == null) {
            $mcount = $count;
        }
        $data = $this->getDataForDb();
        $data["count"] = $count;
        $data["mcount"] = $mcount;
        return $data;
    }

    function canSkip() {
        if (count($this->delegates) == 0) {
            return true;
        }
        return parent::canSkip();
    }

    function withDelegate(Operation $sub) {
        $this->delegates[] = $sub;
        $sub->withData($this->getData(), true);
        return $this;
    }

    function paramInfo(Operation $sub) {
        $err = "";
        if ($sub->isVoid()) {
            $err = $sub->getError();
        }
        $q = 0;
        $max = 0;
        if ($err) {
            $q = Material::ERR_NOT_APPLICABLE;
        } elseif ($sub instanceof CountableOperation) {
            $count = $this->getCount();
            $limit = $sub->getLimitCount();
            $max = min($count, $limit);
        } else {
            $max = 1000;
        }

        $args = $sub->getExtraArgs();
        $res = [
            "name" => $sub->getIconicName(),
            "args" => $args,
            "err" => $err,
            "r" => $sub->getTypeFullExpr(),
            "q" => $q,
            "max" => $max,
            "tooltip" => $sub->getOpName(),
        ];
        return $res;
    }

    function getPossibleMoves() {
        if ($this->isRangedChoice()) {
            return parent::getPossibleMoves();
        }
        $res = [];
        foreach ($this->delegates as $sub) {
            $res[$sub->getOpId()] = $this->paramInfo($sub);
        }
        return $res;
    }

    function isSubTrancient() {
        foreach ($this->delegates as $sub) {
            if ($sub->isTrancient()) {
                return true;
            }
        }
        return false;
    }

    function getRecName($join) {
        $args = [];
        $pars = [];
        if (count($this->delegates) == 0) {
            return $this->game->getRulesFor($this->getOpId(), "name", "?");
        }
        foreach ($this->delegates as $i => $sub) {
            $pars[] = "p$i";
            $args["p$i"] = ["log" => $sub->getIconicName(), "args" => $sub->getExtraArgs()];
        }
        $log = implode(
            $join,
            array_map(function ($a) {
                return '${' . $a . "}";
            }, $pars)
        );

        return ["log" => $log, "args" => $args];
    }

    function getTypeFullExpr() {
        $op = $this->getOperator();

        $opcount = count($this->delegates);
        if ($opcount == 1) {
            $base = static::str($this->delegates[0]);
        } elseif ($opcount == 0) {
            $base = "0";
        } else {
            $res = static::str($this->delegates[0], $op);
            for ($i = 1; $i < $opcount; $i++) {
                $res .= $op . static::str($this->delegates[$i], $op);
            }
            $base = $res;
        }
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
