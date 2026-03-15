<?php

declare(strict_types=1);

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\Table;

/**
 * Generic harness driver for BGA games.
 * Handles scenario execution, state persistence, endpoint dispatch, and notifications.
 *
 * The game instance must provide:
 *   getGameName(): string — namespace name (e.g. "Fate")
 *   saveDbState(): array — serialize all custom DB tables
 *   loadDbState(array $db): void — restore custom DB tables
 *   getAllDatas(): array — game data for client
 */
class GameDriver {
    public Table&HarnessGameInterface $game;
    private string $stagingDir;
    public array $states;

    public function __construct(Table&HarnessGameInterface $game, string $stagingDir, int $currentPlayerId = 10) {
        $this->game = $game;
        $this->stagingDir = $stagingDir;
        $this->game->_setCurrentPlayerId($currentPlayerId);
        $this->game->gamestate->changeActivePlayer($currentPlayerId);
        $this->states = $this->buildStateNameMap();
    }

    /** @return array<int, GameState> map of state id => state instance */
    public function buildStateNameMap(): array {
        $gameName = $this->game->getGameName();
        $map = [];
        $statesDir = __DIR__ . "/../../modules/php/States";
        foreach (glob("$statesDir/*.php") as $file) {
            $className = "Bga\\Games\\{$gameName}\\States\\" . basename($file, ".php");
            if (!class_exists($className)) {
                continue;
            }
            $inst = new $className($this->game);
            $map[$inst->id] = $inst;
        }
        return $map;
    }

    // ── State persistence ────────────────────────────────────────────────────

    public function loadDbFromJson(string $dbPath): void {
        $db = self::loadJson($dbPath);
        if ($db === null) {
            die("db.json not found: $dbPath\n");
        }
        $this->debugLog("Loading $dbPath");
        $this->game->loadDbState($db);
        if (isset($db["gamestate"]["active_player"])) {
            $this->game->gamestate->changeActivePlayer($db["gamestate"]["active_player"]);
        }
        if (isset($db["gamestate"]["state_id"])) {
            $this->game->gamestate->jumpToState($db["gamestate"]["state_id"]);
        }
        if (isset($db["players"])) {
            $this->game->_colors = array_column($db["players"], "player_color");
        }
    }

    public function saveDbToJson(): void {
        $finalDb = $this->game->saveDbState() + [
            "gamestate" => [
                "state_id" => $this->game->gamestate->getCurrentMainStateId(),
                "active_player" => (int) $this->game->getActivePlayerId(),
            ],
            "players" => array_values($this->game->loadPlayersBasicInfos()),
        ];
        self::saveJson("$this->stagingDir/db.json", $finalDb);
    }

    public function saveGamedatas(): void {
        $gamedatas = $this->game_getAllDatas();
        self::saveJson("$this->stagingDir/gamedatas.json", $gamedatas);
        $this->debugLog("Wrote staging/gamedatas.json");
    }

    public function getGameState(int $stateId) {
        $activePlayer = (int) $this->game->getActivePlayerId();

        $stateNameMap = $this->states;
        /** @var GameState */
        $stateInst = $stateNameMap[$stateId];
        if (!$stateInst) {
            die("State not found: $stateId\n");
        }
        $stateName = $stateInst->name;

        $stateArgs = $this->runStateClass_getArgs($stateId, (int) $this->game->getCurrentPlayerId());

        return [
            "id" => $stateId,
            "name" => $stateName,
            "active_player" => $activePlayer,
            "args" => $stateArgs,
        ];
    }

    private function privateFilter(array &$state, int $currentPlayerId) {
        $private = $state["args"]["_private"] ?? null;
        if ($private === null) {
            return;
        }
        $forPlayer = $private[$currentPlayerId]
            ?? ($this->game->gamestate->isPlayerActive($currentPlayerId) ? ($private["active"] ?? null) : null);
        unset($state["args"]["_private"]);
        if ($forPlayer !== null) {
            $state["args"]["_private"] = $forPlayer;
        }
    }
    public function game_getAllDatas() {
        /** @var int */
        $currentPlayerId = (int) $this->game->getCurrentPlayerId();
        $result = [];
        $stateId = $this->game->gamestate->getCurrentMainStateId();
        $state = $this->getGameState($stateId);
        $this->privateFilter($state, $currentPlayerId);
        $result["gamestate"] = $state;
        $result["gamestate"]["updateGameProgression"] = $stateId === 99 ? 100 : round((float) $this->game->getGameProgression());

        // Players info, aliases
        $players = $this->game->loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            foreach (["color", "name", "avatar", "zombie", "eliminated"] as $field) {
                $result["players"][$player_id][$field] = $player["player_$field"];
            }
            $result["players"][$player_id]["beginner"] = $player["player_beginner"] !== null;
        }

