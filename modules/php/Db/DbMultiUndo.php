<?php

declare(strict_types=1);

namespace Bga\Games\Fate\Db;

use Bga\GameFramework\UserException;
use Bga\Games\Fate\Game;

/*
 * This is a generic class to manage multi-step undo.
 *
 *
 *
CREATE TABLE IF NOT EXISTS `multiundo` (
  `move_id` int(10) NOT NULL,
  `player_id` int(10) NOT NULL,
  `data` mediumtext NOT NULL,
  `meta` mediumtext NOT NULL,
  PRIMARY KEY (`move_id`, `player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

 *
 *
 */

class DbMultiUndo {
    var $table;
    public Game $game; // game ref

    function __construct(Game $game, private $customRestoreHook = null) {
        $this->table = "multiundo";
        $this->game = $game;
    }

    function getSelectQuery() {
        $sql = "SELECT * FROM " . $this->table;
        return $sql;
    }

    function setMoveSnapshot(int $move_id, int $player_id, array $data, array $meta = []) {
        $meta = $meta + ["version" => 1];
        $json_data = $this->game->escapeStringForDB(fixedJsonEncode($data));
        $json_meta = $this->game->escapeStringForDB(fixedJsonEncode($meta));

        $table = $this->table;
        $hasmove = $this->game->getUniqueValueFromDB("SELECT `move_id` FROM $table WHERE `move_id`='$move_id' AND player_id='$player_id'");
        if ($hasmove) {
            $sql = "UPDATE " . $this->table;
            $sql .= " SET data='$json_data', meta='$json_meta'";
            $sql .= " WHERE move_id='$move_id' AND player_id='$player_id'";
            $this->game->DbQuery($sql);
        } else {
            $sql = "INSERT INTO " . $this->table . " (move_id,player_id,data,meta)";
            $sql .= " VALUES ('$move_id','$player_id', '$json_data', '$json_meta')";
            $this->game->DbQuery($sql);
        }
    }

    function doSaveUndoSnapshot(array $meta, int $player_id, bool $notify = false) {
        $move_id = $this->getNextMoveId() - 1; // this is called after notify so the stored move id is previous

        $barrier = $meta["barrier"] ?? 0;

        //$this->game->setGameStateValue("undo_moves_player", $player_id);
        $data_all = $this->getCurrentTablesAsObject();

        $this->clearSnapshotsAfter($move_id, $player_id);
        if ($barrier) {
            $this->clearSnapshotsBefore($move_id, $player_id);
        }
        if ($player_id == 0 || $player_id == 1 || $barrier == 2) {
            // basically with player_id = 0 it will clear all tables
            return;
        }

        $this->setMoveSnapshot($move_id, $player_id, $data_all, $meta);

        if ($notify) {
            $this->notifyUndoMove($move_id, $player_id);
        }
    }

    function getNextMoveId() {
        //getGameStateValue does not work when dealing with undo, have to read directly from db
        $next_move_index = 3;
        $subsql = "SELECT global_value FROM global WHERE global_id='$next_move_index' ";
        $move_id = $this->game->getUniqueValueFromDB($subsql);
        return (int) $move_id;
    }

    function notifyUndoMove(int $move_id, int $player_id) {
        $this->game->systemAssert("Missing player_id in notifyUndoMove", $player_id);
        $meta = $this->getMetaForMove($move_id, $player_id, true);
        $this->notifyUndoMoveMeta($meta ?? []);
    }

    function notifyUndoMoveMeta($meta) {
        $this->game->notify->all(
            "undoMove",
            "",
            //'${player_name} undoinfo ${label} ${undo_button} ${barrier}',
            $meta
        );
    }

    function clearSnapshotsAfter(int $move_id, int $player_id) {
        $undotable = $this->table;
        if ($player_id == 0) {
            $this->game->DbQuery("DELETE FROM $undotable WHERE `move_id` >= $move_id");
        } else {
            $this->game->DbQuery("DELETE FROM $undotable WHERE `move_id` >= $move_id AND `player_id` = $player_id");
        }
    }
    function clearSnapshotsBefore(int $move_id, int $player_id) {
        $undotable = $this->table;
        if ($player_id == 0) {
            $this->game->DbQuery("DELETE FROM $undotable WHERE `move_id` < $move_id");
        } else {
            $this->game->DbQuery("DELETE FROM $undotable WHERE `move_id` < $move_id AND `player_id` = $player_id");
        }
    }

