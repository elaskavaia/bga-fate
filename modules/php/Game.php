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
    public DbTokens $tokens;
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
        $this->tokens = new DbTokens($this);
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
        $this->tokens->createAllTokens();
        // setup
        $pnum = $this->getPlayersNumber();
        $startingPlayer = $this->getFirstPlayer();
        //         Main Board Setup

        $this->game->tokens->dbSetTokenLocation(
            "rune_stone",
            "timetrack_1",
            1,
            clienttranslate('Rune Stone advances to step ${new_state} of ${max}'),
            [
                "max" => Material::TIME_TRACK_SHORT_LENGTH,
            ]
        );

        // Shuffle monster card decks
        $this->tokens->shuffle("deck_monster_yellow");
        $this->tokens->shuffle("deck_monster_red");

        // Remove excess town pieces based on player count (1p=4, 2p=6, 3p=8, 4p=10)
        $players = $this->loadPlayersBasicInfos();
        $pnum = count($players);
        $townPieceCount = 2 * $pnum + 2; // 1p=4, 2p=6, 3p=8, 4p=10
        for ($i = $townPieceCount; $i <= 9; $i++) {
            $this->tokens->moveToken("house_$i", "limbo");
        }

        // Player setup — heroes randomly assigned
        $heroNos = range(1, 4);
        shuffle($heroNos);
        $heroIdx = 0;
        foreach ($players as $player_id => $player) {
            $heroNo = $heroNos[$heroIdx++];
            $color = $player["player_color"];
            $this->tokens->pickTokensForLocation(2, "supply_crystal_yellow", "tableau_{$color}");

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
            $this->tokens->moveToken("card_hero_{$heroNo}_1", "tableau_{$color}");
            $this->tokens->moveToken("card_ability_{$heroNo}_3", "tableau_{$color}");
            $this->tokens->moveToken("card_equip_{$heroNo}_15", "tableau_{$color}");
            // Shuffle decks
            $this->tokens->shuffle("deck_ability_{$color}");
            $this->tokens->shuffle("deck_equip_{$color}");
            $this->tokens->shuffle("deck_event_{$color}");
            // Hero already at starting hex from material, no move needed
        }
        // Move unused heroes to limbo
        $usedHeros = array_slice($heroNos, 0, $heroIdx);
        for ($i = 1; $i <= 4; $i++) {
            if (!in_array($i, $usedHeros)) {
                $this->tokens->moveToken("hero_$i", "limbo");
            }
        }
        $color = $this->custom_getPlayerColorById($startingPlayer);
        $this->machine->queue("reinforcement", $color);
        $this->machine->queue("turn", $color);
        $this->customUndoSavepoint($startingPlayer, 1);
        return GameDispatch::class;
    }

    public function getDefaultStatValue(string $key, string $type): ?int {
        if (str_starts_with($key, "game_")) {
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
            $pdata["heroNo"] = $this->getHeroNumber($pdata["color"]);
        }
        unset($pdata);

        $gameStage = $this->tokens->getTokenState(Game::GAME_STAGE);
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
        $currentStep = $this->tokens->getTokenState("rune_stone");
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
        $wellLoc = $this->tokens->getTokenLocation("house_0");
        return $wellLoc === "hex_9_9";
    }

    function handleEndOfGame(): void {
        if ($this->isHeroesWin()) {
            $this->notify->all("message", clienttranslate("The time track has reached the end. Freyja returns! Heroes win!"), []);
        } else {
            $this->notify->all("message", clienttranslate("The Heroes have failed. The Monsters win!"), []);
        }
    }

    function getUserPreference(int $player_id, int $code): int {
        return (int) $this->userPreferences->get($player_id, $code);
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////

    function effect_moveCrystals(string $charId, string $type, int $inc, string $location, array $options = []) {
        $message = array_get($options, "message", clienttranslate('${char_name} gains ${count} ${token_name}'));
        unset($options["message"]);
        $options["char_name"] = $charId;

        if ($inc == 0) {
            return;
        }

        if ($inc > 0) {
            $tokens = $this->tokens->pickTokensForLocation($inc, "supply_crystal_$type", $location);
            // TODO: unlimited? create more if needed
            $this->tokens->dbSetTokensLocation($tokens, $location, 0, $message, $options);
        } else {
            $needed = abs($inc);
            $tokens = $this->tokens->pickTokensForLocation($needed, $location, "supply_crystal_$type");
            if (count($tokens) < $needed) {
                throw new UserException(
                    new NotificationMessage(clienttranslate('Insufficient resources to pay: ${res_name}'), [
                        "res_name" => $this->getTokenName("crystal_$type"),
                    ])
                );
            }
            $this->tokens->dbSetTokensLocation($tokens, "supply_crystal_$type", 0, $message, $options);
        }
    }

    /**
     * Returns the total attack strength for a hero: base hero card + equipment + abilities on tableau.
     * Hero card has an integer strength (e.g. 2), equipment/abilities use "+N" format.
     */
    function getHeroAttackStrength(string $owner): int {
        $cards = $this->tokens->getTokensOfTypeInLocation("card", "tableau_$owner");
        $total = 0;
        foreach ($cards as $card) {
            $cardId = $card["key"];
            $strength = $this->material->getRulesFor($cardId, "strength", "");
            if ($strength === "" || $strength === null) {
                continue;
            }
            $strength = (string) $strength;
            if (str_starts_with($strength, "+")) {
                $total += (int) substr($strength, 1);
            } else {
                $total += (int) $strength;
            }
        }
        return max($total, 0);
    }

    /**
     * Returns the attack range for any character (hero or monster). Default is 1.
     */
    function getAttackRange(string $characterId): int {
        if (str_starts_with($characterId, "hero")) {
            $owner = $this->getHeroOwner($characterId);
            $cards = $this->tokens->getTokensOfTypeInLocation("card", "tableau_$owner");
            $maxRange = 1;
            foreach ($cards as $card => $info) {
                $range = (int) $this->material->getRulesFor($card, "attack_range", 0);
                if ($range > $maxRange) {
                    $maxRange = $range;
                }
            }
            return $maxRange;
        }
        // Monster: Fire Horde faction has range 2
        $faction = $this->getRulesFor($characterId, "faction", "");
        if ($faction === "firehorde") {
            return 2;
        }
        return 1;
    }

    /**
     * Roll attack dice: announce the attack, clean up previous dice, roll, count hits and return hit count.
     * Used by both hero attacks and monster attacks.
     * @return int number of hits
     */
    function rollAttackDice(string $attackerId, string $defenderId, int $strength): int {
        $this->notifyMessage(clienttranslate('${token_name} attacks ${token_name2} with strength ${strength}'), [
            "token_name" => $attackerId,
            "token_name2" => $defenderId,
            "strength" => $strength,
        ]);

        // Forest hex provides cover — "hitcov" results are blocked
        $defenderHex = $this->hexMap->getCharacterHex($defenderId);
        $hasCover = $defenderHex !== null && $this->hexMap->getHexTerrain($defenderHex) === "forest";

        // Clean up any leftover dice on display from a previous attack
        $leftover = $this->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        if (count($leftover) > 0) {
            $leftoverKeys = array_map(fn($d) => $d["key"], $leftover);
            $this->tokens->dbSetTokensLocation($leftoverKeys, "supply_die_attack", 6, "");
        }

        // Roll attack dice — pick from supply, then notify each with its roll result
        $hits = 0;
        $diceTokens = $this->tokens->pickTokensForLocation($strength, "supply_die_attack", "display_battle");
        foreach ($diceTokens as $die) {
            $dieId = $die["key"];
            $roll = $this->bgaRand(1, 6);
            $sideName = $this->material->getRulesFor("side_die_attack_$roll", "name", "?");
            $this->tokens->dbSetTokenLocation(
                $dieId,
                "display_battle",
                $roll,
                clienttranslate('${token_name} attacks ${token_name2} - ${side_name}'),
                [
                    "token_name" => $attackerId,
                    "token_name2" => $defenderId,
                    "side_name" => $sideName,
                    "anim_target" => $defenderId,
                ]
            );
            $rule = $this->material->getRulesFor("side_die_attack_$roll", "rule", "miss");
            if ($rule === "hit" || ($rule === "hitcov" && !$hasCover)) {
                $hits++;

                // Place red crystals on the monster token
                $this->effect_moveCrystals($defenderId, "red", 1, $defenderId, ["message" => ""]);
            }
            // TODO: handle rune effects (some cards trigger on rune)
            // TODO: handle armor (draugr — reduce hits)
        }
        return $hits;
    }

    /**
     * Apply damage to a monster:
     * If total damage >= monster health, the monster is killed (moved to supply, XP awarded).
     * @return bool true if the monster was killed
     */
    function effect_applyDamageMonster(string $monsterId, int $amount, string $owner): bool {
        if ($amount <= 0) {
            $this->notifyMessage(clienttranslate('${token_name} takes no damage'), ["token_name" => $monsterId]);
            return false;
        }

        // Count total red crystals on this monster
        $crystals = $this->tokens->getTokensOfTypeInLocation("crystal_red", $monsterId);
        $totalDamage = count($crystals);
        $health = (int) $this->material->getRulesFor($monsterId, "health", 0);

        $this->notifyMessage(clienttranslate('${token_name} takes ${amount} damage (${totalDamage}/${health})'), [
            "token_name" => $monsterId,
            "amount" => $amount,
            "totalDamage" => $totalDamage,
            "health" => $health,
        ]);

        // Check if monster is killed
        if ($totalDamage >= $health) {
            // Monster killed — award XP
            $xp = (int) $this->material->getRulesFor($monsterId, "xp", 0);
            $this->effect_gainXp($owner, $xp);
            // Remove red crystals from monster back to supply
            $this->effect_moveCrystals($monsterId, "red", -$totalDamage, $monsterId, ["message" => ""]);
            // Remove monster from map
            $heroId = $this->getHeroTokenId($owner);
            $this->hexMap->moveCharacter($monsterId, "supply_monster", clienttranslate('${token_name2} kills ${token_name}'), [
                "token_name2" => $heroId,
            ]);
            return true;
        }
        return false;
    }

    /**
     * Award XP (yellow crystals) to a hero by moving them from supply to their tableau.
     */
    function effect_gainXp(string $owner, int $amount): void {
        if ($amount <= 0) {
            return;
        }
        $heroId = $this->getHeroTokenId($owner);
        $this->effect_moveCrystals($heroId, "yellow", $amount, "tableau_$owner");
    }

    /**
     * Apply damage to a hero after monster attack.
     * If total damage >= hero health, hero is knocked out:
     * - Moved to Grimheim, damage set to 5, 2 town pieces destroyed.
     * @return bool true if the hero was knocked out
     */
    function effect_applyDamageHero(string $heroId, int $amount): bool {
        if ($amount <= 0) {
            return false;
        }
        $owner = $this->getHeroOwner($heroId);
        // Count total red crystals on hero
        $totalDamage = count($this->tokens->getTokensOfTypeInLocation("crystal_red", $heroId));
        $heroCardKey = $this->tokens->getTokensOfTypeInLocationSingleKey("card_hero", "tableau_$owner");
        $health = (int) $this->material->getRulesFor($heroCardKey, "health", 9);

        $this->notifyMessage(clienttranslate('${char_name} takes ${amount} damage (${totalDamage}/${health})'), [
            "char_name" => $heroId,
            "amount" => $amount,
            "totalDamage" => $totalDamage,
            "health" => $health,
        ]);

        if ($totalDamage >= $health) {
            // Adjust damage to exactly 5: remove excess or add missing
            $diff = $totalDamage - 5;
            if ($diff !== 0) {
                $this->effect_moveCrystals($heroId, "red", -$diff, $heroId, ["message" => ""]);
            }

            // Move hero to their starting hex in Grimheim
            $startHex = $this->getRulesFor($heroId, "location");
            $this->hexMap->moveCharacter(
                $heroId,
                $startHex,
                clienttranslate('${token_name} is knocked out and carried back to Grimheim. By their mother. She\'s not happy.')
            );

            // "Some villagers panic and flee, leaving their houses undefended"
            $this->effect_destroyHouses(2, $heroId, clienttranslate('Villagers panic and flee, ${token_name} is left undefended!'));

            if ($this->isEndOfGame()) {
                $this->handleEndOfGame();
            }
            return true;
        }
        return false;
    }

    /**
     * Destroy N town pieces (houses). Freyja's Well (house_0) is always destroyed last.
     * @param string $charId token causing the destruction (for log messages and animation)
     * @param string $message log message per house destroyed (use ${token_name} for causeTokenId)
     */
    function effect_destroyHouses(int $count, string $charId, string $message = ""): void {
        if ($message === "") {
            $message = clienttranslate('${char_name} destroys ${token_name}');
        }
        $houses = $this->tokens->getTokensOfTypeInLocation("house", "hex%");
        // Skip animation move if char is already in Grimheim (e.g. knocked out hero)
        $charInGrimheim = $this->hexMap->isInGrimheim($this->tokens->getTokenLocation($charId));

        for ($i = 0; $i < $count; $i++) {
            $targetHouseRec = array_shift($houses);
            if ($targetHouseRec === null) {
                break;
            }
            // Freyja's Well is destroyed last — push it back if others remain
            if ($targetHouseRec["key"] === "house_0" && count($houses) > 0) {
                $houses[$targetHouseRec["key"]] = $targetHouseRec;
                $i--;
                continue;
            }
            if (!$charInGrimheim) {
                $this->tokens->dbSetTokenLocation($charId, $targetHouseRec["location"], null, "");
            }
            $this->tokens->dbSetTokenLocation($targetHouseRec["key"], "limbo", 0, $message, [
                "char_name" => $charId,
            ]);
        }
    }

    function getVariantSoloBoard() {
        return (int) $this->getGameStateValue("variant_solo_board");
    }

    /**
     * Returns the hero number (1-4) for a player color.
     * Looks up which card_hero_N_M is on the player's tableau.
     */
    function getHeroNumber(string $owner): int {
        $heroCardKey = $this->game->tokens->getTokensOfTypeInLocationSingleKey("card_hero", "tableau_$owner");
        $this->systemAssert("No hero card found", $heroCardKey);
        return (int) getPart($heroCardKey, 2); // card_hero_<heroNo>_<num>
    }

    /**
     * Returns the hero miniature token id for a player color, e.g. "hero_1".
     */
    function getHeroTokenId(string $owner): string {
        return "hero_" . $this->getHeroNumber($owner);
    }

    /**
     * Returns the owner (player color) for a hero token id, e.g. "hero_1" → "6cd0f6".
     */
    function getHeroOwner(string $heroId): string {
        foreach ($this->getPlayerColors() as $color) {
            if ($this->getHeroTokenId($color) === $heroId) {
                return $color;
            }
        }
        $this->systemAssert("No owner found for hero $heroId");
        return ""; // unreachable
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

    // Debug functions below: kept commented out, uncomment as needed for BGA Studio testing
    // function debug_monster() {
    //     // Place monsters on the map for visual testing
    //     // Trollkin faction
    //     $this->tokens->dbSetTokenLocation("monster_goblin_1", "hex_7_7");
    //     $this->tokens->dbSetTokenLocation("monster_goblin_2", "hex_12_6");
    //     $this->tokens->dbSetTokenLocation("monster_goblin_3", "hex_5_10");
    //     $this->tokens->dbSetTokenLocation("monster_brute_1", "hex_6_8");
    //     $this->tokens->dbSetTokenLocation("monster_brute_2", "hex_11_5");
    //     $this->tokens->dbSetTokenLocation("monster_troll_1", "hex_4_9");
    //     $this->tokens->dbSetTokenLocation("monster_troll_2", "hex_13_7");
    //     // Fire Horde faction
    //     $this->tokens->dbSetTokenLocation("monster_sprite_1", "hex_8_3");
    //     $this->tokens->dbSetTokenLocation("monster_sprite_2", "hex_13_5");
    //     $this->tokens->dbSetTokenLocation("monster_elemental_1", "hex_6_4");
    //     $this->tokens->dbSetTokenLocation("monster_elemental_2", "hex_14_6");
    //     $this->tokens->dbSetTokenLocation("monster_jotunn_1", "hex_4_7");
    //     $this->tokens->dbSetTokenLocation("monster_jotunn_2", "hex_12_9");
    //     // Dead faction
    //     $this->tokens->dbSetTokenLocation("monster_imp_1", "hex_3_6");
    //     $this->tokens->dbSetTokenLocation("monster_imp_2", "hex_14_4");
    //     $this->tokens->dbSetTokenLocation("monster_skeleton_1", "hex_5_5");
    //     $this->tokens->dbSetTokenLocation("monster_skeleton_2", "hex_12_3");
    //     $this->tokens->dbSetTokenLocation("monster_draugr_1", "hex_7_4");
    //     $this->tokens->dbSetTokenLocation("monster_draugr_2", "hex_11_8");
    //     $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    // }

    // function debug_houses() {
    //     // Move a couple houses out of Grimheim to test removal visuals
    //     $this->tokens->dbSetTokenLocation("house_0", "hex_8_9");
    //     $this->tokens->dbSetTokenLocation("house_2", "hex_8_9");
    //     $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    // }

    // function debug_monsterCards() {
    //     // Place a few monster cards on display_monsterturn for visual testing
    //     $this->tokens->dbSetTokenLocation("card_monster_28", "display_monsterturn", 0); // Flanking (yellow)
    //     $this->tokens->dbSetTokenLocation("card_monster_36", "display_monsterturn", 0); // Viral Trolls (yellow)
    //     $this->tokens->dbSetTokenLocation("card_monster_22", "display_monsterturn", 1); // Imp-ressive Swarm (skipped)
    //     $this->tokens->dbSetTokenLocation("card_monster_43", "display_monsterturn", 0); // Feed the Flames (red)
    //     $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    // }

    // function debug_heroCards(int $hno = 1) {
    //     // Place all cards for hero $hno on the current player's tableau for visual testing
    //     // Creates cards if they don't exist (e.g. testing a hero nobody is playing)
    //     $color = $this->getPlayerColorById((int) $this->getCurrentPlayerId());
    //     foreach ($this->material->getTokensWithPrefix("card_") as $cardId => $info) {
    //         if (($info["hno"] ?? null) != $hno) {
    //             continue;
    //         }
    //         $tokens = $this->tokens->getTokensOfTypeInLocation($cardId, null);
    //         if (!$tokens) {
    //             // Card doesn't exist yet — create it (count>1 needs indexed tokens)
    //             $count = $info["count"] ?? 1;
    //             $info["location"] = "tableau_{$color}";
    //             $info["create"] = $count > 1 ? 2 : 1;
    //             $this->tokens->createTokenFromInfo($cardId, $info);
    //         } else {
    //             $firstKey = array_key_first($tokens);
    //             $this->tokens->dbSetTokenLocation($firstKey, "tableau_{$color}", 0, "", ["noa" => true]);
    //         }
    //     }
    //     $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    // }

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

    function debug_dice() {
        // Place 6 attack dice with different sides for visual testing
        for ($i = 11; $i <= 16; $i++) {
            $this->tokens->dbSetTokenLocation("die_attack_$i", "display_monsterturn", $i - 10);
        }
        // Place monster die with side 1
        $this->tokens->dbSetTokenLocation("die_monster_3", "display_monsterturn", 4);
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    }

    function debug_eval(string $x) {
        $color = $this->getPlayerColorById((int) $this->getCurrentPlayerId());
        $v = $this->evaluateExpression($x, $color);
        $this->notify->all("log", "result: $v");
    }
}
