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

namespace Bga\Games\Fate\Common;

use Bga\Games\Fate\Game;

use function Bga\Games\Fate\getPart;

/**
 * Hex map helper: adjacency, distance, pathfinding, and terrain queries.
 */
class HexMap {
    public function __construct(private Game $game) {}

    function getHexCoords(string $hexId): array {
        $q = (int) getPart($hexId, 1, true);
        $r = (int) getPart($hexId, 2, true);
        return [$q, $r];
    }

    function getAdjacentHexes(string $hexId): array {
        [$q, $r] = $this->getHexCoords($hexId);
        // Axial hex directions (pointy-top)
        $dirs = [[1, 0], [-1, 0], [0, 1], [0, -1], [1, -1], [-1, 1]];
        $result = [];
        foreach ($dirs as [$dq, $dr]) {
            $nId = "hex_" . ($q + $dq) . "_" . ($r + $dr);
            if ($this->isValidHex($nId)) {
                $result[] = $nId;
            }
        }
        return $result;
    }

    function isValidHex(?string $hexId): bool {
        if (!$hexId) {
            return false;
        }
        if (!str_starts_with($hexId, "hex")) {
            return false;
        }
        return $this->game->material->getRulesFor($hexId, "*", null) !== null;
    }

    function getHexTerrain(string $hexId): string {
        return $this->game->material->getRulesFor($hexId, "terrain", "");
    }

    function getHexNamedLocation(string $hexId): string {
        return $this->game->material->getRulesFor($hexId, "loc", "");
    }

    function isInGrimheim(string $hexId): bool {
        return $this->getHexNamedLocation($hexId) === "Grimheim";
    }

    function getHexesInGrimheim(): array {
        $grimhelmHexes = [];
        foreach (array_keys($this->game->material->getTokensWithPrefix("hex")) as $key) {
            if ($this->isInGrimheim($key)) {
                $grimhelmHexes[] = $key;
            }
        }
        return $grimhelmHexes;
    }

    function getHexDistance(string $hexId, string $otherHexId): int {
        [$q1, $r1] = $this->getHexCoords($hexId);
        [$q2, $r2] = $this->getHexCoords($otherHexId);
        $dq = $q1 - $q2;
        $dr = $r1 - $r2;
        return (int) ((abs($dq) + abs($dr) + abs($dq + $dr)) / 2);
    }

    function getMoveDistance(string $hexId, string $otherHexId): int {
        if (!$this->isValidHex($hexId) || !$this->isValidHex($otherHexId)) {
            return -1;
        }
        $g1 = $this->isInGrimheim($hexId);
        $g2 = $this->isInGrimheim($otherHexId);
        if ($g1 && $g2) {
            return 0;
        }
        if ($g1 || $g2) {
            return $this->getMoveDistanceFromGrimheim($g1 ? $otherHexId : $hexId);
        }
        return $this->getHexDistance($hexId, $otherHexId);
    }

    function getMoveDistanceFromGrimheim(string $hexId): int {
        $grimhelmHexes = $this->getHexesInGrimheim();
        $min = PHP_INT_MAX;
        foreach ($grimhelmHexes as $gHex) {
            $d = $this->getHexDistance($hexId, $gHex);
            if ($d < $min) {
                $min = $d;
            }
        }
        return $min;
    }

    /**
     * Returns all hex IDs belonging to a named location, sorted left-to-right, top-to-bottom
     * (by y ascending, then x ascending).
     */
    function getHexesInLocation(string $locName): array {
        $hexes = [];
        foreach (array_keys($this->game->material->getTokensWithPrefix("hex")) as $key) {
            if ($this->getHexNamedLocation($key) === $locName) {
                $hexes[] = $key;
            }
        }
        usort($hexes, function (string $a, string $b) {
            [$ax, $ay] = $this->getHexCoords($a);
            [$bx, $by] = $this->getHexCoords($b);
            if ($ay !== $by) {
                return $ay - $by;
            }
            return $ax - $bx;
        });
        return $hexes;
    }

    /** @var array<string, int>|null cached distance map to Grimheim */
    private ?array $distMapToGrimheim = null;

