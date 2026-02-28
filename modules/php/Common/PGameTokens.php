<?php
namespace Bga\Games\Fate\Common;

use Bga\Games\Fate\Db\DbTokens;
use Bga\Games\Fate\Game;

use function Bga\Games\Fate\array_get;
use function Bga\Games\Fate\endsWith;
use function Bga\Games\Fate\toJson;

/**
 * This class contants functions that work with tokens SQL model and tokens class
 *
 *
 */

class PGameTokens {
    public function __construct(private Game $game, public DbTokens &$db) {
        $this->db->autoreshuffle_trigger = ["obj" => $this, "method" => "autoreshuffleHandler"];
    }

    protected function setCounter(&$array, $key, $value) {
        $array[$key] = ["value" => $value, "name" => $key];
    }

    protected function counterNameOf($location) {
        return "counter_$location";
    }

    protected function fillCounters(&$array, $locs, $create = true) {
        foreach ($locs as $location => $count) {
            $key = $this->counterNameOf($location);
            if ($create || array_key_exists($key, $array)) {
                $this->setCounter($array, $key, $count);
            }
        }
    }

    function autoreshuffleHandler($place_from, $place_to) {
        $player_id = $this->game->getMostlyActivePlayerId();
        if ($this->isCounterAllowedForLocation($player_id, $place_from)) {
            $this->notifyCounterChanged($place_from, ["nod" => true]);
        }
        if ($place_to != $place_from && $this->isCounterAllowedForLocation($player_id, $place_to)) {
            $this->notifyCounterChanged($place_to, ["nod" => true]);
        }
    }

    protected function fillTokensFromArray(&$array, $cards) {
        foreach ($cards as $pos => $card) {
            $id = $card["key"];
            $array[$id] = $card;
        }
    }

    public function getTokenName($token_id) {
        if (is_array($token_id)) {
            return $token_id;
        }
        if ($token_id == null) {
            return "null";
        }
        if (!$token_id) {
            return "";
        }
        return $this->getRulesFor($token_id, "name", $token_id);
    }

    public function getAllDatas(): array {
        $result = [];
        $current_player_id = $this->game->getCurrentPlayerId(); // !! We must only return informations visible by this player !!

        $token_types = $this->game->material->get();
        $result["token_types"] = $token_types;
        $result["tokens"] = [];
        $result["counters"] = $this->getDefaultCounters();
        $locs = $this->db->countTokensInLocations();
        //$color = $this->getPlayerColor($current_player_id);
        foreach ($locs as $location => $count) {
            $sort = $this->getRulesFor($location, "sort", null);
            $this->game->debugLog("$location sort=$sort");
            if ($this->isCounterAllowedForLocation($current_player_id, $location)) {
                $this->fillCounters($result["counters"], [$location => $count]);
            }
            $content = $this->isContentAllowedForLocation($current_player_id, $location);

            if ($content === false) {
                continue;
            }
            if ($content === true) {
                $tokens = $this->db->getTokensOfTypeInLocation(null, $location, null, $sort);
                $this->fillTokensFromArray($result["tokens"], $tokens);
            } else {
                $num = floor($content);
                if ($count < $num) {
                    $num = $count;
                }
                $tokens = $this->db->getTokensOnTop($num, $location);
                $this->fillTokensFromArray($result["tokens"], $tokens);
            }
        }

        return $result;
    }

    function getReverseLocationTokensMapping(array $tokens, bool $flatten = false) {
        $array = [];
        foreach ($tokens as $pos => $token) {
            $id = $token["location"];
            if ($flatten) {
                $array[$id] = $token["key"];
            } else {
                if (!array_get($array, $id)) {
                    $array[$id] = [];
                }
                $array[$id][] = $token["key"];
            }
        }
        return $array;
    }