    function deleteGamelogs(int $move_id) {
        $packet_id = $this->game->getUniqueValueFromDB("SELECT MIN(`gamelog_packet_id`) FROM gamelog WHERE `gamelog_move_id` > $move_id");
        if (!$packet_id) {
            return;
        }

        $this->game->DbQuery("DELETE FROM gamelog WHERE `gamelog_packet_id` >= $packet_id AND `gamelog_private` != 1");
    }

    function getLatestSavedMoveId(int $before, int $player_id) {
        $undotable = $this->table;

        return $this->game->getUniqueValueFromDB(
            "SELECT MAX(`move_id`) FROM $undotable WHERE `move_id` < $before AND `player_id` = $player_id"
        );
    }

    function getEarliestSavedMoveId(int $player_id) {
        $undotable = $this->table;

        return $this->game->getUniqueValueFromDB("SELECT MIN(`move_id`) FROM $undotable WHERE `player_id` = $player_id");
    }

    function getAvailableUndoMoves(int $player_id) {
        $moves = [];
        $undotable = $this->table;
        $all = $this->game->getObjectListFromDB("SELECT `move_id`,`player_id`,`meta` FROM $undotable WHERE `player_id` = $player_id");

        foreach ($all as $row) {
            $value = $row["meta"];
            $res = json_decode($value, true);
            $res["player_id"] = $row["player_id"];
            $res["move_id"] = $row["move_id"];
            $moves[$row["move_id"]] = $res;
        }
        return $moves;
    }

    function errorCannotUndo(int $move_id = 0) {
        if ($move_id == 0) {
            throw new UserException(clienttranslate("Nothing to undo"));
        } else {
            throw new UserException(clienttranslate('Nothing to undo for move ${move_id}'), ["move_id" => $move_id]);
        }
    }

    /** Replace custom undo data of move_id into real tables */
    function doReplaceUndoSnapshot(int $move_id, int $player_id) {
        //$this->warn("restoring to move $move_id ($current)|");

        $tables = $this->game->getObjectListFromDB("SHOW TABLES", true);
        $saved = $this->getMoveSnapshotDataJson($move_id, $player_id);
        $meta = $this->getMetaForMove($move_id, $player_id, true);

        if (!$saved) {
            $this->errorCannotUndo($move_id);
        }
        if (count($saved) == 0) {
            $this->errorCannotUndo($move_id);
        }

        foreach ($tables as $table) {
            $saved_data = $saved[$table] ?? [];
            if ($this->customRestoreHook) {
                $method = $this->customRestoreHook;
                $this->game->$method($table, $saved_data, $meta);
            } elseif ($this->needsRestoring($table)) {
                $this->game->DbQuery("DELETE FROM $table");
                $this->dbInsertValues($table, $saved_data);
            }
        }
    }

    function needsSaving(string $table) {
        if ($table == "token" || $table == "machine" || $table == "stats") {
            return true;
        }
        return false;
    }

    function needsRestoring(string $table) {
        return $this->needsSaving($table);
    }

    function getMoveSnapshotDataJson(int $move_id, int $player_id) {
        $row = $this->getMoveSnapshot($move_id, $player_id);
        if ($row == null) {
            return null;
        }
        $value = $row["data"];
        return json_decode($value, true);
    }

    function getMetaForMove(int $move_id, int $player_id, $extra = false) {
        $row = $this->getMoveSnapshot($move_id, $player_id);
        if ($row == null) {
            return null;
        }
        $value = $row["meta"];
        $res = json_decode($value, true);
        if ($extra) {
            $res["player_id"] = $player_id;
            $res["move_id"] = $move_id;
        }
        return $res;
    }

    function getCurrentTablesAsObject() {
        $tables = $this->game->getObjectListFromDB("SHOW TABLES", true);
        $data_all = [];

        foreach ($tables as $table) {
            if ($this->needsSaving($table)) {
                $datatable = $this->game->getObjectListFromDB("SELECT * from $table");
                $data_all[$table] = $datatable;
            }
        }
        return $data_all;
    }

    function getMoveSnapshot(int $move_id, int $player_id) {
        $sql = $this->getSelectQuery();
        $sql .= " WHERE move_id='$move_id' AND player_id='$player_id'";
        $dbres = $this->game->getObjectListFromDB($sql);
        return reset($dbres);
    }

