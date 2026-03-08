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

namespace Bga\Games\Fate\Db;

use Bga\GameFramework\SystemException;
use Bga\Games\Fate\Game;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\OpCommon\OpExpression;

use Throwable;

/**
 * Class DbMachine to access database called 'machine'
 * That is stack of Operations which is pending player or game actions
 */
class DbMachine {
    protected Game $game;
    public function __construct() {
        $this->game = Game::$instance;
    }
    function _($text) {
        return $this->game->_($text);
    }

    function getTableFields() {
        return ["id", "rank", "type", "owner", "data"];
    }
    // DB OPERATIONS
    final function checkInt($key) {
        if ($key === null || $key === false) {
            throw new SystemException("must be integer number but was null/false");
        }
        if (is_numeric($key)) {
            return (int) $key;
        }
        throw new SystemException("must be integer number");
    }

    final function checkId($key) {
        $id = $this->checkInt($key);
        if ($id <= 0) {
            throw new SystemException("must be positive integer number");
        }
        return $id;
    }

    function gettablearr() {
        $arr = $this->game->getCollectionFromDB("SELECT * from machine WHERE `rank` >= 0 ORDER BY `rank` ASC");
        return array_values($arr);
    }

    final function getId($info, $throw = true) {
        try {
            if ($info instanceof Operation) {
                return $info->getId();
            }
            if (is_array($info)) {
                if (array_key_exists("id", $info)) {
                    return $this->checkId($info["id"]);
                }
                $debug = json_encode($info);
                throw new SystemException("operation structure is not correct: $debug");
            }
            return $this->checkId($info);
        } catch (Throwable $e) {
            if ($throw) {
                throw $e;
            } else {
                return false;
            }
        }
    }

    /**
     * Checks that given array either list of ids or list returned by function such get operations() which is map of
     * id => record
     * throws exception if not of any of this structures, otherwise it returns array of ids
     *
     * @param
     *            $arr
     * @return array of operaton ids
     */
    final function ids($arr) {
        if ($arr === null) {
            throw new SystemException("arr cannot be null");
        }

        if (!is_array($arr)) {
            return [$this->getId($arr)];
        }
        if (count($arr) == 0) {
            return [];
        }
        if (array_key_exists("id", $arr)) {
            $id = $this->getId($arr);
            return [$id];
        }
        $res = [];
        foreach ($arr as $info) {
            $id = $this->getId($info);
            $res[] = $id;
        }
        return $res;
    }
    function getIdsWhereExpr($list) {
        $keys = $this->ids($list);
        $sql = " id IN ('" . implode("','", $keys) . "') ";
        return $sql;
    }

    // Get max on min state on the specific location
    function getExtremeRank(bool $getMax, $owner = null) {
        if ($getMax) {
            $min = "MAX(`rank`) res ";
        } else {
            $min = "MIN(`rank`) res ";
        }
        $andowner = "";
        $andpool = "";
        if ($owner) {
            $andowner = " AND owner = '$owner'";
        }

        $sql = "SELECT $min FROM machine WHERE `rank` > 0 $andowner $andpool";
        $res = $this->game->getUniqueValueFromDB($sql);
        if ($res) {
            return (int) $res;
        } else {
            return 0;
        }
    }

    function getTopRank($owner = null) {
        return $this->getExtremeRank(false, $owner);
    }

    /**
     * Remove operations (its not really removed from db, but rank set to -1)
     */
    function hide($list) {
        $ids = $this->getIdsWhereExpr($list);
        $this->game->DbQuery("UPDATE machine SET `rank` = `rank` - 1 WHERE `rank` < 0");
        $sql = "UPDATE machine SET `rank` = -1 WHERE $ids";
        $this->game->DbQuery($sql);
    }

    function renice($list, $rank) {
        $ids = $this->getIdsWhereExpr($list);
        $this->game->DbQuery("UPDATE machine SET `rank` = $rank WHERE $ids");
    }

    function interrupt(int $from = 0, int $count = 1) {
        $this->game->DbQuery("UPDATE machine SET `rank` = `rank` + $count WHERE `rank` >= $from");
    }

    function normalize() {
        $top = $this->getTopRank();
        if ($top > 1) {
            $this->game->DbQuery("UPDATE machine SET `rank` = `rank` - $top + 1 WHERE `rank` >= $top");
        }
    }

    /**
     * Insert list of records of rank, UNCHECKED fields, must not come from user
     *
     * @param int $rank
     * @param array $list
     * @return []
     */
    function insertList($rank, $list) {
        $res = [];
        foreach ($list as $record) {
            if ($rank !== null) {
                $record["rank"] = $rank;
            }
            $res[] = $this->insertMap($record);
        }
        return $res;
    }

    function insertMap($map) {
        $fields = $this->getTableFields();
        array_shift($fields);
        $flat = [];
        foreach ($fields as $key) {
            $flat[] = $map[$key];
        }

        return $this->dbInsert($fields, $flat);
    }

    private function dbInsert($fields, $values) {
        $sql = "INSERT INTO machine";
        $sql .= " (`" . implode("`,`", $fields) . "`)";
        $sql .= " VALUES ('" . implode("','", $values) . "')";
        $this->game->DbQuery($sql);
        $id = $this->game->DbGetLastId();
        $this->game->systemAssert("Over 100,000", $id < 100000);
        return $id;
    }

    function insertRow($rank, $op) {
        $this->insertList($rank, [$op]);
        $id = $this->game->DbGetLastId();
        return $id;
    }

    function getLastId() {
        $id = $this->game->getUniqueValueFromDB("SELECT MAX(id) from machine");
        return $id;
    }

    function getTopOperations($owner = null) {
        return $this->getOperationsByRank(null, $owner);
    }

    function getOperationsByRank($rank = null, $owner = null) {
        if ($rank === null) {
            $rank = $this->getTopRank($owner);
        }
        $this->checkInt($rank);
        $andowner = "";
        if ($owner !== null) {
            $andowner = " AND owner = '$owner'";
        }
        return $this->game->getCollectionFromDB("SELECT * from machine WHERE `rank` = $rank $andowner");
    }

    function getOperations($owner = null, $type = null) {
        $andowner = "";
        $andtype = "";
        if ($owner !== null) {
            $andowner = " AND owner = '$owner'";
        }
        if ($type !== null) {
            $andtype = " AND type = '$type'";
        }

        return $this->game->getCollectionFromDB("SELECT * from machine WHERE `rank` >= 0 $andowner $andtype ORDER BY `rank` ASC");
    }

    function getHistoricalOperations($owner = null, $type = null) {
        $andowner = "";
        $andtype = "";
        if ($owner !== null) {
            $andowner = " AND owner = '$owner'";
        }
        if ($type !== null) {
            $andtype = " AND type = '$type'";
        }

        return $this->game->getCollectionFromDB("SELECT * from machine WHERE `rank` < 0 $andowner $andtype ORDER BY `rank` ASC");
    }

    function createRow($type, $owner = null, $data = null) {
        // sanity check
        OpExpression::parseExpression($type);

        $record = [
            "type" => $this->escapeStringForDB($type),
            "rank" => 42,
            "owner" => $this->escapeStringForDB($owner),
            "data" => $this->escapeStringForDB($this->fixedJsonEncode($data)),
        ];

        return $record;
    }

    function escapeStringForDB($str) {
        return $this->game->escapeStringForDB($str);
    }

    function fixedJsonEncode($data) {
        $result = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        return $result;
    }
}
