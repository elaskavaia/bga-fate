<?php

declare(strict_types=1);

namespace Bga\Games\Fate\Stubs;

use Bga\Games\Fate\Db\DbMultiUndo;

/**
 * In-memory undo store for tests and the harness.
 *
 * Keeps the real logic of DbMultiUndo (barrier clearing, move-id math, player checks,
 * error paths) and replaces only the SQL storage: snapshots live in an array and hold
 * the in-memory token + machine tables instead of a copy of every DB table.
 *
 * The move counter stands in for BGA's `next_move_id` global, which advances once per
 * request when notifications flush - GameWrapper::sendNotifications() advances it here.
 */
class MultiUndoInMem extends DbMultiUndo {
    /** [move_id][player_id] => ["data" => json, "meta" => json], mirroring a `multiundo` row */
    private array $snapshots = [];
    private int $nextMoveId = 1;

    function advanceMove(): void {
        $this->nextMoveId++;
    }

    function getNextMoveId() {
        return $this->nextMoveId;
    }

    function setMoveSnapshot(int $move_id, int $player_id, array $data, array $meta = []) {
        $this->snapshots[$move_id][$player_id] = [
            "data" => json_encode($data),
            "meta" => json_encode($meta + ["version" => 1]),
        ];
    }

    function getMoveSnapshot(int $move_id, int $player_id) {
        return $this->snapshots[$move_id][$player_id] ?? null;
    }

    function clearSnapshotsAfter(int $move_id, int $player_id) {
        $this->forget(fn(int $id) => $id >= $move_id, $player_id);
    }

    function clearSnapshotsBefore(int $move_id, int $player_id) {
        $this->forget(fn(int $id) => $id < $move_id, $player_id);
    }

    private function forget(callable $matches, int $player_id): void {
        foreach ($this->snapshots as $id => $byPlayer) {
            if (!$matches($id)) {
                continue;
            }
            if ($player_id == 0) {
                unset($this->snapshots[$id]);
            } else {
                unset($this->snapshots[$id][$player_id]);
                if (!$this->snapshots[$id]) {
                    unset($this->snapshots[$id]);
                }
            }
        }
    }

    function getLatestSavedMoveId(int $before, int $player_id) {
        $ids = array_filter($this->savedMoveIds($player_id), fn(int $id) => $id < $before);
        return $ids ? max($ids) : null;
    }

    function getEarliestSavedMoveId(int $player_id) {
        $ids = $this->savedMoveIds($player_id);
        return $ids ? min($ids) : null;
    }

    /** @return int[] */
    private function savedMoveIds(int $player_id): array {
        return array_keys(array_filter($this->snapshots, fn(array $byPlayer) => isset($byPlayer[$player_id])));
    }

    function getAvailableUndoMoves(int $player_id) {
        $moves = [];
        foreach ($this->savedMoveIds($player_id) as $id) {
            $moves[$id] = $this->getMetaForMove($id, $player_id, true);
        }
        return $moves;
    }

    function getCurrentTablesAsObject() {
        return [
            "token" => $this->game->tokens->toJson(),
            "machine" => $this->game->machine->db->toJson(),
        ];
    }

    function doReplaceUndoSnapshot(int $move_id, int $player_id) {
        $saved = $this->getMoveSnapshotDataJson($move_id, $player_id);
        if (!$saved) {
            $this->errorCannotUndo($move_id);
        }
        $this->game->tokens->deleteAll();
        $this->game->tokens->fromJson($saved["token"] ?? []);
        $this->game->machine->db->fromJson($saved["machine"] ?? []);
        $this->game->hexMap->invalidateOccupancy();
    }

    /** No gamelog table in memory, and the harness keeps its notification list as-is. */
    function deleteGamelogs(int $move_id) {}
}
