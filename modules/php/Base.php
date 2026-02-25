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
 * fate.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate;

use Bga\GameFramework\NotificationMessage;
use Bga\GameFramework\SystemException;
use Bga\GameFramework\Table;
use Bga\GameFramework\UserException;
use Bga\Games\Fate\OpCommon\MathExpression;
use Bga\Games\Fate\OpCommon\OpMachine;

use Exception;
use ReflectionMethod;

class Base extends Table {
    const PLAYER_AUTOMA = 1;

    public OpMachine $machine;
    public Material $material;
    protected array $player_colors;
    protected Game $game;

    function __construct() {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        $this->game = $this;

        //self::initGameStateLabels([
        //    "my_first_global_variable" => 10,
        //    "my_second_global_variable" => 11,
        //      ...
        //    "my_first_game_variant" => 100,
        //    "my_second_game_variant" => 101,
        //      ...
        //]);
        $this->notify->addDecorator(function (string $message, array $args) {
            if (!isset($args["player_id"]) && str_contains($message, '${player_name}')) {
                $args["player_id"] = $this->getMostlyActivePlayerId();
            }
            if (isset($args["player_id"]) && !isset($args["player_name"]) && str_contains($message, '${player_name}')) {
                $args["player_name"] = $this->custom_getPlayerNameById((int) $args["player_id"]);
            }
            if (str_contains($message, '${you}')) {
                $args["you"] = "You"; // translated on client side, this is for replay after
            }
            return $args;
        });
    }

    function getAvailColors($players) {
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos["player_colors"];
        if (count($players) == 1) {
            unset($default_colors[count($default_colors) - 1]); // last one will be reserved to automa
        }
        return $default_colors;
    }
    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame($players, $options = []) {
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams

        $default_colors = $this->getAvailColors($players);
        shuffle($default_colors);

        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = [];
        foreach ($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $values[] =
                "('" .
                $player_id .
                "','$color','" .
                $player["player_canal"] .
                "','" .
                addslashes($player["player_name"]) .
                "','" .
                addslashes($player["player_avatar"]) .
                "')";
        }
        $sql .= implode(",", $values);
        $this->DbQuery($sql);
        $default_colors = $this->getAvailColors($players);
        self::reattributeColorsBasedOnPreferences($players, $default_colors);
        self::reloadPlayersBasicInfos();

        /************ Start the game initialization *****/

        // Init global values with their initial values
        //self::setGameStateInitialValue( 'my_first_global_variable', 0 );

        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        //self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
        //self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)

        // Activate first player (which is in general a good idea :) )
        $player_ids = array_keys($players);
        shuffle($player_ids);
        $startingPlayer = array_shift($player_ids);
        $this->gamestate->changeActivePlayer($startingPlayer);

        $this->initStats();
        // Setup the initial game situation here
        return $this->setupGameTables();
        /**
         * ********** End of the game initialization ****
         */
    }
    public function initStats() {
        // INIT GAME STATISTIC
        $all_stats = $this->getStatTypes();
        $player_stats = $all_stats["player"];
        // auto-initialize all stats that starts with game_
        // we need to filter it because there is some other system stuff
        foreach ($player_stats as $key => $value) {
            $v = $this->getDefaultStatValue($key, "player");
            if ($v !== null) {
                $this->playerStats->init($key, $v);
            }
        }
        $table_stats = $all_stats["table"];
        foreach ($table_stats as $key => $value) {
            $v = $this->getDefaultStatValue($key, "table");
            if ($v !== null) {
                $this->tableStats->init($key, $v);
            }
        }
    }

    public function getDefaultStatValue(string $key, string $type): ?int {
        if (startsWith($key, "game_")) {
            return 0;
        } elseif ($key === "turns_number") {
            return 0;
        }
        return null;
    }

