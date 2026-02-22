<?php

/*
 * This is a generic class to manage game pieces.
 *
 * On DB side this is based on a standard table with the following fields:
 * token_key (string), token_location (string), token_state (int)
 *
 *
 * CREATE TABLE IF NOT EXISTS `token` (
 * `token_key` varchar(32) NOT NULL,
 * `token_location` varchar(32) NOT NULL,
 * `token_state` int(10),
 * PRIMARY KEY (`token_key`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
 *
 *
 */
namespace Bga\Games\Fate\Db;

use Bga\GameFramework\SystemException;
use Bga\Games\Fate\Game;

class DbTokens {
    var $table;
    var $autoreshuffle = false; // If true, a new deck is automatically formed with a reshuffled discard as soon at is needed
    var $autoreshuffle_trigger = null; // Callback to a method called when an autoreshuffle occurs
    // autoreshuffle_trigger = array( 'obj' => object, 'method' => method_name )
    // If defined, tell the name of the deck and what is the corresponding discard (ex : "mydeck" => "mydiscard")
    var $autoreshuffle_custom = [];
    private $custom_fields;
    protected $keyindex = []; // cache

    public Game $game; // game ref
    function __construct(Game $game) {
        $this->game = $game;
        $this->table = "token";
        $this->custom_fields = [];
    }

    // MUST be called before any other method if db table is not called 'token'
    function init($table) {
        $this->table = $table;
    }

    function clear_cache() {
        $this->keyindex = [];
    }

    function init_cache() {
        if (count($this->keyindex) == 0) {
            $this->keyindex = $this->getTokensOfTypeInLocation(null);
        }
    }

    // This inserts new records in the database. Generically speaking you should only be calling during setup with some
    // rare exceptions.
    //
    // DbTokens are added into location specified, (default is 'deck')
    //
    // DbTokens is an array with at least the following fields:
    // array(
    //      array(                              // This is my first token
    //          "key" => <unique key>           // This unique alphanum and underscore key, use {INDEX} to replace with index if 'nbr' > 1, i..e "meeple_{INDEX}_red"
    //          "nbr" => <nbr>                  // Number of tokens with this key, default is 1. If nbr >1 and key does not have {INDEX} it will throw an exception
    //          "location" => <location>        // Optional argument specifies the location, alphanum and underscore
    //          "state" => <state>              // Optional argument specifies integer state, if not specified and $token_state_global is not specified auto-increment is used
    function createTokens($tokens, $location_global, $token_state_global = null) {
        if ($location_global) {
            $next_pos = $this->getExtremePosition(true, $location_global) + 1;
        } else {
            $next_pos = 0;
        }
        $values = [];
        $keys = [];
        foreach ($tokens as $token_info) {
            if (isset($token_info["nbr"])) {
                $n = $token_info["nbr"];
            } else {
                $n = 1;
            }
            if (isset($token_info["nbr_start"])) {
                $start = $token_info["nbr_start"];
            } else {
                $start = 0;
            }
            for ($i = $start; $i < $n + $start; $i++) {
                if (isset($token_info["location"])) {
                    $location = $token_info["location"];
                } else {
                    $location = $location_global;
                }
                if (isset($token_info["state"])) {
                    $token_state = (int) $token_info["state"];
                } else {
                    $token_state = $token_state_global;
                }
                if ($token_state === null) {
                    if ($location == $location_global) {
                        $token_state = $next_pos;
                        $next_pos++;
                    } else {
                        $token_state = 0;
                    }
                }
                $key = $token_info["key"];
                if ($key == null) {
                    throw new SystemException("createTokens: key cannot be null");
                }
                $key = $this->varsub($key, array_merge($token_info, ["INDEX" => $i]), true);
                if ($location == null) {
                    throw new SystemException("createTokens: location cannot be null (set per token location or location_global");
                }
                self::checkLocation($location);
                self::checkKey($key);
                $values[] = [$key, $location, $token_state];
                $keys[] = $key;
            }
        }
        $this->DbCreateTokens($values);
        return $keys;
    }