    function undoRestorePoint(int $player_id = 0, int $move_id = 0, bool $partial = false) {
        if ($player_id === 0) {
            $player_id = (int) $this->game->getCurrentPlayerId(true);
        }
        if (!$this->game->gamestate->isPlayerActive($player_id)) {
            $this->game->userAssert(clienttranslate("Only active player can Undo"));
        }
        $next = $this->getNextMoveId();

        if ($move_id == 0) {
            $move_id = (int) $this->getEarliestSavedMoveId($player_id);
        } elseif ($move_id == -1) {
            $latest_saved_move_id = (int) $this->getLatestSavedMoveId($next + 1, $player_id);
            $prev = (int) $this->getLatestSavedMoveId($latest_saved_move_id, $player_id);
            if (!$prev) {
                $prev = (int) $this->getEarliestSavedMoveId($player_id);
            }
            $move_id = $prev;
        }
        if (!$move_id) {
            $this->errorCannotUndo();
        }

        // if ($move_id >= $next - 1) {
        //     $this->errorCannotUndo($move_id);
        // }
        $meta = $this->getMetaForMove($move_id, $player_id, true);

        $save_player_id = $meta["player_id"] ?? 0;
        if ($player_id != $save_player_id && $save_player_id != 0) {
            $this->game->userAssert(clienttranslate("Stored Undo data belongs to other player"));
        }

        //$this->game->not_a_move_notification = false;

        $this->doReplaceUndoSnapshot($move_id, $player_id);

        $this->game->setUndoSavepoint(false); // unset it because it was set by bga undo
        //$this->dbMultiUndo->doSaveUndoSnapshot(["barrier" => $barrier, "label" => $label], $player_id, true);
        $this->clearSnapshotsAfter($move_id, $player_id);
        $this->deleteGamelogs($move_id);
        //$this->rewriteHistory($move_id, $next, $player_id);
        //$this->game->setGameStateValue("next_move_id", $next);

        $this->game->notifyWithName(
            "undoRestore",
            clienttranslate('${player_name} undoes moves ${last_move} - ${undo_move_prev}'),
            [
                "last_move" => $next - 1,
                "undo_move" => $move_id,
                "undo_move_prev" => $move_id + 1,
            ],
            $player_id
        );

        $this->notifyUndoMoveMeta($meta);
    }

    protected static function extractNotifIds($notifications) {
        $notificationUIds = [];
        foreach ($notifications as $packet) {
            $data = \json_decode($packet["gamelog_notification"], true);
            foreach ($data as $notification) {
                array_push($notificationUIds, $notification["uid"]);
            }
        }
        return $notificationUIds;
    }

    function dbInsertValues($table, $values) {
        if (count($values) == 0) {
            return;
        }
        $fields_list = $this->game->dbGetFieldList($table);
        $seqvalues = [];
        foreach ($values as $row) {
            $quoted = [];
            foreach ($fields_list as $field) {
                $value = $row[$field] ?? null;
                if ($value === null) {
                    $quoted[] = "NULL";
                } elseif (is_numeric($value)) {
                    $quoted[] = "$value";
                } else {
                    $value = $this->game->escapeStringForDB($value);
                    $quoted[] = "'$value'";
                }
            }
            $seqvalues[] = "( " . implode(",", $quoted) . " )";
        }
        $fields = "`" . implode("`,`", $fields_list) . "`";
        $sql = "INSERT INTO $table ($fields)";
        $sql .= " VALUES " . implode(",", $seqvalues);
        $this->game->DbQuery($sql);
    }
}

function convertToUtf8($d) {
    if (is_array($d)) {
        foreach ($d as $k => $v) {
            $d[$k] = convertToUtf8($v);
            // if ($d[$k] != $v) {
            //     throw new UserException("trip point $v $k ($d[$k])");
            // }
        }
    } elseif (is_object($d)) {
        foreach ($d as $k => $v) {
            $d->$k = convertToUtf8($v);
        }
    } else {
        return mb_convert_encoding($d, "UTF-8", "ISO-8859-1");
    }

    return $d;
}

function fixedJsonEncode($data) {
    $result = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    if ($result === false) {
        $data = convertToUtf8($data);
        $result = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    }
    if ($result === false) {
        throw new \Exception("json error " . json_last_error());
    }
    return $result;
}
