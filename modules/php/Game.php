<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * wayfarers implementation : © Alena Laskavaia <laskava@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * wayfarers.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate;

use Bga\GameFramework\NotificationMessage;
use Bga\GameFramework\UserException;
use Bga\Games\Fate\Common\HexMap;
use Bga\Games\Fate\Common\PGameTokens;
use Bga\Games\Fate\Db\DbMultiUndo;
use Bga\Games\Fate\Db\DbTokens;
use Bga\Games\Fate\OpCommon\AiOperation;
use Bga\Games\Fate\OpCommon\ComplexOperation;
use Bga\Games\Fate\OpCommon\OpMachine;
use Bga\Games\Fate\States\GameDispatch;

class Game extends Base {
    const GAME_STAGE = "game_stage";

    public static Game $instance;
    public OpMachine $machine;
    public Material $material;
    public PGameTokens $tokens;
    public HexMap $hexMap;
    public DbMultiUndo $dbMultiUndo;

    function __construct() {
        Game::$instance = $this;
        parent::__construct();
        self::initGameStateLabels([
            // "variant_solo_board" => 101,
        ]);

        $this->material = new Material();
        $this->machine = new OpMachine();
        $tokens = new DbTokens($this);
        $this->tokens = new PGameTokens($this, $tokens);
        $this->hexMap = new HexMap($this);
        $this->dbMultiUndo = new DbMultiUndo($this, "restorePlayerTables");

        $this->notify->addDecorator(function (string $message, array $args) {
            if (str_contains($message, '${reason}') && !isset($args["reason"])) {
                $args["reason"] = "";
            }
            return $args;
        });
    }

    /*
        setupGameTables:
        
        init all game tables (players and stats init in base class)
        called from setupNewGame
    */
    protected function setupGameTables() {
        $this->tokens->createTokens();
        $tokensDb = $this->tokens->db;
        // setup
        $pnum = $this->getPlayersNumber();
        $startingPlayer = $this->getFirstPlayer();
        //         Main Board Setup

        $this->game->tokens->dbSetTokenLocation(
            "rune_stone",
            "timetrack_1",
            1,
            clienttranslate('Rune Stone: time advances to step ${new_state} of ${max}'),
            [
                "max" => Material::TIME_TRACK_SHORT_LENGTH,
            ]
        );

        // Shuffle monster card decks
        $tokensDb->shuffle("deck_monster_yellow");
        $tokensDb->shuffle("deck_monster_red");

        // Remove excess town pieces based on player count (1p=4, 2p=6, 3p=8, 4p=10)
        $players = $this->loadPlayersBasicInfos();
        $pnum = count($players);
        $townPieceCount = 2 * $pnum + 2; // 1p=4, 2p=6, 3p=8, 4p=10
        for ($i = $townPieceCount; $i <= 9; $i++) {
            $tokensDb->moveToken("house_$i", "limbo");
        }

        // Player setup — heroes start at their CSV-defined hex positions
        $heroNo = 1;
        foreach ($players as $player_id => $player) {
            $color = $player["player_color"];
            $tokensDb->pickTokensForLocation(2, "supply_crystal_yellow", "tableau_{$color}");

            // Create all cards for this hero and place in appropriate decks
            $deckMap = [
                "hero" => "limbo",
                "ability" => "deck_ability_{$color}",
                "equip" => "deck_equip_{$color}",
                "event" => "deck_event_{$color}",
            ];
            foreach ($this->material->getTokensWithPrefix("card_") as $cardId => $info) {
                if (($info["hno"] ?? null) != $heroNo) {
                    continue;
                }
                $ctype = $info["ctype"];
                $location = $deckMap[$ctype] ?? "limbo";
                $count = $info["count"] ?? 1;
                $info["location"] = $location;
                $info["create"] = $count > 1 ? 2 : 1;
                $this->tokens->createTokenFromInfo($cardId, $info);
            }
            // Move starting cards to tableau
            $tokensDb->moveToken("card_hero_{$heroNo}_1", "tableau_{$color}");
            $tokensDb->moveToken("card_ability_{$heroNo}_3", "tableau_{$color}");
            $tokensDb->moveToken("card_equip_{$heroNo}_15", "tableau_{$color}");
            // Shuffle decks
            $tokensDb->shuffle("deck_ability_{$color}");
            $tokensDb->shuffle("deck_equip_{$color}");
            $tokensDb->shuffle("deck_event_{$color}");
            // Hero already at starting hex from material, no move needed
            $heroNo++;
        }
        // Move unused heroes to limbo
        for ($i = $heroNo; $i <= 4; $i++) {
            $tokensDb->moveToken("hero_$i", "limbo");
        }
        $color = $this->custom_getPlayerColorById($startingPlayer);
        $this->machine->queue("reinforcement", $color);
        $this->machine->queue("turn", $color);
        $this->customUndoSavepoint($startingPlayer, 1);
        return GameDispatch::class;
    }