    protected function getDefaultCounters() {
        $token_types = $this->game->material->get();
        $types = $token_types;
        $res = [];
        $players_basic = $this->game->loadPlayersBasicInfosWithBots();
        foreach ($types as $key => $info) {
            if (!$this->isConsideredLocation($key)) {
                continue;
            }
            $scope = array_get($info, "scope");
            $counter = array_get($info, "counter");
            if ($scope && $counter != "hidden") {
                if ($scope == "player") {
                    // per player location
                    foreach ($players_basic as $player_info) {
                        $color = $player_info["player_color"];
                        $this->setCounter($res, $this->counterNameOf("{$key}_{$color}"), 0);
                    }
                } else {
                    $this->setCounter($res, $this->counterNameOf("{$key}"), 0);
                }
            }
        }
        return $res;
    }

    function createCounterInfoForLocation($location) {
        $counter = $this->counterNameOf($location);
        $location_name = $this->getRulesFor($location, "name");
        return [
            "counter_name" => $counter,
            "location" => $location,
            "name" => [
                "log" => clienttranslate('${location_name} Counter'),
                "args" => ["location_name" => $location_name, "i18n" => ["location_name"]],
            ],
        ];
    }

    function getAllRules($token_id, $default = []) {
        return $this->getRulesFor($token_id, "*", $default);
    }

    function getRulesFor($token_id, $field = "r", $default = "") {
        return $this->game->material->getRulesFor($token_id, $field, $default);
    }
    /**
     * Create tokens based on fields found in $this->token_types
     * Only tokens with 'create' field set will be considered
     * 'create' field can be one the following values:
     * 1 - the token with id $id will be created, count must be set to 1 if used
     * 4 - the token with id "${id}_{COLOR}" for each player will be created, count must be 1
     * 2 - the token with id "${id}_{INDEX}" will be created, using count
     * 3 - the token with id "${id}_{COLOR}_{INDEX}" will be created, using count, per player
     * 'location' - if set token will be created on this location, if not set in 'limbo'
     * 'state' - if set token will be create with this state, otherwise it is 0
     */
    function createTokens() {
        $token_types = $this->game->material->get();
        foreach ($token_types as $id => $info) {
            $this->createTokenFromInfo($id, $info);
        }
    }

    protected function createTokenFromInfo($id, $info) {
        $create_type = array_get($info, "create", 0);
        if (!$create_type) {
            return;
        }
        $count = array_get($info, "count", 1);

        if (!$count) {
            return;
        }

        try {
            $token_id = $id;
            if ($create_type === 1 || $create_type === "single") {
                $token_id = $id;
            } elseif ($create_type === 2 || $create_type === "index") {
                $token_id = "{$id}_{INDEX}";
            } elseif ($create_type === 3 || $create_type === "color_index") {
                $token_id = "{$id}_{COLOR}_{INDEX}";
            } elseif ($create_type === 4 || $create_type === "color") {
                $token_id = "{$id}_{COLOR}";
            } elseif ($create_type === 5 || $create_type === "index_color") {
                $token_id = "{$id}_{INDEX}_{COLOR}";
            }
            if (strpos($token_id, "{INDEX}") === false) {
                $count = 1;
            }
            // location and state use recursive parent fallback
            $location = $this->game->getRulesFor($id, "location", "limbo");
            $state = $this->game->getRulesFor($id, "state", 0);
            $start = array_get($info, "start", 1);
            if (strpos($token_id, "{COLOR}") === false) {
                $this->db->createTokensPack($token_id, $location, $count, $start, null, $state);
            } else {
                $this->db->createTokensPack($token_id, $location, $count, $start, $this->game->getPlayerColors(), $state);
            }
        } catch (\Exception $e) {
            $this->game->systemAssert("Failed to create tokens in location $token_id $location x $count ");
        }
    }
    function createCounterToken($token) {
        $info = $this->db->getTokenInfo($token);
        if ($info != null) {
            return $info;
        }
        //$this->game->systemAssert("Not found $token");
        $info = $this->getRulesFor($token, "*");
        if (!is_array($info)) {
            $this->game->systemAssert("Not found $token");
        }
        $id = $info["_key"];
        $this->createTokenFromInfo($id, $info);
        return $info;
    }

