<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate;

use Bga\GameFramework\NotificationMessage;
use Bga\GameFramework\UserException;
use Bga\Games\Fate\Common\HexMap;
use Bga\Games\Fate\Db\DbMultiUndo;
use Bga\Games\Fate\Db\DbTokens;
use Bga\Games\Fate\Model\Card;
use Bga\Games\Fate\OpCommon\AiOperation;
use Bga\Games\Fate\OpCommon\ComplexOperation;
use Bga\Games\Fate\OpCommon\OpMachine;
use Bga\Games\Fate\Model\Character;
use Bga\Games\Fate\Model\Hero;
use Bga\Games\Fate\Model\Monster;
use Bga\Games\Fate\OpCommon\Operation;
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

        $this->registerNotifyDecorators();
    }

    public function registerNotifyDecorators(): void {
        parent::registerNotifyDecorators();
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
    function setupGameTables() {
        // Player setup — heroes randomly assigned, reassign colors before creating tokens
        $heroNos = $this->getHeroOrder();
        $heroColors = $this->getAvailColors();
        $players = $this->loadPlayersBasicInfos();
        $heroIdx = 0;
        foreach ($players as $player_id => $player) {
            $heroNo = $heroNos[$heroIdx++];
            $color = $heroColors[$heroNo - 1];
            $this->setPlayerColor((int) $player_id, $color);
        }
        $this->reloadPlayersBasicInfos();

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
                "max" => $this->getTimeTrackLength(),
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

        // Second pass: create tokens with correct colors
        $heroIdx = 0;
        foreach ($players as $player_id => $player) {
            $heroNo = $heroNos[$heroIdx++];
            $color = $heroColors[$heroNo - 1];
            $this->tokens->pickTokensForLocation(2, "supply_crystal_yellow", "tableau_{$color}");
            $hero = new Hero($this, "hero_$heroNo", $color);
            $hero->createHeroCards();
            $hero->createHeroTrackers();

            // Move starting cards to tableau
            $this->tokens->moveToken("card_hero_{$heroNo}_1", "tableau_{$color}");
            $this->tokens->moveToken("card_ability_{$heroNo}_3", "tableau_{$color}");
            $this->tokens->moveToken("card_equip_{$heroNo}_15", "tableau_{$color}");
            // Add 1 mana to starting ability card
            $this->tokens->pickTokensForLocation(1, "supply_crystal_green", "card_ability_{$heroNo}_3");
            // Place upgrade cost marker on tableau with starting cost of 5
            $this->tokens->moveToken("marker_{$color}_3", "tableau_{$color}", 5);
            // Shuffle decks
            $this->tokens->shuffle("deck_ability_{$color}");
            $this->tokens->shuffle("deck_equip_{$color}");
            $this->tokens->shuffle("deck_event_{$color}");
            // Draw 1 event card to hand
            $this->tokens->pickTokensForLocation(1, "deck_event_{$color}", "hand_{$color}");
            // Hero already at starting hex from material, no move needed

            // Create attribute trackers for this hero and set initial values

            $hero->recalcTrackers();
        }
        // Move unused heroes to limbo
        $usedHeros = array_slice($heroNos, 0, $heroIdx);
        for ($i = 1; $i <= 4; $i++) {
            if (!in_array($i, $usedHeros)) {
                $this->tokens->moveToken("hero_$i", "limbo");
            }
        }
        $color = $this->getPlayerColorById($startingPlayer);
        $this->machine->queue("reinforcement", $color);
        $this->machine->queue("turnStart", $color);
        $this->customUndoSavepoint($startingPlayer, 1);
        return GameDispatch::class;
    }

    protected function getHeroOrder() {
        $heroNos = range(1, 4);
        shuffle($heroNos);
        return $heroNos;
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
    public function getAllDatas(): array {
        $result = [];
        $result = parent::getAllDatas();

        $result = array_merge($result, $this->tokens->getAllDatas());

        // Add heroNo to each player
        foreach ($result["players"] as $player_id => &$pdata) {
            $pdata["heroNo"] = $this->getHeroNumber($pdata["color"]);
        }
        unset($pdata);

        // Hero attribute trackers (tracker_strength_{color}, etc.) are sent automatically via tokens->getAllDatas()

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
        $currentStep = $this->tokens->getTokenState("rune_stone");
        $maxSteps = $this->getTimeTrackLength();
        return min(100, (int) round(($currentStep / $maxSteps) * 100));
    }

    function isEndOfGame() {
        $currentStep = $this->tokens->getTokenState("rune_stone");
        $maxSteps = $this->getTimeTrackLength();

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

        $supply = "supply_crystal_$type";

        if ($inc > 0) {
            $tokens = $this->tokens->pickTokensForLocation($inc, $supply, $location);
            // TODO: unlimited? create more if needed
            $this->tokens->dbSetTokensLocation($tokens, $location, 0, $message, $options);
        } else {
            $needed = abs($inc);
            $tokens = $this->tokens->getTokensOfTypeInLocation("crystal_$type", $location);
            if (count($tokens) < $needed) {
                throw new UserException(
                    new NotificationMessage(clienttranslate('Insufficient resources to pay: ${res_name}'), [
                        "res_name" => $this->getTokenName("crystal_$type"),
                    ])
                );
            }
            // trim $tokens to $needed
            $tokens = array_slice($tokens, 0, $needed);
            $this->tokens->dbSetTokensLocation($tokens, $supply, 0, $message, $options);
        }
    }

    function evaluateTerm($x, $owner, $context = null, ?array $options = null) {
        if ($x === "true") {
            return 1;
        }
        if (str_starts_with($x, "count")) {
            return parent::evaluateTerm($x, $owner, $context, $options);
        }
        if ($context === null) {
            return 0;
        }
        if ($x === "legend") {
            // monster_legend_1
            return (int) (getPart($context, 1) == "legend");
        } elseif ($x === "not_legend") {
            return (int) (getPart($context, 1) != "legend");
        } elseif ($x === "trollkin" || $x === "firehorde" || $x === "dead") {
            return (int) ($this->getRulesFor($context, "faction", "") === $x);
        } elseif ($x === "adj") {
            $heroId = $this->getHeroTokenId($owner);
            $heroHex = $this->hexMap->getCharacterHex($heroId);
            $contextHex = $this->tokens->getTokenLocation($context);
            return (int) ($heroHex !== null && $contextHex !== null && $this->hexMap->getMoveDistance($heroHex, $contextHex) === 1);
        } elseif ($x === "healthRem") {
            $health = (int) $this->getRulesFor($context, "health", 0);
            $damage = count($this->tokens->getTokensOfTypeInLocation("crystal_red", $context));
            return $health - $damage;
        } elseif ($x === "closerToGrimheim") {
            $heroId = $this->getHeroTokenId($owner);
            $heroHex = $this->hexMap->getCharacterHex($heroId);
            $contextHex = $this->tokens->getTokenLocation($context);
            $distMap = $this->hexMap->getDistanceMapToGrimheim();
            $heroDist = $distMap[$heroHex] ?? PHP_INT_MAX;
            $monsterDist = $distMap[$contextHex] ?? PHP_INT_MAX;
            return (int) ($monsterDist < $heroDist);
        }

        //id|name|count|type|create|location|tc|faction|rank|strength|health|xp|move|armor

        return $this->getRulesFor($context, $x, 0);
    }

    /**
     * Roll attack dice onto display_battle. Does NOT count hits — that's done later by effect_resolveHits.
     * Cleans up leftover dice, announces the attack, picks dice from supply and rolls them.
     */
    function effect_rollAttackDice(string $attackerId, string $defenderId, int $strength): void {
        $this->notifyMessage(clienttranslate('${token_name} attacks ${token_name2} with strength ${strength}'), [
            "token_name" => $attackerId,
            "token_name2" => $defenderId,
            "strength" => $strength,
        ]);

        // Clean up any leftover dice on display from a previous attack
        $leftover = $this->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        if (count($leftover) > 0) {
            $leftoverKeys = array_map(fn($d) => $d["key"], $leftover);
            $this->tokens->dbSetTokensLocation($leftoverKeys, "supply_die_attack", 6, "");
        }

        // Roll attack dice — pick from supply, then notify each with its roll result
        $diceTokens = $this->tokens->pickTokensForLocation($strength, "supply_die_attack", "display_battle");
        foreach ($diceTokens as $die) {
            $dieId = $die["key"];
            $this->effect_rollAttackDie($attackerId, $dieId);
        }
    }

    function effect_rollAttackDie(string $attackerId, string $dieId): void {
        $roll = $this->bgaRand(1, 6);
        $sideName = $this->material->getRulesFor("side_die_attack_$roll", "name", "?");
        $this->tokens->dbSetTokenLocation($dieId, "display_battle", $roll, clienttranslate('${char_name} rolls ${side_name}'), [
            "char_name" => $attackerId,
            "side_name" => $sideName,
        ]);
    }

    function effect_addAttackDiceDamage(string $attackerId, int $strength): void {
        $this->notifyMessage(clienttranslate('${char_name} adds ${strength} damage'), [
            "char_name" => $attackerId,
            "strength" => $strength,
        ]);

        $diceTokens = $this->tokens->pickTokensForLocation($strength, "supply_die_attack", "display_battle");
        foreach ($diceTokens as $die) {
            $dieId = $die["key"];
            $this->tokens->dbSetTokenLocation($dieId, "display_battle", 6 /* damage */, "");
        }
    }

    /**
     * Resolve dice on display_battle: count hits (applying cover, armor, rune rules) and return hit count.
     * Dice remain on display_battle so the player can see them.
     * @return int number of hits
     */
    function effect_resolveHits(string $attackerId, string $defenderId): int {
        $defender = $this->getCharacter($defenderId);
        $hits = 0;
        $diceOnDisplay = $this->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        foreach ($diceOnDisplay as $die) {
            $roll = (int) $die["state"];
            $rule = $this->material->getRulesFor("side_die_attack_$roll", "rule", "miss");
            $hits += $defender->countHit($rule, $attackerId);
        }
        return $hits;
    }

    /** Count dice on display_battle showing a rune (side 3). Used by evaluateExpression("countRunes"). */
    function countRunes($owner = null, $context = null, $options = null): int {
        $count = 0;
        $diceOnDisplay = $this->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        foreach ($diceOnDisplay as $die) {
            $roll = (int) $die["state"];
            $rule = $this->material->getRulesFor("side_die_attack_$roll", "rule", "miss");
            if ($rule === "rune") {
                $count++;
            }
        }
        return $count;
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

    function getTimeTrackLength(): int {
        // TODO: check game option for long track variant
        return Material::TIME_TRACK_SHORT_LENGTH;
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

    /** Factory: create a Hero model for a player color. */
    function getHero(string $owner): Hero {
        return new Hero($this, $this->getHeroTokenId($owner));
    }

    /** Return the hex targeted by the current attack, or null if no attack in progress. */
    function getAttackHex(): ?string {
        $loc = $this->tokens->getTokenLocation("marker_attack");
        return $loc !== null && $loc !== "limbo" ? $loc : null;
    }

    /** Factory: create a Hero model from a hero token id. */
    function getHeroById(string $heroId): Hero {
        return new Hero($this, $heroId);
    }

    /** Factory: create a Monster model from a monster token id. */
    function getMonster(string $monsterId): Monster {
        return new Monster($this, $monsterId);
    }

    /** Factory: create a Character model (Hero or Monster) from any character token id. */
    function getCharacter(string $characterId): Character {
        if (str_starts_with($characterId, "hero")) {
            return $this->getHeroById($characterId);
        }
        return $this->getMonster($characterId);
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

    /**
     * Instantiate a custom card class for cards declaring on=custom in Material.
     * The class name is derived from the card type and the Material `name` field
     * (alphanumeric only, PascalCase).
     *
     * Example: card_equip_1_20 "Black Arrows" → Bga\Games\Fate\Cards\CardEquip_BlackArrows
     *
     * @param string $cardId Token id (e.g. "card_equip_1_20")
     * @param Operation $op Calling operation (provides owner and queue context)
     */
    function instantiateCard(array $card, Operation $op): Card {
        $cardId = $card["key"];
        // card_<type>_..., e.g. card_equip_1_20 → "equip"
        $type = getPart($cardId, 1);
        $name = (string) $this->material->getRulesFor($cardId, "name", "");
        $sanitized = preg_replace("/[^A-Za-z0-9]/", "", $name);
        $this->systemAssert("ERR:instantiateCard:emptyName:$cardId", $sanitized !== "" && $sanitized !== null);

        // Try bespoke class first; fall back to per-supertype generic in Model/.
        $bespoke = "Bga\\Games\\Fate\\Cards\\Card" . ucfirst($type) . "_" . $sanitized;
        if (class_exists($bespoke)) {
            return new $bespoke($this, $card, $op);
        }
        $generic = "Bga\\Games\\Fate\\Model\\CardGeneric";
        $this->systemAssert("ERR:instantiateCard:noGeneric:$generic", class_exists($generic));
        return new $generic($this, $card, $op);
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
            $this->machine->queue("turnStart", $this->custom_getPlayerColorById($nextPlayerId));
        }
    }

    public function customUndoSavepoint(int $player_id, int $barrier = 0, string $label = "undo"): void {
        //$this->debugLog("customUndoSavepoint $player_id bar= $barrier");
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

    function debug_draw(string $card) {
        $color = $this->getPlayerColorById((int) $this->getCurrentPlayerId());
        $this->tokens->dbSetTokenLocation($card, "hand_{$color}");
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    }

    function debug_Op_killMonster() {
        $color = $this->getPlayerColorById((int) $this->getCurrentPlayerId());
        $heroId = $this->getHeroTokenId($color);
        $heroHex = $this->hexMap->getCharacterHex($heroId);
        $adjHexes = $this->hexMap->getAdjacentHexes($heroHex);
        // Place a goblin (rank 1) and a brute (rank 2) adjacent to hero
        $placed = 0;
        $monsters = ["monster_goblin_1", "monster_brute_1"];
        foreach ($adjHexes as $hex) {
            if ($placed >= 2) {
                break;
            }
            if (!$this->hexMap->isOccupied($hex)) {
                $this->tokens->dbSetTokenLocation($monsters[$placed], $hex, 0, "");
                $placed++;
            }
        }
        $this->hexMap->invalidateOccupancy();
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    }

    function debug_Op_c_sureshotII() {
        $color = $this->getPlayerColorById((int) $this->getCurrentPlayerId());
        $heroId = $this->getHeroTokenId($color);
        $cardId = "card_ability_1_4";
        // Ensure Sure Shot II is on tableau with 4 mana
        $this->tokens->dbSetTokenLocation($cardId, "tableau_$color", 0);
        $this->effect_moveCrystals($heroId, "green", 4, $cardId);
        // Place monsters in range
        $heroHex = $this->hexMap->getCharacterHex($heroId);
        $adjHexes = $this->hexMap->getAdjacentHexes($heroHex);
        $monsters = ["monster_goblin_1", "monster_brute_1"];
        $placed = 0;
        foreach ($adjHexes as $hex) {
            if ($placed >= 2) {
                break;
            }
            if (!$this->hexMap->isOccupied($hex)) {
                $this->tokens->dbSetTokenLocation($monsters[$placed], $hex, 0, "");
                $placed++;
            }
        }
        $this->hexMap->invalidateOccupancy();
        $this->machine->push("c_sureshotII", $color, ["card" => $cardId]);
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    }

    function debug_Op_gainMana() {
        $color = $this->getPlayerColorById((int) $this->getCurrentPlayerId());
        $this->machine->push("2gainMana", $color);
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    }

    function debug_Op_gainXp() {
        $color = $this->getPlayerColorById((int) $this->getCurrentPlayerId());
        $this->machine->push("2gainXp", $color);
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    }

    function debug_Op_upgrade(): void {
        $color = $this->getPlayerColorById((int) $this->getCurrentPlayerId());
        // Give player 10 XP so they can afford the upgrade (cost starts at 5)
        $heroId = $this->getHeroTokenId($color);
        $this->effect_moveCrystals($heroId, "yellow", 10, "tableau_{$color}", ["message" => ""]);
        $this->machine->push("upgrade", $color);
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

    function sendReloadAllNotification() {
        $this->notify->all("undoRestorePoint", "");
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
        $this->notify->all("message", "debug setup is done", []);
        $this->sendReloadAllNotification();
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

    function debug_reinforcement(string $cardId = "card_monster_1") {
        // Run reinforcement with a specific monster card (e.g. "card_monster_1" = Queen of the Dead yellow)
        $color = $this->getPlayerColorById((int) $this->getCurrentPlayerId());
        $this->machine->push("reinforcement", $color, ["card" => $cardId]);
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    }

    function debug_legends() {
        // Place all 6 legend miniatures on the map for visual testing
        $this->tokens->dbSetTokenLocation("monster_legend_1_1", "hex_7_7"); // Queen of the Dead (I)
        $this->tokens->dbSetTokenLocation("monster_legend_2_1", "hex_5_5"); // Seer of Odin (I)
        $this->tokens->dbSetTokenLocation("monster_legend_3_1", "hex_12_6"); // Grendel (I)
        $this->tokens->dbSetTokenLocation("monster_legend_4_1", "hex_8_3"); // Surt (I)
        $this->tokens->dbSetTokenLocation("monster_legend_5_1", "hex_4_9"); // Hrungbald (I)
        $this->tokens->dbSetTokenLocation("monster_legend_6_1", "hex_13_7"); // Nidhuggr (I)
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    }

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