    public function getDefaultStatValue(string $key, string $type): ?int {
        if (startsWith($key, "game_")) {
            return 0;
        } elseif ($key === "turns_number") {
            return 0;
        }
        return null;
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

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas(): array {
        $result = [];
        $result = parent::getAllDatas();

        $result = array_merge($result, $this->tokens->getAllDatas());

        // Add heroNo to each player
        foreach ($result["players"] as $player_id => &$pdata) {
            $heroTokenId = $this->getHeroTokenId($pdata["color"]); // "hero_N"
            $pdata["heroNo"] = (int) getPart($heroTokenId, 1);
        }
        unset($pdata);

        $gameStage = $this->tokens->db->getTokenState(Game::GAME_STAGE);
        $isGameEnded = $gameStage >= 5;
        $result["gameEnded"] = $isGameEnded;
        $result["lastTurn"] = $gameStage >= 1 && $gameStage <= 4;
        $result["endScores"] = $isGameEnded ? $this->getEndScores() : null;

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
        return 0; // TODO impement
    }

    function isEndOfGame() {
        $currentStep = $this->tokens->db->getTokenState("rune_stone");
        $maxSteps = Material::TIME_TRACK_SHORT_LENGTH;

        if ($currentStep >= $maxSteps) {
            return true;
        }
        // Loss condition: Freyja's Well destroyed
        if (!$this->isHeroesWin()) {
            return true;
        }
        return false;
    }

    function isHeroesWin() {
        // Heroes win if at least Freyja's Well remains in Grimheim
        $wellLoc = $this->tokens->db->getTokenLocation("house_0");
        return $wellLoc === "hex_9_9";
    }

    function getUserPreference(int $player_id, int $code): int {
        return (int) $this->userPreferences->get($player_id, $code);
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////

    function effect_incCount(string $color, string $type, int $inc = 1, string $reason, array $options = []) {
        $message = array_get($options, "message", "*");
        unset($options["message"]);

        $token_id = $this->tokens->getTrackerId($color, $type);

        $value = $this->tokens->dbResourceInc(
            $token_id,
            $inc,
            $message,
            ["reason" => $reason, "place_from" => $reason] + $options,
            $this->custom_getPlayerIdByColor($color)
        );

        if ($value < 0 && $inc < 0) {
            $this->userAssert(clienttranslate("Insufficient resources to pay"));
        }
    }

    function effect_moveCrystals(string $color, string $type, int $inc = 1, string $location, array $options = []) {
        $message = array_get($options, "message", "*");
        unset($options["message"]);

        if ($inc == 0) {
            return;
        }

        if ($inc > 0) {
            $tokens = $this->tokens->db->pickTokensForLocation($inc, "supply_crystal_$type", $location);
            // TODO: unlimite? create more if needed
            $this->tokens->dbSetTokensLocation($tokens, $location);
        } else {
            $tokens = $this->tokens->db->pickTokensForLocation($inc, $location, "supply_crystal_$type");
            $this->tokens->dbSetTokensLocation($tokens, $location);
            if (count($tokens) < $inc) {
                throw new UserException(
                    new NotificationMessage(clienttranslate('Insufficient resources to pay: ${res_name"}'), [
                        "res_name" => $this->getTokenName("crystal_$type"),
                    ])
                );
            }
        }
    }

    function effect_incVp(string $owner, int $inc, string $stat = "", string $target = "") {
        $player_id = $this->custom_getPlayerIdByColor($owner);

        if ($target) {
            if ($inc < 0) {
                $message = clienttranslate('${player_name} loses ${absInc} VP for ${token_name} ${reason}');
            } else {
                // if 0 print gain 0
                $message = clienttranslate('${player_name} gains ${absInc} VP for ${token_name} ${reason}');
            }
        } else {
            if ($inc < 0) {
                $message = clienttranslate('${player_name} loses ${absInc} VP ${reason}');
            } else {
                // if 0 print gain 0
                $message = clienttranslate('${player_name} gains ${absInc} VP ${reason}');
            }
        }

        $this->playerScore->inc(
            $player_id,
            $inc,
            new NotificationMessage($message, [
                "reason" => $stat,
                "target" => $target,
                "token_name" => $target,
            ])
        );

        if ($stat) {
            $this->playerStats->inc($stat, $inc, $player_id);
        }
    }

    function getVariantSoloBoard() {
        return (int) $this->getGameStateValue("variant_solo_board");
    }

    /**
     * Returns the hero miniature token id for the current operation owner.
     * Looks up which card_hero_N_M is on the player's tableau, then returns hero_N.
     */
    function getHeroTokenId(string $owner): string {
        $heroCardKey = $this->game->tokens->db->getTokensOfTypeInLocationSingleKey("card_hero", "tableau_$owner");
        $this->systemAssert("No hero card found", $heroCardKey);

        // card_hero_1_1 → hero_1
        $heroNo = getPart($heroCardKey, 2); // card_hero_<heroNo>_<num>
        return "hero_$heroNo";
    }

    function getRulesFor($token_id, $field = "r", $default = "") {
        return $this->material->getRulesFor($token_id, $field, $default);
    }
    function getRulesForAndAssert($token_id, $field = "r", $default = "") {
        $r = $this->material->getRulesFor($token_id, $field, $default);
        $this->systemAssert("Expected non empty rule for for $token_id:$field", $r);
        return $r;
    }
    function getTokenName($token_id, $default = "") {
        if (!$default) {
            $default = "$token_id ?";
        }

        $name = $this->material->getRulesFor($token_id, "name", $default);

        return $name;
    }

    function getTrackerIdAndValue(?string $color, string $type, ?array &$arr = null) {
        return $this->tokens->getTrackerIdAndValue($color, $type, $arr);
    }

    function getEndScores(): array {
        $endScores = [];
        $players = $this->loadPlayersBasicInfos();
        $vp_stats = ["game_vp_tags", "game_vp_sets", "game_vp_space", "game_vp_insp", "game_vp_caravan", "game_vp_guilds"];

        foreach ($players as $player_id => $player) {
            foreach ($vp_stats as $stat) {
                $endScores[$player_id][$stat] = $this->playerStats->get($stat, $player_id);
            }
            $endScores[$player_id]["total"] = $this->playerStats->get("game_vp_total", $player_id);
        }

        return $endScores;
    }

    /**
     * Queue the next player's turn, or monster turn if all players have gone.
     * After monster turn, the first player starts the next round.
     *
     * Round structure: Player 1 turn → Player 2 turn → ... → Monster turn → repeat
     */
    function queueNextTurnOrEnd(int $playerId): void {
        // Game already ended
        if ($this->isEndOfGame()) {
            return;
        }

        $nextPlayerId = $this->getNextReadyPlayerId($playerId);
        $firstPlayerId = $this->getFirstPlayer();

        if ($nextPlayerId === $firstPlayerId) {
            // All players have taken their turn — queue monster turn
            // turnMonster will queue the next round's first player turn (or end the game)
            $this->machine->queue("turnMonster", $this->getAutomaColor());
        } else {
            // More players still need to take their turn this round
            $this->machine->queue("turn", $this->custom_getPlayerColorById($nextPlayerId));
        }
    }

    public function customUndoSavepoint(int $player_id, int $barrier = 0, string $label = "undo"): void {
        $this->debugLog("customUndoSavepoint $player_id bar= $barrier");
        if ($this->isMultiActive()) {
            $this->dbMultiUndo->doSaveUndoSnapshot(["barrier" => $barrier, "label" => $label], $player_id, true);
        } else {
            $this->dbMultiUndo->doSaveUndoSnapshot(["barrier" => $barrier, "label" => $label], $player_id, true);
            $this->undoSavepoint();
        }
    }

    function restorePlayerTables($table, $saved_data, $meta) {
        // TODO
        return false;
    }

    function debug_op(string $type) {
        $color = $this->getPlayerColorById((int) $this->getCurrentPlayerId());
        $this->machine->push($type, $color);
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    }

    function debug_game_variant(string $type = "variant_multi", int $value = 1) {
        $this->setGameStateValue($type, $value);
    }
    // /**
    //  * Example of debug function.
    //  * Here, jump to a state you want to test (by default, jump to next player state)
    //  * You can trigger it on Studio using the Debug button on the right of the top bar.
    //  */
    // public function debug_goToState(int $state = 3) {
    //     $this->gamestate->jumpToState($state);
    // }

    /**
     * Another example of debug function, to easily test the zombie code.
     */
    // public function debug_playAutomatically(int $moves = 1) {
    //     $count = 0;
    //     while (intval($this->gamestate->getCurrentMainStateId()) < 99 && $count < $moves) {
    //         $count++;
    //         foreach ($this->gamestate->getActivePlayerList() as $playerId) {
    //             $playerId = (int) $playerId;
    //             $this->gamestate->runStateClassZombie($this->gamestate->getCurrentState($playerId), $playerId);
    //         }
    //     }
    // }
    // public function debug_playAutomatically1() {
    //     return $this->debug_playAutomatically(1);
    // }

    function debug_maxRes() {
        $color = $this->getPlayerColorById((int) $this->getCurrentPlayerId());

        // $this->machine->push("5food", $color);
        // $this->machine->push("5coin", $color);

        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    }

    function debug_setupGameTables() {
        $this->DbQuery("DELETE FROM token");
        $this->DbQuery("DELETE FROM machine");
        $this->DbQuery("DELETE FROM multiundo");
        $this->DbQuery("DELETE FROM `stats`");
        $this->DbQuery("DELETE FROM `gamelog`");
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
        $this->setupGameTables();
        //$newGameDatas = $this->getAllTableDatas(); // this is framework function
        //$this->notify->player($this->getActivePlayerId(), "resetInterfaceWithAllDatas", "", $newGameDatas); // this is notification to reset all data
        $this->notify->all("message", "setup is done", []);
        $this->notify->all("undoRestorePoint", "", []);
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    }

    function debug_monster() {
        // Place monsters on the map for visual testing
        // Trollkin faction
        $this->tokens->dbSetTokenLocation("monster_goblin_1", "hex_7_7");
        $this->tokens->dbSetTokenLocation("monster_goblin_2", "hex_12_6");
        $this->tokens->dbSetTokenLocation("monster_goblin_3", "hex_5_10");
        $this->tokens->dbSetTokenLocation("monster_brute_1", "hex_6_8");
        $this->tokens->dbSetTokenLocation("monster_brute_2", "hex_11_5");
        $this->tokens->dbSetTokenLocation("monster_troll_1", "hex_4_9");
        $this->tokens->dbSetTokenLocation("monster_troll_2", "hex_13_7");
        // Fire Horde faction
        $this->tokens->dbSetTokenLocation("monster_sprite_1", "hex_8_3");
        $this->tokens->dbSetTokenLocation("monster_sprite_2", "hex_13_5");
        $this->tokens->dbSetTokenLocation("monster_elemental_1", "hex_6_4");
        $this->tokens->dbSetTokenLocation("monster_elemental_2", "hex_14_6");
        $this->tokens->dbSetTokenLocation("monster_jotunn_1", "hex_4_7");
        $this->tokens->dbSetTokenLocation("monster_jotunn_2", "hex_12_9");
        // Dead faction
        $this->tokens->dbSetTokenLocation("monster_imp_1", "hex_3_6");
        $this->tokens->dbSetTokenLocation("monster_imp_2", "hex_14_4");
        $this->tokens->dbSetTokenLocation("monster_skeleton_1", "hex_5_5");
        $this->tokens->dbSetTokenLocation("monster_skeleton_2", "hex_12_3");
        $this->tokens->dbSetTokenLocation("monster_draugr_1", "hex_7_4");
        $this->tokens->dbSetTokenLocation("monster_draugr_2", "hex_11_8");
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    }

    function debug_houses() {
        // Move a couple houses out of Grimheim to test removal visuals
        $this->tokens->dbSetTokenLocation("house_0", "hex_8_9");
        $this->tokens->dbSetTokenLocation("house_2", "hex_8_9");
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    }

    function debug_monsterCards() {
        // Place a few monster cards on display_monsterturn for visual testing
        $this->tokens->dbSetTokenLocation("card_monster_28", "display_monsterturn", 0); // Flanking (yellow)
        $this->tokens->dbSetTokenLocation("card_monster_36", "display_monsterturn", 0); // Viral Trolls (yellow)
        $this->tokens->dbSetTokenLocation("card_monster_22", "display_monsterturn", 1); // Imp-ressive Swarm (skipped)
        $this->tokens->dbSetTokenLocation("card_monster_43", "display_monsterturn", 0); // Feed the Flames (red)
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    }

    function debug_heroCards(int $hno = 1) {
        // Place all cards for hero $hno on the current player's tableau for visual testing
        // Creates cards if they don't exist (e.g. testing a hero nobody is playing)
        $color = $this->getPlayerColorById((int) $this->getCurrentPlayerId());
        foreach ($this->material->getTokensWithPrefix("card_") as $cardId => $info) {
            if (($info["hno"] ?? null) != $hno) {
                continue;
            }
            $tokens = $this->tokens->db->getTokensOfTypeInLocation($cardId, null);
            if (!$tokens) {
                // Card doesn't exist yet — create it (count>1 needs indexed tokens)
                $count = $info["count"] ?? 1;
                $info["location"] = "tableau_{$color}";
                $info["create"] = $count > 1 ? 2 : 1;
                $this->tokens->createTokenFromInfo($cardId, $info);
            } else {
                $firstKey = array_key_first($tokens);
                $this->tokens->dbSetTokenLocation($firstKey, "tableau_{$color}", 0, "", ["noa" => true]);
            }
        }
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    }

    function debug_dumpMachineDb() {
        $t = $this->machine->gettablearr();
        $this->debugLog("all stack " . ($t[0]["type"] ?? "halt"), $t);
        return $t;
    }
    function debugConsole($info, $args = []) {
        $this->notify->all("log", $info, $args);
        $this->warn($info);
    }
    function debugLog($info, $args = []) {
        $this->notify->all("log", "", ["log" => $info, "args" => $args]);
        //$this->warn($info . ": " . toJson($args));
    }

    function debug_eval(string $x) {
        $color = $this->getPlayerColorById((int) $this->getCurrentPlayerId());
        $v = $this->evaluateExpression($x, $color);
        $this->notify->all("log", "result: $v");
    }
}