    function createTokenIfNot($key, $location = "limbo", $token_state = 0) {
        if ($this->getTokenInfo($key)) {
            $this->moveToken($key, $location, $token_state);
            return $key;
        } else {
            return $this->createToken($key, $location, $token_state);
        }
    }
    function createToken($key, $location = "limbo", $token_state = 0) {
        self::checkLocation($location);
        self::checkState($token_state);
        self::checkKey($key);
        $values = [];
        $values[] = [$key, $location, $token_state];
        $this->DbCreateTokens($values);
        return $key;
    }

    function DbCreateTokens($values) {
        $this->clear_cache();
        $seqvalues = [];
        foreach ($values as $row) {
            $seqvalues[] = "( '$row[0]', '$row[1]', '$row[2]' )";
        }
        $sql = "INSERT INTO " . $this->table . " (token_key,token_location,token_state)";
        $sql .= " VALUES " . implode(",", $seqvalues);
        $this->game->DbQuery($sql);
    }

    /**
     * Create tokens during the game (dynamic piles) with auto increment number
     * token must be in form of "prefix_index" where prefix is passed to this function, and index is generated as max
     * number of tokens starting prefix pre-existing in db
     *
     * @param string $type
     * @param string $location
     * @return string - token key
     */
    function createTokenAutoInc($type, $location = "limbo", $token_state = 0, $start = 0) {
        $this->clear_cache();
        $allsuf = $this->getTokensOfTypeInLocation($type);
        $resnum = count($allsuf) + $start;
        return $this->createToken("{$type}_{$resnum}", $location, $token_state);
    }

    function createTokensPack($key, $location, $nbr = 1, $nbr_start = 1, $iterArr = null, $token_state = null) {
        $this->clear_cache();
        if ($iterArr == null) {
            $iterArr = [""];
        }
        if (!is_array($iterArr)) {
            throw new SystemException("iterArr must be an array");
        }
        if (count($iterArr) == 0) {
            $iterArr = [""];
        }
        $tokenSpec = ["key" => $key, "location" => $location, "nbr" => $nbr, "nbr_start" => $nbr_start];
        $tokens = [];
        foreach ($iterArr as $iterKey) {
            $newspec = [];
            foreach ($tokenSpec as $tokenSpecKey => $value) {
                $value = $this->varsub($value, ["TYPE" => $iterKey]);
                $value = $this->varsub($value, ["COLOR" => $iterKey]);
                $newspec[$tokenSpecKey] = $value;
            }
            $tokens[] = $newspec;
        }
        return $this->createTokens($tokens, null, $token_state);
    }

    // Get max on min state on the specific location
    function getExtremePosition($getMax, $location, $token_key = null) {
        self::checkLocation($location, true);
        if ($getMax) {
            $sql = "SELECT MAX( token_state ) res ";
        } else {
            $sql = "SELECT MIN( token_state ) res ";
        }
        $sql .= "FROM " . $this->table;
        $like = "LIKE";
        if (strpos($location, "%") === false) {
            $like = "=";
        } else {
            $location = preg_replace("/_/", "\\_", $location);
        }
        $sql .= " WHERE token_location $like '$location' ";
        if ($token_key != null) {
            self::checkKey($token_key, true);
            $like = "LIKE";
            if (strpos($token_key, "%") === false) {
                $like = "=";
            } else {
                $token_key = preg_replace("/_/", "\\_", $token_key);
            }
            $sql .= " AND token_key $like '$token_key' ";
        }
        return $this->game->getUniqueValueFromDB($sql);
    }