        // Player ordering
        $player_ids = array_keys($players);
        $pos = array_search($currentPlayerId, $player_ids);
        $result["playerorder"] =
            $pos !== false ? array_merge(array_slice($player_ids, $pos), array_slice($player_ids, 0, $pos)) : $player_ids;

        // assume this is blackbox, we don't know what game did the data
        $result += $this->game->getAllDatas();
        return $result;
    }

    public function saveNotifications(): void {
        self::saveJson("$this->stagingDir/notifications.json", $this->game->notify->_getNotifications());
        $this->debugLog("Wrote staging/notifications.json (" . count($this->game->notify->_getNotifications()) . " notifications)");
    }

    // ── Dispatch ─────────────────────────────────────────────────────────────

    public function dispatchEndpoint(string $endpoint, array $data): void {
        $states = $this->states;
        if (str_starts_with($endpoint, "action_")) {
            $stateId = $this->game->gamestate->getCurrentMainStateId();
            $stateInst = $states[$stateId];
            $ref = new ReflectionMethod($stateInst, $endpoint);
            $state = $ref->invokeArgs($stateInst, self::matchArgs($ref, $data));
            $this->game->gamestate->jumpToState($this->getStateId($state));
        } elseif (str_starts_with($endpoint, "debug_")) {
            if (!method_exists($this->game, $endpoint)) {
                die("Unknown debug endpoint: $endpoint\n");
            }
            $ref = new ReflectionMethod($this->game, $endpoint);
            $ref->invokeArgs($this->game, self::matchArgs($ref, $data));
        } else {
            die("Unknown endpoint: $endpoint\n");
        }
    }

    public function runDispatchLoop(): void {
        $stateId = $this->game->gamestate->getCurrentMainStateId();
        $nextState = $this->runStateClass_onEnteringState($stateId, (int) $this->game->getCurrentPlayerId());
        if (!$nextState) {
            // state did not change
            $nextState = $stateId;
        } else {
            $nextState = $this->getStateId($nextState);
            $this->game->gamestate->jumpToState($nextState);
        }

        $this->debugLog("  → Dispatched → state:  $nextState");
    }

    public function runStateClass_getArgs(int $stateId, ?int $privateStatePlayerId = null): mixed {
        return $this->runStateMethod($stateId, "getArgs", $privateStatePlayerId) ?? [];
    }

    public function runStateClass_onEnteringState(int $stateId, ?int $privateStatePlayerId = null): mixed {
        return $this->runStateMethod($stateId, "onEnteringState", $privateStatePlayerId);
    }

    private function runStateMethod(int $stateId, string $methodName, ?int $privateStatePlayerId): mixed {
        $state = $this->states[$stateId];
        $reflection = new \ReflectionClass($state);

        if (!$reflection->hasMethod($methodName)) {
            return null;
        }

        $method = $reflection->getMethod($methodName);
        $args = $this->matchStateMethodArgs($method, $privateStatePlayerId);
        return $state->$methodName(...$args);
    }

    private function matchStateMethodArgs(\ReflectionMethod $method, ?int $privateStatePlayerId): array {
        $functionParameters = [];
        foreach ($method->getParameters() as $parameter) {
            $paramName = $parameter->getName();
            $paramType = $parameter->getType()->getName();
            if (in_array($paramName, ["arg", "args"]) && $paramType === "array") {
                $functionParameters[] = [];
            } elseif (in_array($paramName, ["playerId", "player_id", "currentPlayerId", "current_player_id"]) && $paramType === "int") {
                $functionParameters[] = $privateStatePlayerId;
            } elseif (in_array($paramName, ["activePlayerId", "active_player_id"]) && $paramType === "int") {
                $functionParameters[] = (int) $this->game->getActivePlayerId();
            } else {
                $functionName = $method->getName();
                $stateName = $method->getDeclaringClass()->getName();
                die("Unknown $paramType $paramName for $stateName::$functionName\n");
            }
        }
        return $functionParameters;
    }

    private static function matchArgs(ReflectionMethod $ref, array $data): array {
        $args = [];
        foreach ($ref->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $data)) {
                $args[] = $data[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $endpoint = $ref->getName();
                die("Missing required param '$name' for $endpoint\n");
            }
        }
        return $args;
    }

    public function emitGameStateChange(): void {
        $currentPlayerId = $this->game->_getCurrentPlayerId();
        $stateId = $this->game->gamestate->getCurrentMainStateId();
        $newGamestate = $this->getGameState($stateId);
        $this->privateFilter($newGamestate, $currentPlayerId);

        $this->game->notify->all("gameStateChange", "", [
            "id" => $newGamestate["id"] ?? 0,
            "name" => $newGamestate["name"] ?? "",
            "active_player" => (string) $newGamestate["active_player"],
            "type" => "activeplayer",
            "args" => $newGamestate["args"] ?? [],
        ]);
    }

    private function debugLog($loc) {
        echo "$loc\n";
    }

    // ── Run ──────────────────────────────────────────────────────────────────

    public function runStep(string $endpoint, array $data): void {
        $this->dispatchEndpoint($endpoint, $data);
        $this->runDispatchLoop();
        $this->emitGameStateChange();
    }

    public function runSteps(array $steps): void {
        $this->debugLog("Loading " . count($steps) . " steps");
        foreach ($steps as $i => $step) {
            $endpoint = $step["endpoint"] ?? "";
            $this->debugLog("Step " . ($i + 1) . ": $endpoint");
            $this->runStep($endpoint, $step);

            if ($step["reload"] ?? false) {
                $gamedatas = $this->game_getAllDatas();
                self::saveJson("$this->stagingDir/gamedatas.json", $gamedatas);
                $this->debugLog("  → Wrote staging/gamedatas.json (reload after step)");
            }
        }
    }

    public function runDebug(string $debugFunction): void {
        $this->debugLog("Calling debug: $debugFunction");
        $this->runStep($debugFunction, []);
    }

    // ── CLI entry point ────────────────────────────────────────────────────

    /**
     * Run the harness from CLI arguments.
     * @param array $argv CLI arguments (from $argv global)
     * @param string $baseDir Directory containing plays/ subdirectory (for default scenario)
     * @param string $defaultStagingDir Default output directory
     */
    public static function main(Table&HarnessGameInterface $game, array $argv, string $baseDir, string $defaultStagingDir): void {
        $debugFunction = null;
        $scriptPath = null;
        $dbPath = null;
        $outputDir = null;

        $args = array_slice($argv, 1);
        for ($i = 0; $i < count($args); $i++) {
            switch ($args[$i]) {
                case "--debug":
                    $debugFunction = $args[++$i] ?? null;
                    if (!$debugFunction) {
                        die("Usage: --debug <function_name>\n");
                    }
                    break;
                case "--script":
                case "--scenario":
                    $scriptPath = $args[++$i] ?? null;
                    if (!$scriptPath) {
                        die("Usage: --script <path.json>\n");
                    }
                    break;
                case "--db":
                    $dbPath = $args[++$i] ?? null;
                    if (!$dbPath) {
                        die("Usage: --db <path.json>\n");
                    }
                    break;
                case "--output":
                    $outputDir = $args[++$i] ?? null;
                    if (!$outputDir) {
                        die("Usage: --output <dir>\n");
                    }
                    break;
                default:
                    if (!$scriptPath && !str_starts_with($args[$i], "-")) {
                        $scriptPath = $args[$i];
                    } else {
                        die("Unknown option: {$args[$i]}\n");
                    }
            }
        }

        if (!$scriptPath && !$debugFunction) {
            $scriptPath = "$baseDir/plays/setup.json";
        }

        $stagingDir = $outputDir ?? $defaultStagingDir;
        $currentPlayerId = 10;
        $steps = [];

        if ($scriptPath) {
            $script = self::loadJson($scriptPath);
            if ($script === null) {
                die("Script not found: $scriptPath\n");
            }
            echo "Loading script: $scriptPath\n";
            $currentPlayerId = (int) ($script["current_player_id"] ?? 10);
            $steps = $script["steps"] ?? [];
        }

        $driver = new self($game, $stagingDir, $currentPlayerId);

        if ($dbPath) {
            $driver->loadDbFromJson($dbPath);
        }

        $driver->runSteps($steps);

        if ($debugFunction) {
            $driver->runDebug($debugFunction);
        }

        $driver->saveGamedatas();
        $driver->saveNotifications();
        $driver->saveDbToJson();

        echo "Done.\n";
    }

    // ── Static helpers ───────────────────────────────────────────────────────

    public static function loadJson(string $path): mixed {
        if (!file_exists($path)) {
            return null;
        }
        $data = json_decode(file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            die("JSON parse error in $path: " . json_last_error_msg() . "\n");
        }
        return $data;
    }

    public static function saveJson(string $path, mixed $data): void {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function getStateId(mixed $targetClass): int {
        if ($targetClass instanceof GameState) {
            return $targetClass->id;
        } elseif (is_numeric($targetClass)) {
            return (int) $targetClass;
        } else {
            $ref = new ReflectionClass($targetClass);
            $inst = $ref->newInstance($this->game);
            return $inst->id;
        }
    }
}