    protected function isConsideredLocation(string $id) {
        $type = $this->getRulesFor($id, "type", "");
        return $type == "location"; // XXX contains?
    }

    protected function isContentAllowedForLocation($player_id, $location, $attr = "content") {
        if ($location === "dev_null") {
            return false;
        }

        if ($this->isConsideredLocation($location)) {
            $info = $this->getAllRules($location, null);
            $scope = array_get($info, "scope");
            $content_type = array_get($info, $attr);

            if ($scope) {
                if ($content_type == "public") {
                    // content allowed for everyboady
                    return true;
                }
                if ($content_type == "private" && $this->game->isRealPlayer($player_id)) {
                    // content allow only if location of same color
                    $color = $this->game->custom_getPlayerColorById($player_id);
                    return endsWith($location, $color);
                }
                return false;
            } else {
                return false; // not listed as location
            }
        }

        if ($attr == "counter") {
            return false;
        } // not listed - do not need counter
        return true; // otherwise it location ok
    }

    protected function isCounterAllowedForLocation($player_id, $location) {
        return $this->isContentAllowedForLocation($player_id, $location, "counter");
    }

    function dbSetTokenState($token_id, $state = null, $notif = "*", $args = [], int $player_id = 0) {
        $this->dbSetTokenLocation($token_id, null, $state, $notif, $args, $player_id);
    }

    function dbPickTokenForLocation($from_place, $to_place, $state = null, $notif = "*", $args = [], int $player_id = 0) {
        $picks = $this->game->tokens->db->pickTokensForLocation(1, $from_place, $to_place);
        $pick = array_shift($picks);
        if ($pick) {
            $this->game->tokens->dbSetTokenLocation(
                $pick["key"],
                $to_place,
                $state,
                $notif,
                ["place_from" => $from_place] + $args,
                $player_id
            );
        } else {
            $this->game->notifyMessage(clienttranslate('No cards left in ${token_name}'), ["token_name" => $from_place]);
        }
    }

    /**
     * Move a token to a new location, update DB, and send a "tokenMoved" notification.
     *
     * The following keys are auto-added to $args (and available in $notif as substitution variables):
     *  - token_id   — the token key being moved
     *  - place_id   — the destination location
     *  - token_name — will be change to actual token name on the client
     *  - place_name — same as place_id (used for log rendering, will be change to name)
     *  - new_state  — the state value after the move
     *  - place_from — the token's previous location
     *  - token_div  — same as token_id (only added when $notif contains '${token_div}')
     *
     * Caller-supplied $args take precedence over auto-added keys (merged after).
     *
     * @param string      $token_id  Token key to move
     * @param string|null $place_id  Destination location (null = keep current location)
     * @param int|null    $state     New state value (null = keep current state)
     * @param string      $notif     Notification message with ${…} placeholders ("*" = default message)
     * @param array       $args      Extra notification args (merged over auto-added keys)
     * @param int         $player_id Player to attribute the notification to (0 = auto-detect)
     */
    function dbSetTokenLocation($token_id, $place_id, $state = null, $notif = "*", $args = [], int $player_id = 0) {
        if (is_array($token_id)) {
            $this->game->error("token_id is array " . toJson($token_id));
            $token_id = array_get($token_id, "key");
        }
        $this->game->systemAssert("token_id is null/empty $token_id, $place_id $notif", $token_id != null && $token_id != "");
        if ($notif === "*") {
            $notif = clienttranslate('${player_name} moves ${token_name} into ${place_name} ${reason}');
        }
        if ($state === null) {
            $state = $this->db->getTokenState($token_id) ?? 0;
        }
        $place_from = $this->db->getTokenLocation($token_id) ?? "limbo";
        $this->game->systemAssert("token_id does not exists, create first: $token_id", $place_from);
        if ($place_id === null) {
            $place_id = $place_from;
        }
        $this->db->moveToken($token_id, $place_id, $state);

        $notifyArgs = [
            "token_id" => $token_id,
            "place_id" => $place_id,
            "token_name" => $token_id,
            "place_name" => $place_id,
            "new_state" => $state,
            "place_from" => $place_from,
        ];

        if (str_contains($notif, '${token_div}')) {
            $notifyArgs["token_div"] = $token_id;
        }
        $args = array_merge($notifyArgs, $args);
        //$this->warn("$type $notif ".$args['token_id']." -> ".$args['place_id']."|");
        if ($player_id != 0) {
            // use it
        } elseif (array_key_exists("player_id", $args)) {
            $player_id = $args["player_id"];
        } else {
            $player_id = $this->game->getMostlyActivePlayerId();
        }

        $this->game->notifyWithName("tokenMoved", $notif, $args, $player_id);
        if ($this->isCounterAllowedForLocation($player_id, $place_from)) {
            $this->notifyCounterChanged($place_from, ["nod" => true]);
        }
        if ($place_id != $place_from && $this->isCounterAllowedForLocation($player_id, $place_id)) {
            $this->notifyCounterChanged($place_id, ["nod" => true]);
        }
    }