    // Shuffle token of a specified location, result of the operation will changes state of the token to be a position after shuffling
    function shuffle($location) {
        self::checkLocation($location);
        $this->clear_cache();
        $token_keys = $this->game->getObjectListFromDB("SELECT token_key FROM " . $this->table . " WHERE token_location='$location'", true);
        shuffle($token_keys);
        $n = 0;
        foreach ($token_keys as $token_key) {
            $this->game->DbQuery("UPDATE " . $this->table . " SET token_state='$n' WHERE token_key='$token_key'");
            $n++;
        }
    }

    function deleteAll() {
        $this->game->DbQuery("DELETE FROM " . $this->table);
        $this->clear_cache();
    }

    // Pick the first "$nbr" cards on top of specified deck and place it in target location
    // Return cards infos or void array if no card in the specified location
    function pickTokensForLocation($nbr, $from_location, $to_location, $state = 0, $no_deck_reform = false, &$was_reshuffled = null) {
        $this->clear_cache();
        $tokens = self::getTokensOnTop($nbr, $from_location);
        $tokens_ids = [];
        foreach ($tokens as $i => $card) {
            $tokens_ids[] = $card["key"];
            $tokens[$i]["location"] = $to_location;
            $tokens[$i]["state"] = $state;
        }
        $sql = "UPDATE " . $this->table . " SET token_location='" . addslashes($to_location) . "', token_state='$state' ";
        $sql .= "WHERE token_key IN ('" . implode("','", $tokens_ids) . "') ";
        $this->game->DbQuery($sql);
        if (isset($this->autoreshuffle_custom[$from_location]) && count($tokens) < $nbr && $this->autoreshuffle && !$no_deck_reform) {
            // No more cards in deck & reshuffle is active => form another deck
            if ($this->countTokensInLocation($this->autoreshuffle_custom[$from_location]) == 0) {
                return $tokens;
            }
            $nbr_token_missing = $nbr - count($tokens);
            self::reformDeckFromDiscard($from_location);
            $newcards = self::pickTokensForLocation($nbr_token_missing, $from_location, $to_location, $state, true); // Note: block anothr deck reform
            foreach ($newcards as $card) {
                $tokens[] = $card;
            }
            $was_reshuffled = true;
        }
        return $tokens;
    }

    /**
     * Return token on top of this location, top defined as item with higher state value
     */
    function getTokenOnTop($location, $no_deck_reform = true) {
        $result_arr = $this->getTokensOnTop(1, $location);
        if (count($result_arr) > 0) {
            return $result_arr[0];
        }
        if (isset($this->autoreshuffle_custom[$location]) && $this->autoreshuffle && !$no_deck_reform) {
            // No more cards in deck & reshuffle is active => form another deck
            self::reformDeckFromDiscard($location);
            $result_arr = $this->getTokensOnTop(1, $location);
            if (count($result_arr) > 0) {
                return $result_arr[0];
            }
        }
        return null;
    }

    /**
     * Return "$nbr" tokens on top of this location, top defined as item with higher state value
     */
    function getTokensOnTop($nbr, $location) {
        self::checkLocation($location);
        self::checkPosInt($nbr);
        $result = [];
        $sql = $this->getSelectQuery();
        $sql .= " WHERE token_location='$location'";
        $sql .= " ORDER BY token_state DESC";
        $sql .= " LIMIT $nbr";
        return $this->game->getObjectListFromDB($sql);
    }

    function reformDeckFromDiscard($from_location) {
        self::checkLocation($from_location);
        $this->clear_cache();
        if (isset($this->autoreshuffle_custom[$from_location])) {
            $discard_location = $this->autoreshuffle_custom[$from_location];
        } else {
            throw new SystemException("reformDeckFromDiscard: Unknown discard location for $from_location !");
        }
        self::checkLocation($discard_location);
        self::moveAllTokensInLocation($discard_location, $from_location);
        self::shuffle($from_location);
        if ($this->autoreshuffle_trigger) {
            $obj = $this->autoreshuffle_trigger["obj"];
            $method = $this->autoreshuffle_trigger["method"];
            $obj->$method($from_location, $discard_location);
        }
    }