    /**
     * BFS from Grimheim outward: returns distance-to-Grimheim for every reachable hex.
     * Only considers terrain passability for monsters (lakes block, mountains are fine).
     * Ignores token occupancy — the caller decides what to do with blocked hexes.
     * Cached — terrain doesn't change during the game.
     * @return array<string, int> hex => distance from Grimheim (0 for Grimheim hexes)
     */
    function getDistanceMapToGrimheim(): array {
        if ($this->distMapToGrimheim !== null) {
            return $this->distMapToGrimheim;
        }
        $dist = [];
        $queue = [];
        // Seed all Grimheim hexes at distance 0
        foreach ($this->getHexesInGrimheim() as $gHex) {
            $dist[$gHex] = 0;
            $queue[] = [$gHex, 0];
        }
        while ($queue) {
            [$current, $steps] = array_shift($queue);
            foreach ($this->getAdjacentHexes($current) as $neighbor) {
                if (isset($dist[$neighbor])) {
                    continue;
                }

                if ($this->isImpassable($neighbor)) {
                    continue;
                }
                $dist[$neighbor] = $steps + 1;
                $queue[] = [$neighbor, $steps + 1];
            }
        }
        $this->distMapToGrimheim = $dist;
        return $dist;
    }

    function isImpassable(string $hexId, string $characterType = "monster") {
        $terrain = $this->getHexTerrain($hexId);
        if ($terrain === "lake") {
            return true;
        }
        if ($terrain === "mountain" && $characterType === "hero") {
            return true;
        }
        return false;
    }

    /**
     * Returns the next hex a monster on $hexId should move to on the shortest path toward Grimheim.
     * If monster is already in Grimheim, returns null.
     * If no path exists (surrounded by lakes), returns null.
     * Picks the adjacent hex with the smallest distance to Grimheim, breaking ties by hex id.
     * Does NOT check occupancy — caller should handle blocked moves.
     */
    function getMonsterNextHex(string $hexId): ?string {
        if ($this->isInGrimheim($hexId)) {
            return null; // already there
        }
        $distMap = $this->getDistanceMapToGrimheim();
        $bestHex = null;
        $bestDist = $distMap[$hexId] ?? PHP_INT_MAX;
        foreach ($this->getAdjacentHexes($hexId) as $neighbor) {
            $nd = $distMap[$neighbor] ?? PHP_INT_MAX;
            if ($nd < $bestDist || ($nd === $bestDist && $bestHex !== null && $neighbor < $bestHex)) {
                $bestDist = $nd;
                $bestHex = $neighbor;
            }
        }
        return $bestHex;
    }

    // -------------------------------------------------------------------------
    // Occupancy-dependent queries (may trigger lazy DB load)
    // -------------------------------------------------------------------------
    /** @var array<string, array{character: string|null, stuff: string[]}> | null  hex => occupancy info */
    private ?array $map = null;
    /**
     * Invalidate the occupancy cache. Call when tokens are moved outside of HexMap.
     */
    function invalidateOccupancy(): void {
        $this->map = null;
    }

    /**
     * Find which hex a token currently occupies in the occupancy cache.
     * @return string|null hex id, or null if the token is not on the map
     */
    function getCharacterHex(string $tokenId): ?string {
        $occ = $this->getOccupancyMap();
        foreach ($occ as $hex => $entry) {
            if ($entry["character"] === $tokenId) {
                return $hex;
            }
        }
        return null;
    }

    function moveStuff(string $tokenId, string $location, string $message = "*") {
        $this->game->tokens->dbSetTokenLocation($tokenId, $location, 0, $message);
        $this->moveStuffOnMap($tokenId, $location);
    }

    /**
     * Update the occupancy cache for a token. Automatically removes the token from its current hex.
     * @param string $tokenId The token to move
     * @param string|null $hex Target hex, or null to remove from map entirely
     */
    function moveCharacterOnMap(string $tokenId, ?string $hex): void {
        $fromHex = $this->getCharacterHex($tokenId);
        // Remove from current hex
        if ($fromHex !== null) {
            $this->map[$fromHex]["character"] = null;
        }
        // Place on new hex
        if ($this->isValidHex($hex)) {
            $characterType = getPart($tokenId, 0);
            if ($characterType === "hero" || $characterType === "monster") {
                $this->game->systemAssert("collission on $hex", $this->map[$hex]["character"] == null);
                $this->map[$hex]["character"] = $tokenId;
            } else {
                $this->game->systemAssert("$tokenId is not a character");
            }
        }
    }