    public function debug_dumpStats() {
        $all_stats = $this->getStatTypes();
        $player_stats = $all_stats["player"];

        $players = $this->loadPlayersBasicInfosWithBots();

        foreach ($players as $player_id => $player) {
            foreach ($player_stats as $key => $value) {
                $stat = $this->playerStats->get($key, $player_id);
                $this->notify->all("message", "$key=$stat");
            }
        }

        $table_stats = $all_stats["table"];
        foreach ($table_stats as $key => $value) {
            $stat = $this->tableStats->get($key);
            $this->notify->all("message", "$key=$stat");
        }
    }

    /**
     * override to setup all custom tables
     */
    protected function setupGameTables() {}

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas(): array {
        $result = ["players" => []];

        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result["players"] = self::getCollectionFromDb($sql);

        //$result["CON"] = $this->game->getPhpConstants("MA_");

        // Get information about players
        // Note: this is needed because basic does not have the score

        $players = $this->loadPlayersBasicInfos();

        foreach ($players as $player_id => $player) {
            foreach ($player as $pkey => $value) {
                $key = str_replace("player_", "", $pkey);
                $result["players"][$player_id][$key] = $value;
            }
        }

        $table_options = $this->getTableOptions(); // aka Game options defined in gameoptions.json
        $result["table_options"] = [];
        foreach ($table_options as $option_id => $option) {
            $value = $this->tableOptions->get($option_id) ?? ($option["default"] ?? 0);
            $result["table_options"][$option_id] = $option;
            $result["table_options"][$option_id]["value"] = $value;
        }
        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression() {
        return 0;
    }

    function isEndOfGame() {
        return false;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////
    /*
     * In this space, you can put any utility methods useful for your game logic
     */

    function isSolo() {
        return $this->getPlayersNumber() == 1;
    }
    function getMostlyActivePlayerId() {
        if ($this->isMultiActive()) {
            $list = $this->gamestate->getActivePlayerList();
            if (count($list) > 0) {
                return $list[0];
            }
            return 0;
        } else {
            return $this->getActivePlayerId();
        }
    }

    function getActivePlayerColor() {
        $id = $this->getActivePlayerId();
        if ($id === null || $id <= 0) {
            return "000000";
        }
        return $this->custom_getPlayerColorById((int) $id);
    }

    function getAutomaColor() {
        return "ffffff"; // different color for php but in UI it will be purple 982fff
    }

    function custom_getPlayerColorById(int $p): string {
        if ($p == self::PLAYER_AUTOMA) {
            return $this->getAutomaColor();
        }
        return parent::getPlayerColorById($p);
    }

    function custom_getPlayerNameById(int $p): string {
        if ($p == self::PLAYER_AUTOMA) {
            return "Monsters";
        }
        return $this->getPlayerNameById($p);
    }

    function custom_getPlayerNoById(int $p): int {
        if ($p == self::PLAYER_AUTOMA) {
            return 2;
        }
        return (int) $this->getPlayerNoById($p);
    }

    public function isMultiActive() {
        return $this->gamestate->isMultiactiveState();
    }

    function isRealPlayer($player_id) {
        if ($player_id == 0 || $player_id == 1) {
            return false;
        }
        $players = $this->loadPlayersBasicInfos();
        return isset($players[$player_id]);
    }
    function isPlayerEliminated($player_id) {
        $players = $this->loadPlayersBasicInfos();
        if (isset($players[$player_id])) {
            return $players[$player_id]["player_eliminated"] == 1;
        }
        return false;
    }
    function isZombiePlayer($player_id) {
        $players = $this->loadPlayersBasicInfos();
        if (isset($players[$player_id])) {
            if ($players[$player_id]["player_zombie"] == 1) {
                return true;
            }
        }
        return false;
    }

    function isPlayerAlive($player_id) {
        return $this->isRealPlayer($player_id) && !$this->isPlayerEliminated($player_id) && !$this->isZombiePlayer($player_id);
    }
    /**
     *
     * @return integer first player in natural player order
     */
    function getFirstPlayer() {
        $table = $this->getNextPlayerTable();
        return $table[0];
    }
    function getNextReadyPlayerId($player_id): int {
        if ($this->isSolo()) {
            if ($player_id == self::PLAYER_AUTOMA) {
                return $this->getFirstPlayer();
            } else {
                return self::PLAYER_AUTOMA;
            }
        }
        $this->systemAssert("invalid player id $player_id", $this->isRealPlayer($player_id));
        $player_id = $this->getPlayerAfter($player_id);
        return $player_id;
    }

    /**
     *
     * @return array of player ids
     */
    function getPlayerIds() {
        $players = $this->loadPlayersBasicInfos();
        return array_keys($players);
    }

    function getPlayerIdsInOrder($starting) {
        $player_ids = $this->getPlayerIds();
        $rotate_count = array_search($starting, $player_ids);
        if ($rotate_count === false) {
            return $player_ids;
        }
        for ($i = 0; $i < $rotate_count; $i++) {
            array_push($player_ids, array_shift($player_ids));
        }
        return $player_ids;
    }

    function loadPlayersBasicInfosWithBots($bots = true) {
        $infos = parent::loadPlayersBasicInfos();
        if ($bots && $this->isSolo()) {
            $infos[self::PLAYER_AUTOMA]["player_id"] = self::PLAYER_AUTOMA;
            $infos[self::PLAYER_AUTOMA]["player_no"] = 2;
            $infos[self::PLAYER_AUTOMA]["player_color"] = $this->getAutomaColor();
            $infos[self::PLAYER_AUTOMA]["player_name"] = "Monsters";
            $infos[self::PLAYER_AUTOMA]["player_ai"] = 1;
            $infos[self::PLAYER_AUTOMA]["player_score"] = 0;
        }
        return $infos;
    }
    public function getPlayerColors() {
        $players_basic = $this->loadPlayersBasicInfos();
        $colors = [];
        foreach ($players_basic as $player_id => $player_info) {
            $colors[] = $player_info["player_color"];
        }
        return $colors;
    }

    /**
     *
     * @return integer player id based on hex $color, player is not in the list return 0
     */
    function custom_getPlayerIdByColor(?string $color): int {
        if ($color === null) {
            return 0;
        }

        $players = $this->loadPlayersBasicInfosWithBots();
        if (!isset($this->player_colors)) {
            $this->player_colors = [];
            foreach ($players as $player_id => $info) {
                $this->player_colors[$info["player_color"]] = $player_id;
            }
        }
        if (!isset($this->player_colors[$color])) {
            return 0;
        }
        return (int) $this->player_colors[$color];
    }

    /**
     * This will throw an exception if condition is false.
     * The message should be translated and shown to the user.
     *
     * @param $message string or NotificationMessage
     *            user side error message, translation is needed, use clienttranslate() when passing string to it (because it needs to be marked but this method will wrap it into _)
     * @param $cond boolean
     *            condition of assert

     * @throws UserException
     */
    function userAssert(string|NotificationMessage $message, $cond = false) {
        if ($cond) {
            return;
        }

        throw new UserException($message);
    }

    /**
     * This will throw an exception if condition is false.
     * This only can happened if user hacks the game, client must prevent this
     *
     * @param string $log
     *            server side log message, no translation needed
     * @param bool $cond
     *            condition of assert
     * @throws UserException
     */
    function systemAssert($log, $cond = false, ?string $logonly = null) {
        if ($cond) {
            return;
        }
        $this->dumpError($log);
        if ($logonly) {
            $this->error($logonly);
        }
        throw new UserException("Internal Error. That should not have happened. Reload page and Retry" . " " . $log);
    }

    function dumpError($log) {
        $move = $this->getNextMoveId();
        $this->error("Internal Error during move $move: $log.");
        $e = new Exception($log);
        $this->error($e->getTraceAsString());
    }

    function getNextMoveId() {
        //getGameStateValue does not work when dealing with undo, have to read directly from db
        $next_move_index = 3;
        $subsql = "SELECT global_value FROM global WHERE global_id='$next_move_index' ";
        $move_id = $this->getUniqueValueFromDB($subsql);
        return (int) $move_id;
    }

    // ------ NOTIFICATIONS ----------
    /**
     * Advanced notification, which does more work on parameters
     * 1) If player id is not set it will try to determine it
     * 2) If player_id is set or passed via args it will also add player_name
     * 3) Auto add i18n tag to for all keys if they ends with _name or _tr
     * 4) Auto add preserve tag if keys end with _preserve
     * 5) If _previte is set true in args - send as private, otherwise sends to all players
     * 6) Can also pass _notifType via $args insterad of $type if needed
     * 7) Can add special animation params via args:
     * 'nod'=>true // no delay
     * 'noa'=>true // no animation
     * 'nop'=>true // ignore
     * If any of these parameters passed the type will change to be "${type}Async"
     * - which should be supported on clinet as asyncronious notification
     */
    function notifyWithName($type, $message = "", $args = null, $player_id = 0) {
        if ($args == null) {
            $args = [];
        }
        $this->systemAssert("Invalid notification signature", is_array($args));
        $this->systemAssert("Invalid notification signature", is_string($message));
        if (array_key_exists("player_id", $args) && !$player_id) {
            $player_id = $args["player_id"];
        }
        if (!$player_id) {
            $player_id = $this->getMostlyActivePlayerId();
        }
        $args["player_id"] = $player_id;
        if ($message) {
            // automaticaly add to i18n array all keys if they ends with _name or _tr, except reserved which are auto-translated on client side
            $i18n = array_get($args, "i18n", []);
            foreach ($args as $key => $value) {
                if (
                    is_string($value) &&
                    is_string($key) &&
                    (endsWith($key, "_tr") ||
                        (endsWith($key, "_name") && $key != "player_name" && $key != "token_name" && $key != "place_name"))
                ) {
                    $i18n[] = $key;
                }
            }
            if (count($i18n) > 0) {
                $args["i18n"] = $i18n;
            }
        }
        if ($message) {
            $player_name = $this->custom_getPlayerNameById((int) $player_id);
            $args["player_name"] = $player_name;
        }
        if (isset($args["_notifType"])) {
            $type = $args["_notifType"];
            unset($args["_notifType"]);
        }
        $this->systemAssert("Invalid notification signature", is_string($type));
        if (array_key_exists("noa", $args) || array_key_exists("nop", $args) || array_key_exists("nod", $args)) {
            $type .= "Async";
        }
        // automaticaly add to preserve array all keys if they ends with _preserve
        $preserve = array_get($args, "preserve", []);
        foreach ($args as $key => $arg) {
            if (is_string($arg) && endsWith($key, "_preserve")) {
                $preserve[] = $key;
            }
            if ($key == "reason_tr") {
                $preserve[] = $key;
            }
        }
        if (count($preserve) > 0) {
            $args["preserve"] = $preserve;
        }
        $private = false;
        if (array_key_exists("_private", $args)) {
            $private = $args["_private"];
            unset($args["_private"]);
        }
        if ($private) {
            $this->notify->player($player_id, $type, $message, $args);
        } else {
            $this->notify->all($type, $message, $args);
        }
    }

    function notifyMessage($message, $args = [], $player_id = 0) {
        $this->notifyWithName("message", $message, $args, $player_id);
    }

    function isStudio() {
        return $this->getBgaEnvironment() == "studio";
    }

    function getPrivateStateId($player_id): int {
        return (int) $this->getUniqueValueFromDB("SELECT player_state FROM player WHERE player_id = $player_id");
    }

    function switchActivePlayer(int $playerId, bool $moreTime = true) {
        if ($playerId <= 2) {
            return;
        }

        if (!$this->gamestate->isPlayerActive($playerId)) {
            if ($this->gamestate->isMultiactiveState()) {
                $this->gamestate->setPlayersMultiactive([$playerId], "notpossible", false);
            } else {
                $this->gamestate->changeActivePlayer($playerId);
            }
            if ($moreTime) {
                $this->giveExtraTime($playerId);
            }
        }
    }

    function evaluateExpression(mixed $cond, $owner = 0, $context = null, $options = null): int {
        try {
            $cond = (string) $cond;
            if (!$owner) {
                $owner = $this->getActivePlayerColor();
            }
            if (strlen($cond) > 80) {
                throw new SystemException("Parse expression is too long '$cond'");
            }
            $expr = MathExpression::parse($cond);
            $mapper = function ($x) use ($owner, $context, $options) {
                return $this->evaluateTerm($x, $owner, $context, $options);
            };
            return $expr->evaluate($mapper);
        } catch (Exception $e) {
            $this->error(toJson($e));
            throw new SystemException("Cannot evaluate math expression '$cond'");
        }
    }

    function evaluateTerm($x, $owner, $context = null, ?array $options = null) {
        if (startsWith($x, "count") && strlen($x) > 6) {
            $method = new ReflectionMethod(get_class($this), "$x");
            if (!$method) {
                throw new Exception("Uknown term $x");
            }
            return $method->invoke($this, $owner, $context, $options);
        } else {
            throw new Exception("Uknown term $x");
        }
        return 0;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Undo
    ////////////
    protected $undoSaveOnMoveEndDup = false;

    /*
     * @Override
     * - have to override to track second copy of var flag as original one is private
     */
    public function undoSavepoint(): void {
        //parent::undoSavepoint(); // do not set the original flag - it cannot be unset
        $this->setUndoSavepoint(true);
        //$this->statelog("undoSavepoint");
    }

    function setUndoSavepoint(bool $value) {
        $this->undoSaveOnMoveEndDup = $value;
    }

    function isUndoSavepoint() {
        return $this->undoSaveOnMoveEndDup;
    }

    /*
     * @Override
     * - I had to override this not fail in multiactive, it will just ignore it
     * - fixed resetting the save flag when its done
     */
    function doUndoSavePoint() {
        //$this->statelog("*** doUndoSavePoint *** " . $this->undoSaveOnMoveEndDup);
        if (!$this->isUndoSavepoint()) {
            return;
        }

        try {
            $this->doCustomUndoSavePoint();
        } catch (Exception $e) {
            $this->error("undo save point failed " . $e->getMessage());
            $this->error($e->getTraceAsString());
        } finally {
            $this->setUndoSavepoint(false);
        }
    }

    function doCustomUndoSavePoint() {
        //$this->statelog("*** doCustomUndoSavePoint ***");
        if ($this->game->gamestate->isMultiactiveState()) {
            return;
        }

        $this->bgaDoUndoSavePoint();
    }

    /*
     * @Override
     * fixed bug where it does not save state if there is no notifications
     */
    function sendNotifications() {
        //$next = $this->getNextMoveId();
        parent::sendNotifications();
        if ($this->undoSaveOnMoveEndDup) {
            try {
                $this->doUndoSavePoint();
                // $this->setGameStateValue('next_move_id', $next); // restore next move so it does not increase
                // parent::sendNotifications(); // if any notif was produced by undo save point send them also
            } catch (Exception $e) {
                $this->error($e->getTraceAsString());
            }
        }
    }
    function dbGetFieldList(string $table) {
        $result = [];
        $fields = $this->game->getObjectListFromDB("SHOW COLUMNS FROM $table");
        foreach ($fields as $field) {
            $result[] = $field["Field"];
        }
        return $result;
    }

    function dbGetFieldListAsString(string $table) {
        $fields_list = $this->dbGetFieldList($table);
        $fields = "`" . implode("`,`", $fields_list) . "`";
        return $fields;
    }

    function bgaDoUndoSavePoint() {
        $tables = $this->getObjectListFromDB("SHOW TABLES", true);
        $this->setGameStateValue("undo_moves_player", (int) $this->getActivePlayerId());
        $prefix = "zz_savepoint_";
        foreach ($tables as $table) {
            if (str_starts_with($table, $prefix)) {
                continue;
            } elseif (str_starts_with($table, "zz_replay")) {
                continue;
            } elseif ($table == "replaysavepoint") {
                continue;
            } elseif ($table == "bga_user_preferences") {
                continue;
            } elseif ($table == "multiundo") {
                continue;
            } else {
                $copy = $prefix . $table;
                $this->DbQuery("DELETE FROM $copy");
                $fields = $this->dbGetFieldListAsString($table);
                $this->DbQuery("INSERT INTO $copy ($fields) SELECT $fields FROM $table");
            }
        }
    }
    //////////////////////////////////////////////////////////////////////////////
    //////////// Zombie
    ////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
    */

    function zombieTurn($state, $active_player) {
        throw new UserException("Zombie mode not supported at this game");
    }

    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// DB upgrade
    //////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */

    function upgradeTableDb($from_version) {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345

        // Example:
        //        if( $from_version <= 1404301345 )
        //        {
        //            $sql = "ALTER TABLE xxxxxxx ....";
        //            $this->DbQuery( $sql );
        //        }
        //        if( $from_version <= 1405061421 )
        //        {
        //            $sql = "CREATE TABLE xxxxxxx ....";
        //            $this->DbQuery( $sql );
        //        }
        //        // Please add your future database scheme changes here
        //
        //
    }
}
// GLOBAL utility functions
function startsWith($haystack, $needle) {
    if ($haystack === null) {
        throw new Exception("ee");
    }
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}

function endsWith($haystack, $needle) {
    if ($haystack === null) {
        return false;
    }
    $length = strlen($needle);
    return $length === 0 || substr($haystack, -$length) === $needle;
}

function custom_array_rotate(array $array, int $start_index, int $direction) {
    $keys = array_keys($array);
    $values = array_values($array);
    $count = count($array);

    if ($count == 0) {
        return $array;
    }

    $result = [];

    for ($i = 0; $i < $count; $i++) {
        // Calculate the source index with wrapping: direction 1 = forward, -1 = backward
        $source_index = ((($start_index + $i * $direction) % $count) + $count) % $count;
        $result[$keys[$source_index]] = $values[$source_index];
    }

    return $result;
}

function getPart($haystack, $i, $bNoexeption = false) {
    $parts = explode("_", $haystack);
    $len = count($parts);
    if ($bNoexeption && $i >= $len) {
        return "";
    }
    if ($i >= $len) {
        throw new SystemException("Access to $i >= $len for $haystack");
    }
    return $parts[$i];
}

function toJson($data, $options = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) {
    $json_string = json_encode($data, $options);
    return $json_string;
}

/**
 * Right unsigned shift
 */
function uRShift($a, $b = 1) {
    if ($b == 0) {
        return $a;
    }
    return ($a >> $b) & ~((1 << 8 * PHP_INT_SIZE - 1) >> $b - 1);
}

if (!function_exists("array_key_first")) {
    function array_key_first(array $arr) {
        foreach ($arr as $key => $unused) {
            return $key;
        }
        return null;
    }
}
if (!function_exists("array_get")) {
    /**
     * Get an item from an array using "dot" notation.
     * If item does not exists return default
     *
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function array_get($array, $key, $default = null) {
        if (is_null($key)) {
            return $array;
        }
        if (is_null($array)) {
            return $default;
        }
        if (!is_array($array)) {
            throw new SystemException("array_get first arg is not array");
        }
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        foreach (explode(".", $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }
        return $array;
    }
}