    // Set token state
    function setTokenState($token_key, $state) {
        try {
            self::checkState($state);
        } catch (\Exception $e) {
            throw new SystemException($e->getMessage() . " for $token_key");
        }
        self::checkKey($token_key);
        $this->clear_cache();
        $sql = "UPDATE " . $this->table;
        $sql .= " SET token_state='$state'";
        $sql .= " WHERE token_key='$token_key'";
        $this->game->DbQuery($sql);
        return $state;
    }

    /**
     * Move a token to specific location in specific state, if state set to null do not change state
     */
    function moveToken(string $token_key, string $location, int $state = 0) {
        self::checkLocation($location);
        self::checkState($state, true);
        self::checkKey($token_key);
        $this->clear_cache();
        $sql = "UPDATE " . $this->table;
        $sql .= " SET token_location='$location'";
        if ($state !== null) {
            $sql .= ", token_state='$state'";
        }
        $sql .= " WHERE token_key='$token_key'";
        return $this->game->DbQuery($sql);
    }

    /**
     * Move tokens (array of ids) to specific location in specific state, if state set to null do not change state
     */
    function moveTokens($tokens, $location, $state = 0) {
        self::checkLocation($location);
        self::checkState($state, true);
        self::checkTokenKeyArray($tokens);
        $sql = "UPDATE " . $this->table;
        $sql .= " SET token_location='$location'";
        if ($state !== null) {
            $sql .= ", token_state='$state'";
        }
        $sql .= " WHERE token_key IN ('" . implode("','", $tokens) . "')";
        $this->game->DbQuery($sql);
        $this->clear_cache();
    }

    // Move a card to a specific location where card are ordered. If location_arg place is already taken, increment
    // all tokens after location_arg in order to insert new card at this precise location
    function insertToken($token_key, $location, $state = 0) {
        self::checkLocation($location);
        self::checkState($state);
        $sql = "UPDATE " . $this->table;
        $sql .= " SET token_state=token_state+1";
        $sql .= " WHERE token_location='$location' ";
        $sql .= " AND token_state>=$state";
        $this->game->DbQuery($sql);
        self::moveToken($token_key, $location, $state);
        $this->clear_cache();
    }

    function insertTokenOnExtremePosition($token_key, $location, $bOnTop) {
        $extreme_pos = self::getExtremePosition($bOnTop, $location);
        if ($bOnTop) {
            self::insertToken($token_key, $location, $extreme_pos + 1);
        } else {
            self::insertToken($token_key, $location, $extreme_pos - 1);
        }
        $this->clear_cache();
    }

    // Move all tokens from a location to another
    // !!! state is reset to 0 or specified value !!!
    // if "from_location" and "from_state" are null: move ALL cards to specific location
    function moveAllTokensInLocation($from_location, $to_location, $from_state = null, $to_state = 0) {
        if ($from_location != null) {
            self::checkLocation($from_location);
        }
        self::checkLocation($to_location);
        $sql = "UPDATE " . $this->table . " ";
        $sql .= "SET token_location='$to_location', token_state='$to_state' ";
        if ($from_location !== null) {
            $sql .= "WHERE token_location='" . addslashes($from_location) . "' ";
            if ($from_state !== null) {
                $sql .= "AND token_state='$from_state' ";
            }
        }
        $this->game->DbQuery($sql);
        $this->clear_cache();
    }

    /**
     * Move all tokens from a location to another location arg stays with the same value
     */
    function moveAllTokensInLocationKeepOrder($from_location, $to_location) {
        self::checkLocation($from_location);
        self::checkLocation($to_location);
        $sql = "UPDATE " . $this->table;
        $sql .= " SET token_location='$to_location'";
        $sql .= " WHERE token_location='$from_location'";
        $this->game->DbQuery($sql);
        $this->clear_cache();
    }