    private function moveStuffOnMap(string $tokenId, ?string $hex): void {
        $characterType = getPart($tokenId, 0);
        if ($characterType === "hero" || $characterType === "monster") {
            $this->game->systemAssert("moveStuffOnMap should not be used for character tokens");
        }
        $occ = $this->getOccupancyMap();
        // Remove from current hex
        foreach ($occ as $h => $entry) {
            if (isset($entry["stuff"][$tokenId])) {
                unset($this->map[$h]["stuff"][$tokenId]);
                break;
            }
        }
        // Place on new hex
        if ($this->isValidHex($hex)) {
            $this->map[$hex]["stuff"][$tokenId] = 0;
        }
    }
    /**
     * Lazy-load occupancy map: one DB query for all tokens on hex_* locations.
     * Each hex maps to: character (hero/monster token key, or null) and stuff (other token keys).
     * @return array<string, array{character: string|null, stuff: string[]}>
     */
    function getOccupancyMap(): array {
        if ($this->map === null) {
            $this->map = [];
            // Seed all valid hexes as empty
            foreach (array_keys($this->game->material->getTokensWithPrefix("hex")) as $hexId) {
                $this->map[$hexId] = ["character" => null, "stuff" => []];
            }
            // Populate with actual tokens on the map
            $tokens = $this->game->tokens->getTokensOfTypeInLocation(null, "hex%");
            foreach ($tokens as $token) {
                $hex = $token["location"];
                if (!isset($this->map[$hex])) {
                    continue; // token on a non-material hex (e.g. test-only locations)
                }
                $supertype = getPart($token["key"], 0);
                if (($supertype === "hero" || $supertype === "monster") && $this->map[$hex]["character"] === null) {
                    $this->map[$hex]["character"] = $token["key"];
                } else {
                    $this->map[$hex]["stuff"][$token["key"]] = $token["state"];
                }
            }
        }
        return $this->map;
    }

    function isOccupied(string $hexId): bool {
        $occ = $this->getOccupancyMap();
        return isset($occ[$hexId]) && $occ[$hexId]["character"] !== null;
    }

    /**
     * Returns the token ID of the character on this hex if it matches the given type, or null.
     * @param string $hexId hex to check
     * @param string $characterType "hero" or "monster"
     */
    function isOccupiedByCharacterType(string $hexId, string $characterType): ?string {
        $occ = $this->getOccupancyMap();
        $char = $occ[$hexId]["character"] ?? null;
        if ($char !== null && getPart($char, 0) === $characterType) {
            return $char;
        }
        return null;
    }

    /**
     * Returns the token ID of the character on this hex
     * @param string $hexId hex to check
     */
    function getCharacterOnHex(string $hexId, ?string $characterType): ?string {
        $occ = $this->getOccupancyMap();
        $char = $occ[$hexId]["character"] ?? null;
        if ($char !== null) {
            if (!$characterType) {
                return $char;
            }
            if (getPart($char, 0) === $characterType) {
                return $char;
            }
        }
        return null;
    }

    function canEnterHex(string $hexId, string $characterType): bool {
        if ($this->isImpassable($hexId, $characterType)) {
            return false;
        }
        if ($this->isOccupied($hexId)) {
            return false;
        }
        return true;
    }