    /**
     * Sends tokenMove notification with multiple objects, parameters of notication (must be handled by tokenMove)
     * list - array of token ids
     * token_divs - comma separate list of tokens (to inject visualisation)
     * token_names - comma separate list of tokens (to inject names)
     * new_state - if same state - new state of all tokens
     * new_states - if multiple states array of integer states
     *
     * @param [] $token_arr
     *            - array of tokens keys or token info
     * @param string $place_id
     *            - location of all tokens will be set to $place_id value
     * @param null|int $state
     *            - if null is passed state won't be changed
     * @param string $notif
     * @param array $args
     */
    function dbSetTokensLocation($token_arr, $place_id, $state = null, $notif = "*", $args = [], $player_id = 0) {
        $type = $this->db->checkListOrTokenArray($token_arr);
        if ($type == 0) {
            return;
        }
        $this->game->systemAssert("place_id cannot be null", $place_id != null);
        if ($notif === "*") {
            $notif = clienttranslate('${player_name} moves ${token_names} into ${place_name} ${reason}');
        }
        $keys = [];
        $states = [];
        if (isset($args["place_from"])) {
            $place_from = $args["place_from"];
        } else {
            $place_from = null;
        }
        foreach ($token_arr as $token) {
            if (is_array($token)) {
                $token_id = $token["key"];
                $states[] = $token["state"];
                if ($place_from == null) {
                    $place_from = $token["location"];
                }
            } else {
                $token_id = $token;
            }
            $keys[] = $token_id;
        }
        $this->db->moveTokens($keys, $place_id, $state);
        $notifyArgs = [
            "list" => $keys, //
            "place_id" => $place_id, //
            "place_name" => $place_id,
        ];
        if ($state !== null) {
            $notifyArgs["new_state"] = $state;
        } elseif (count($states) > 0) {
            $notifyArgs["new_states"] = $states; // this only used for visualization, state won't change in db
        }
        if (strstr($notif, '${you}')) {
            $notifyArgs["you"] = "you"; // translated on client side, this is for replay after
        }
        if (strstr($notif, '${token_divs}')) {
            $notifyArgs["token_divs"] = implode(",", $keys);
        }
        if (strstr($notif, '${token_div}')) {
            $notifyArgs["token_div"] = $keys[0];
        }
        if (strstr($notif, '${token_names}')) {
            $notifyArgs["token_names"] = implode(",", $keys);
        }
        if (strstr($notif, '${token_name}')) {
            $notifyArgs["token_name"] = $keys[0];
        }
        $num = count($keys);
        if (strstr($notif, '${token_div_count}') || strstr($notif, '${count}')) {
            $notifyArgs["count"] = $num;
        }
        $notifyArgs["place_from"] = $place_from;
        $args = array_merge($notifyArgs, $args);
        //$this->warn("$type $notif ".$args['token_id']." -> ".$args['place_id']."|");
        if (!$player_id) {
            if (array_key_exists("player_id", $args)) {
                $player_id = $args["player_id"];
            } else {
                $player_id = $this->game->getMostlyActivePlayerId();
            }
        }
        $this->game->notifyWithName("tokenMoved", $notif, $args, $player_id);
        // send counter update if required
        if ($place_from && $this->isCounterAllowedForLocation($player_id, $place_from)) {
            $this->notifyCounterChanged($place_from, ["nod" => true]);
        }
        if ($place_id != $place_from && $this->isCounterAllowedForLocation($player_id, $place_id)) {
            $this->notifyCounterChanged($place_id, ["nod" => true]);
        }
    }