    /**
     * Get tokens of a specific type in a specific location, since there is no field for type we use like expression on
     * key
     *
     * @param string $type -  if type contains % it will be treated as LIKE, otherwise % will be appended
     * @param string $location - if location contains % it will be treated as LIKE
     * @param int|string $state - state can be just numeric or can be expression i.e. "!=0"
     * @param string $order_by - field to order by (it has to start with "token_" prefix)
     *
     * @return array mixed
     */
    function getTokensOfTypeInLocation($type, $location = null, $state = null, $order_by = null) {
        $sql = $this->getSelectQuery();
        $sql .= " WHERE true ";
        if ($type !== null) {
            if (strpos($type, "%") === false) {
                $type .= "%";
            }
            self::checkType($type);
            $type = preg_replace("/_/", "\\_", $type);
            $sql .= " AND token_key LIKE '$type'";
        }
        if ($location !== null) {
            self::checkLocation($location, true, false);
            $like = "LIKE";
            if (strpos($location, "%") === false) {
                $like = "=";
            } else {
                $location = preg_replace("/_/", "\\_", $location);
            }
            $sql .= " AND token_location $like '$location' ";
        }
        if ($state !== null) {
            $op = $this->extractStateOperator($state);
            self::checkState($state, false);
            $sql .= " AND token_state $op '$state'";
        }
        if ($order_by !== null) {
            $sql .= " ORDER BY $order_by ASC";
        }

        return $this->game->getCollectionFromDB($sql);
    }

    function getTokensOnLocations(array $locs) {
        $sql = $this->getSelectQuery();
        $sql .= " WHERE token_location IN ('" . implode("','", $locs) . "') ";
        return $this->game->getCollectionFromDB($sql);
    }

    function getTokensOfTypeInLocationSingle($type, $location = null, $state = null, $order_by = null) {
        $res = $this->getTokensOfTypeInLocation($type, $location, $state, $order_by);
        if (count($res) == 0) {
            return null;
        }
        return reset($res);
    }

    function getTokensOfTypeInLocationSingleKey($type, $location = null, $state = null, $order_by = null) {
        $res = $this->getTokensOfTypeInLocation($type, $location, $state, $order_by);
        if (count($res) == 0) {
            return null;
        }
        return reset($res)["key"];
    }

    function getTokenState($token_id, $def = 0) {
        $res = $this->getTokenInfo($token_id);
        if ($res == null) {
            return $def;
        }
        return (int) $res["state"];
    }

    function getTokenLocation($token_id) {
        $res = $this->getTokenInfo($token_id);
        if ($res == null) {
            return null;
        }
        return $res["location"];
    }

    /**
     * Get specific token info
     */
    function getTokenInfo($token_key) {
        self::checkKey($token_key);
        $this->init_cache();
        return $this->keyindex[$token_key] ?? null;
        // $sql = $this->getSelectQuery();
        // $sql .= " WHERE token_key='$token_key' ";
        // $dbres = $this->game->DbQuery($sql);
        // return mysql_fetch_assoc($dbres);
    }

    function getTokensInfo($tokens) {
        self::checkTokenKeyArray($tokens);
        $this->init_cache();
        $res = [];
        foreach ($tokens as $id) {
            $res[$id] = $this->keyindex[$id] ?? null;
        }
        return $res;
    }

    function countTokensInLocation($location, $state = null) {
        self::checkLocation($location, true);
        self::checkState($state, true);
        $like = "LIKE";
        if (strpos($location, "%") === false) {
            $like = "=";
        } else {
            $location = preg_replace("/_/", "\\_", $location);
        }
        $sql = "SELECT COUNT( token_key ) cnt FROM " . $this->table;
        $sql .= " WHERE token_location $like '$location' ";
        if ($state !== null) {
            $sql .= "AND token_state='$state' ";
        }

        return $this->game->getUniqueValueFromDB($sql);
    }