    /**
     * BFS reachability: returns all hexes reachable from $startHex within $maxSteps.
     * Grimheim counts as one area (0 cost between its hexes).
     * Entering Grimheim ends movement. Exiting Grimheim costs 1 step.
     * @param string $characterType "hero" or "monster" — heroes cannot enter mountains, monsters can
     * @return array<string, int> hex => distance
     */
    function getReachableHexes(string $startHex, int $maxSteps = 3, string $characterType = "hero"): array {
        $startInGrimheim = $this->isInGrimheim($startHex);
        // BFS queue: [hexId, stepsUsed]
        $visited = [$startHex => 0];
        $queue = [[$startHex, 0]];
        // If starting in Grimheim, seed all Grimheim hexes at distance 0
        if ($startInGrimheim) {
            foreach ($this->getHexesInGrimheim() as $gHex) {
                if (!isset($visited[$gHex])) {
                    $visited[$gHex] = 0;
                    $queue[] = [$gHex, 0];
                }
            }
        }
        while ($queue) {
            [$current, $steps] = array_shift($queue);
            if ($steps >= $maxSteps) {
                continue;
            }
            foreach ($this->getAdjacentHexes($current) as $neighbor) {
                if (isset($visited[$neighbor])) {
                    continue;
                }
                if (!$this->canEnterHex($neighbor, $characterType)) {
                    continue;
                }
                // Entering Grimheim ends movement — mark reachable but don't expand
                if (!$startInGrimheim && $this->isInGrimheim($neighbor)) {
                    foreach ($this->getHexesInGrimheim() as $gHex) {
                        if (!isset($visited[$gHex])) {
                            $visited[$gHex] = $steps + 1;
                        }
                    }
                } else {
                    $visited[$neighbor] = $steps + 1;
                    $queue[] = [$neighbor, $steps + 1];
                }
            }
        }
        // Remove start hex
        unset($visited[$startHex]);
        return $visited;
    }

    /**
     * Returns all monster token IDs currently on the map (on hex_* locations),
     * sorted by distance to Grimheim (closest first).
     */
    function getMonstersOnMap(): array {
        $distMap = $this->getDistanceMapToGrimheim();
        $occ = $this->getOccupancyMap();
        $result = [];
        foreach ($occ as $hex => $entry) {
            $char = $entry["character"];
            if ($char !== null && getPart($char, 0) === "monster") {
                $result[] = ["id" => $char, "hex" => $hex, "dist" => $distMap[$hex] ?? PHP_INT_MAX];
            }
        }
        // Sort closest to Grimheim first, ties broken by hex id
        usort($result, function ($a, $b) {
            if ($a["dist"] !== $b["dist"]) {
                return $a["dist"] - $b["dist"];
            }
            return strcmp($a["hex"], $b["hex"]);
        });
        return $result;
    }

    /**
     * Returns true if any hero is adjacent to the given hex (or on it).
     */
    function isHeroAdjacentTo(string $hexId): bool {
        return $this->isCharacterTypeInRange($hexId, 1, "hero");
    }

    /**
     * Returns true if any character of the given type is within range of the hex (or on it).
     * @param string $type Character type prefix: "hero" or "monster"
     */
    function isCharacterTypeInRange(string $hexId, int $range, string $type): bool {
        $checkHexes = array_merge([$hexId], $this->getHexesInRange($hexId, $range));
        foreach ($checkHexes as $hex) {
            if ($this->isOccupiedByCharacterType($hex, $type) !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns all valid hexes within the given range of a hex (excluding the hex itself).
     * For range >= 2, uses BFS so that Grimheim hexes block the path (cannot shoot over Grimheim).
     * If the source hex is in Grimheim, Grimheim hexes are not blocked.
     */
    function getHexesInRange(string $hexId, int $range): array {
        if ($range <= 1) {
            return $this->getAdjacentHexes($hexId);
        }
        $sourceInGrimheim = $this->isInGrimheim($hexId);
        $visited = [$hexId => 0];
        $queue = [[$hexId, 0]];
        $result = [];
        while ($queue) {
            [$current, $steps] = array_shift($queue);
            if ($steps >= $range) {
                continue;
            }
            foreach ($this->getAdjacentHexes($current) as $neighbor) {
                if (isset($visited[$neighbor])) {
                    continue;
                }
                // Grimheim blocks ranged attacks passing through (unless shooting from inside Grimheim)
                $neighborInGrimheim = $this->isInGrimheim($neighbor);
                if ($neighborInGrimheim && !$sourceInGrimheim) {
                    // Can target Grimheim hexes but cannot pass through them
                    $visited[$neighbor] = $steps + 1;
                    $result[] = $neighbor;
                    continue; // don't expand further through Grimheim
                }
                $visited[$neighbor] = $steps + 1;
                $result[] = $neighbor;
                $queue[] = [$neighbor, $steps + 1];
            }
        }
        return $result;
    }
}
