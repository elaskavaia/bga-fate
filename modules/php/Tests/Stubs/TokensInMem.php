<?php

declare(strict_types=1);

namespace Bga\Games\Fate\Tests\Stubs;

use Bga\Games\Fate\Db\DbTokens;
use Exception;

use function Bga\Games\Fate\array_get;
use function Bga\Games\Fate\toJson;

/**
 * Stub class for tokens overriding db function to be in memory
 */

class TokensInMem extends DbTokens {
    static function record($arr) {
        return [
            "key" => $arr[0],
            "location" => $arr[1],
            "state" => $arr[2],
        ];
    }

    function clear_cache() {
        //
    }
    function getTokenInfo($token_key) {
        self::checkKey($token_key);
        return array_get($this->keyindex, $token_key, null);
    }

    function DbCreateTokens($values) {
        foreach ($values as $row) {
            $rec = static::record($row);
            $key = $rec["key"];
            if (array_key_exists($key, $this->keyindex)) {
                throw new Exception("Dupicate key $key in " . toJson($row));
            }

            $this->keyindex[$key] = $rec;
        }
    }

    function setTokenState($token_key, $state) {
        self::checkState($state);
        self::checkKey($token_key);
        if (!array_key_exists($token_key, $this->keyindex)) {
            echo "$token_key does not exists";
        }
        $this->keyindex[$token_key]["state"] = $state;
        return $state;
    }

    function moveToken($token_key, $location, $state = 0) {
        self::checkLocation($location);
        self::checkState($state, true);
        self::checkKey($token_key);
        if (!array_key_exists($token_key, $this->keyindex)) {
            $this->keyindex[$token_key] = [
                "location" => "limbo",
                "state" => 0,
                "key" => $token_key,
            ];
        }
        $this->keyindex[$token_key]["location"] = $location;
        if ($state !== null) {
            $this->keyindex[$token_key]["state"] = $state;
        }
    }

    static function matchLike($pattern, $value) {
        if ($pattern === null) {
            return true;
        }
        if ($value === null) {
            return false;
        }

        //$pattern = preg_quote($pattern);
        $pattern = str_replace("%", ".*", $pattern);
        $ret = preg_match("/^" . $pattern . '$/i', $value);
        return $ret === 1;
    }

    function getTokensOnLocations(array $locs) {
        $res = [];
        foreach ($locs as $loc) {
            $res = array_merge($res, $this->getTokensOfTypeInLocation(null, $loc));
        }
        return $res;
    }

    function getTokensOfTypeInLocation($type, $location = null, $state = null, $order_by = null) {
        $result = [];

        if ($type !== null) {
            if (strpos($type, "%") === false) {
                $type .= "%";
            }
        }
        foreach ($this->keyindex as $key => $rec) {
            if (!$this->matchLike($type, $key)) {
                continue;
            }
            if (!$this->matchLike($location, array_get($rec, "location", null))) {
                continue;
            }
            if ($state !== null && $rec["state"] != $state) {
                continue;
            }
            $result[$key] = $rec;
        }

        if ($order_by == "token_state") {
            uasort($result, fn($a, $b) => $a["state"] <=> $b["state"]);
        }

        return $result;
    }

    function countTokensInLocations() {
        $result = [];
        foreach ($this->keyindex as $rec) {
            $loc = $rec["location"];
            $result[$loc] = ($result[$loc] ?? 0) + 1;
        }
        return $result;
    }

    function countTokensInLocation($location, $state = null) {
        return count($this->getTokensOfTypeInLocation(null, $location, $state));
    }

    function getExtremePosition($getMax, $location, $token_key = null) {
        $tokens = $this->getTokensOfTypeInLocation($token_key, $location);
        if (count($tokens) == 0) {
            return 0;
        }
        $states = array_map(fn($t) => (int) $t["state"], $tokens);
        return $getMax ? max($states) : min($states);
    }

    function getTokensOnTop($nbr, $location) {
        $tokens = $this->getTokensOfTypeInLocation(null, $location);
        uasort($tokens, fn($a, $b) => $b["state"] <=> $a["state"]);
        return array_slice(array_values($tokens), 0, (int) $nbr);
    }

    function pickTokensForLocation($nbr, $from_location, $to_location, $state = 0, $no_deck_reform = false, &$was_reshuffled = null) {
        $tokens = $this->getTokensOnTop($nbr, $from_location);
        foreach ($tokens as &$token) {
            $this->moveToken($token["key"], $to_location, $state);
            $token["location"] = $to_location;
            $token["state"] = $state;
        }
        return $tokens;
    }

    function moveTokens($tokens, $location, $state = 0) {
        self::checkLocation($location);
        self::checkState($state, true);
        self::checkTokenKeyArray($tokens);
        foreach ($tokens as $key) {
            $this->moveToken($key, $location, $state);
        }
    }

    function shuffle($location) {
        $tokens = $this->getTokensOfTypeInLocation(null, $location);
        $keys = array_keys($tokens);
        \shuffle($keys);
        $n = 0;
        foreach ($keys as $key) {
            $this->keyindex[$key]["state"] = $n;
            $n++;
        }
    }

    function deleteAll() {
        $this->keyindex = [];
    }

    function insertToken($token_key, $location, $state = 0) {
        throw new \Exception("TokensInMem::insertToken() called — add in-memory stub if needed");
    }

    function insertTokenOnExtremePosition($token_key, $location, $bOnTop) {
        throw new \Exception("TokensInMem::insertTokenOnExtremePosition() called — add in-memory stub if needed");
    }

    function moveAllTokensInLocation($from_location, $to_location, $from_state = null, $to_state = 0) {
        throw new \Exception("TokensInMem::moveAllTokensInLocation() called — add in-memory stub if needed");
    }

    function moveAllTokensInLocationKeepOrder($from_location, $to_location) {
        throw new \Exception("TokensInMem::moveAllTokensInLocationKeepOrder() called — add in-memory stub if needed");
    }

    public function getAllTokens(): array {
        return array_values($this->keyindex);
    }

    public function toJson(): array {
        return array_values($this->keyindex);
    }

    public function fromJson(array $rows): void {
        foreach ($rows as $rec) {
            $this->DbCreateTokens([[$rec["key"], $rec["location"], $rec["state"]]]);
        }
    }
}