    // Return an array "location" => number of cards
    function countTokensInLocations() {
        $result = [];
        $sql = "SELECT token_location, COUNT( token_key ) cnt FROM " . $this->table . " GROUP BY token_location ";
        return $this->game->getCollectionFromDB($sql, true);
    }

    function varsub($line, $keymap) {
        if ($line === null) {
            throw new SystemException("varsub: line cannot be null");
        }
        if (strpos($line, "{") !== false) {
            foreach ($keymap as $key => $value) {
                if (strpos($line, "{$key}") !== false) {
                    $line = preg_replace("/\{$key\}/", $value, $line);
                }
            }
        }
        return $line;
    }

    final function checkLocation($location, $like = false, $canBeNull = false) {
        if ($location === null) {
            if ($canBeNull === false) {
                throw new SystemException("location cannot be null");
            } else {
                return;
            }
        }
        $extra = "";
        if ($like) {
            $extra = "%";
        }
        if (preg_match("/^[A-Za-z{$extra}][A-Za-z_0-9{$extra}-]*$/", $location) == 0) {
            throw new SystemException("location must be alphanum and underscore non empty string");
        }
    }

    function extractStateOperator(&$state) {
        $op = "";
        if ($state !== null) {
            $state = trim($state);
            $matches = [];
            $res = preg_match("/^(>=|>|<=|<|<>|!=|=|) *(-?[0-9]+)$/", $state, $matches, PREG_OFFSET_CAPTURE);
            if ($res == 1) {
                $op = $matches[1][0];
                $rest = $matches[2][0];
                $state = $rest;
            }
        }
        if (!$op) {
            $op = "=";
        }
        return $op;
    }

    final function checkState($state, $canBeNull = false) {
        if ($state === null && $canBeNull == false) {
            throw new SystemException("state cannot be null");
        }
        if ($state !== null && preg_match("/^-?[0-9]+$/", $state) != 1) {
            // $bt = debug_backtrace();
            // trigger_error("bt ".print_r($bt[2],true)) ;
            throw new SystemException("state must be integer number");
        }
    }

    final function checkTokenKeyArray($token_arr) {
        $res = $this->checkListOrTokenArray($token_arr);
        if ($res != 1) {
            $debug = var_export($token_arr, true);
            throw new SystemException("token_arr is not a list of token ids $res: $debug");
        }
    }

    final function checkKey($key, $like = false) {
        if ($key == null) {
            throw new SystemException("key cannot be null");
        }
        if (!is_string($key)) {
            throw new SystemException("key is not a string");
        }
        $extra = "";
        if ($like) {
            $extra = "%";
        }
        if (preg_match("/^[A-Za-z_0-9{$extra}]+$/", $key) == 0) {
            throw new SystemException("key must be alphanum and underscore non empty string '$key'");
        }
    }

    final function checkType($key) {
        if ($key == null) {
            throw new SystemException("type cannot be null");
        }
        $this->checkKey($key, true);
    }

    final function checkPosInt($key) {
        if ($key && preg_match("/^[0-9]+$/", $key) == 0) {
            throw new SystemException("must be integer number");
        }
    }