    /**
     * This method will increase/descrease resource counter (as state)
     *
     * @param string $token_id
     *            - token key
     * @param int $num
     *            - increment of the change
     * @param string $place
     *            - optional $place, only used in notification to show where "resource"
     *            is gain or where it "goes" when its paid, used in client for animation
     */
    function dbResourceInc($token_id, $num, $message = "*", $args = [], $player_id = null) {
        $current = $this->db->getTokenState($token_id, 0);
        $value = $current + $num;

        $this->db->setTokenState($token_id, $value);

        if ($message == "*") {
            if ($num <= 0) {
                $message = clienttranslate('${player_name} pays ${token_div} x ${absInc} ${reason}');
            } else {
                $message = clienttranslate('${player_name} gains ${token_div} x ${absInc} ${reason}');
            }
        }

        $args = array_merge($args, [
            "inc" => $num,
            "absInc" => abs($num),
            "token_div" => $token_id,
        ]);

        $this->notifyCounterDirect($token_id, $value, $message, $args, $player_id);
        return $value;
    }

    function notifyCounterChanged($location, $notifyArgs = null) {
        $key = $this->counterNameOf($location);
        $value = $this->db->countTokensInLocation($location);
        $this->notifyCounterDirect($key, $value, "", $notifyArgs);
    }

    function notifyCounterDirect($key, $value, $message, $notifyArgs = null, $player_id = null) {
        $args = ["name" => $key, "value" => $value];
        if ($notifyArgs != null) {
            $args = array_merge($notifyArgs, $args);
        }
        $this->game->notifyWithName("counter", $message, $args, $player_id);
    }

    function getTrackerValue(?string $color, string $type): int {
        $value = (int) $this->db->getTokenState($this->getTrackerId($color, $type));
        return $value;
    }
    function getTrackerIdAndValue(?string $color, string $type, ?array &$arr = null) {
        $id = $this->getTrackerId($color, $type);
        $value = (int) $this->db->getTokenState($id);
        if ($arr) {
            $arr[$id] = $value;
        }
        return [$id, $value];
    }

    function getTrackerId(string $color, string $type) {
        if ($color === "") {
            $token_id = "tracker_{$type}";
        } else {
            if (!$color) {
                $color = $this->game->getActivePlayerColor();
            }
            $token_id = "tracker_{$type}_{$color}";
        }
        return $token_id;
    }

    function notifyMessageWithTokenName($message, $card_id, $player_color = null, $args = []) {
        if (is_array($card_id)) {
            $card_id = $card_id["token_key"];
        }
        $args["token_name"] = $card_id;
        return $this->game->notifyMessage($message, $args, $this->game->custom_getPlayerIdByColor($player_color));
    }

    function getTokensOfTypeInLocation($type, $location = null, $state = null, $order_by = null) {
        return $this->db->getTokensOfTypeInLocation($type, $location, $state, $order_by);
    }

    function getTokensOfTypeInLocationWithChildren($type, $location = null, $state = null, $order_by = null) {
        $tokens = $this->db->getTokensOfTypeInLocation($type, $location, $state, $order_by);
        // init children array
        foreach ($tokens as $key => $token) {
            $tokens[$key]["children"] = [];
        }
        $children = $this->db->getTokensOnLocations(array_keys($tokens));
        foreach ($children as $key => $child) {
            $parent = $child["location"];
            $tokens[$parent]["children"][$key] = $this->db->getTokenInfo($key);
        }
        return $tokens;
    }
}