    /**
     * Checks that given array either list of keys or list returned by function such get getTokensInfo which is map of
     * key => info pairs
     * throws exception if not of any of this structures, otherwise it returns
     *
     * @param * $token_arr
     * @return number below: <br>
     *         0 - array is empty
     *         1 - array of token ids
     *         2 - array of key => info map with token_id as index
     *         3 - array of key => info with number as index (returned by sorting methods)
     */
    final function checkListOrTokenArray($token_arr, $bThrow = true) {
        try {
            if ($token_arr === null) {
                throw new SystemException("token_arr cannot be null");
            }
            $debug = var_export($token_arr, true);
            if (!is_array($token_arr)) {
                throw new SystemException("token_arr is not an array: $debug");
            }
            if (count($token_arr) == 0) {
                return 0;
            }
            $type = -1;
            foreach ($token_arr as $key => $info) {
                $typeone = $this->checkTokenIdOrInfo($info);
                if ($type == -1) {
                    $type = $typeone;
                } elseif ($type != $typeone) {
                    throw new SystemException("token_arr data has mixed types $type != $typeone: $debug");
                }
                if (is_numeric($key)) {
                    // ok
                    if ($typeone == 2) {
                        $typeone = 3;
                    }
                } elseif ($typeone == 2) {
                    $k = $info["key"];
                    if ($key != $k) {
                        throw new SystemException("token_arr data key info mismatch $key != $k: $debug");
                    }
                }
                if ($key === "key" || $key === "location" || $key === "state") {
                    throw new SystemException("token_arr data is not right array: $key $debug");
                }
            }
            return $type;
        } catch (SystemException $e) {
            if ($bThrow) {
                throw $e;
            }
            return -1;
        }
    }

    final function checkTokenIdOrInfo($info, $bThrow = true) {
        try {
            if (is_array($info)) {
                if (array_key_exists("key", $info)) {
                    $this->checkKey($info["key"]);
                    return 2;
                }
                $debug = var_export($info, true);
                throw new SystemException("token info structure is not correct: $debug");
            } else {
                $this->checkKey($info);
                return 1;
            }
        } catch (SystemException $e) {
            if ($bThrow) {
                throw $e;
            }
            return -1;
        }
    }

    /**
     * Converts arbitrary data structure to token key list
     * Accepted structures
     *    <li> string id
     *    <li> info - i.e. ['key'=>'x', 'state'=>1, 'location'=>'y']
     *    <li> info[] - info array (with key or integer as index)
     *    <li> id[] - id array
     * @param * $token_arr
     * @return string[] - token id list
     */
    function toTokenKeyList($tokens) {
        if (!$tokens) {
            return [];
        }
        $kind = $this->checkTokenIdOrInfo($tokens, false);
        switch ($kind) {
            case 1: // key
                return [$tokens];
            case 2: // info
                return [$tokens["key"]];
        }
        $kind = $this->checkListOrTokenArray($tokens);
        switch ($kind) {
            case 0:
                return [];
            case 1:
                return $tokens;
            case 2:
                return array_keys($tokens);
            case 3:
                $keys = [];
                foreach ($tokens as $info) {
                    $keys[] = $info["key"];
                }
                return $keys;
            default:
                $debug = var_export($tokens, true);
                throw new SystemException("tokens structure is not supported: $debug");
        }
    }

    function getSelectQuery() {
        $sql = "SELECT token_key AS \"key\", token_location AS \"location\", token_state AS \"state\"";
        if (count($this->custom_fields)) {
            $sql .= ", ";
            $sql .= implode(", ", $this->custom_fields);
        }
        $sql .= " FROM " . $this->table;
        return $sql;
    }

    function setCustomFields($fields_array) {
        $this->checkTokenKeyArray($fields_array);
        $this->custom_fields = $fields_array;
    }

    function dbReplaceValues($values) {
        if (count($values) == 0) {
            return;
        }
        $this->clear_cache();
        $fields_list = $this->game->dbGetFieldList($this->table);
        $key = array_shift($fields_list);
        $table = $this->table;
        foreach ($values as $row) {
            $quoted = [];
            foreach ($fields_list as $field) {
                $value = $row[$field] ?? null;

                if ($value === null) {
                    $quoted[] = "$field = NULL";
                } elseif (is_numeric($value)) {
                    $quoted[] = "$field = $value";
                } else {
                    $value = $this->game->escapeStringForDB($value);
                    $quoted[] = "$field = '$value'";
                }
            }
            $setValues = implode(",", $quoted);
            $keyValue = $row[$key];
            $sql = "UPDATE $table SET $setValues WHERE $key = '$keyValue'";

            $this->game->DbQuery($sql);
        }
    }
}
