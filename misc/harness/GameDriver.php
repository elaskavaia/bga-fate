<?php

declare(strict_types=1);

use Bga\GameFramework\States\GameState;

/**
 * Drives a GameHarness through scenario steps and debug functions.
 * Handles loading/saving state, dispatching endpoints, and emitting notifications.
 */
class GameDriver {
    public GameHarness $game;
    private string $stagingDir;
    private string $writeDir;
    public array $states;

    private string $gameName;

    public function __construct(string $gameName, string $stagingDir, string $writeDir, int $currentPlayerId = 10) {
        $this->gameName = $gameName;
        $this->stagingDir = $stagingDir;
        $this->writeDir = $writeDir;
        $this->game = new GameHarness();
        $this->game->_setCurrentPlayerId($currentPlayerId);
        $this->states = $this->buildStateNameMap();
    }

    /** @return array<int, GameState> map of state id => state instance */
    public function buildStateNameMap(): array {
        $map = [];
        $statesDir = __DIR__ . "/../../modules/php/States";
        foreach (glob("$statesDir/*.php") as $file) {
            $className = "Bga\\Games\\{$this->gameName}\\States\\" . basename($file, ".php");
            if (!class_exists($className)) {
                continue;
            }
            $inst = new $className($this->game);
            $map[$inst->id] = $inst;
        }
        return $map;
    }

    // ── State persistence ────────────────────────────────────────────────────

    public function loadState(string $stateDir): void {
        $db = self::loadJson("$stateDir/db.json");
        if ($db === null) {
            echo "No db.json found, starting fresh.\n";
            return;
        }
        echo "Loading db.json...\n";
        $this->game->tokens->fromJson($db["tokens"] ?? []);
        $this->game->machine->db->fromJson($db["machine"] ?? []);
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

    public function saveState(): void {
        $finalDb = [
            "tokens" => $this->game->tokens->toJson(),
            "machine" => $this->game->machine->db->toJson(),
            "gamestate" => [
                "state_id" => $this->game->gamestate->getCurrentMainStateId(),
                "active_player" => (int) $this->game->getActivePlayerId(),
            ],
            "players" => array_values($this->game->loadPlayersBasicInfos()),
        ];
        self::saveJson("$this->writeDir/db.json", $finalDb);
    }

    public function saveGamedatas(): void {
        $gamedatas = $this->game_getAllDatas();
        self::saveJson("$this->stagingDir/gamedatas.json", $gamedatas);
        echo "Wrote staging/gamedatas.json\n";
    }

    public function getGameStateArgs() {
        $stateId = $this->game->gamestate->getCurrentMainStateId();
        $activePlayer = (int) $this->game->getActivePlayerId();

        $stateNameMap = $this->states;
        /** @var GameState */
        $stateInst = $stateNameMap[$stateId];
        if (!$stateInst) {
            die("State not found: $stateId\n");
        }
        $stateName = $stateInst->name;

        $stateArgs = [];
        if (method_exists($stateInst, "getArgs")) {
            try {
                $stateArgs = $stateInst->getArgs($activePlayer);
            } catch (\Throwable $e) {
                echo "Warning: getArgs for state $stateName failed: " . $e->getMessage() . "\n";
            }
        }

        return [
            "gamestate" => [
                "id" => $stateId,
                "name" => $stateName,
                "active_player" => $activePlayer,
                "args" => $stateArgs,
            ],
        ];
    }

    public function game_getAllDatas() {
        $gamedatas = $this->getGameStateArgs();
        $gamedatas += $this->game->getAllDatas();
        return $gamedatas;
    }

    public function saveNotifications(): void {
        self::saveJson("$this->stagingDir/notifications.json", $this->game->notify->_getNotifications());
        echo "Wrote staging/notifications.json (" . count($this->game->notify->_getNotifications()) . " notifications)\n";
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
        $nextState = $this->runStateClassOnEnteringState($stateId, (int) $this->game->getCurrentPlayerId());
        if (!$nextState) {
            // state did not change
            $nextState = $stateId;
        } else {
            $nextState = $this->getStateId($nextState);
            $this->game->gamestate->jumpToState($nextState);
        }

        echo "  → Dispatched → state:  $nextState\n";
    }

    public function runStateClassOnEnteringState(int $stateId, ?int $privateStatePlayerId = null): mixed {
        $state = $this->states[$stateId];
        $reflection = new \ReflectionClass($state);

        $functionName = "onEnteringState";

        if (!$reflection->hasMethod($functionName)) {
            return null;
        }

        $method = $reflection->getMethod($functionName);
        $parameters = $method->getParameters();

        $functionParameters = [];
        foreach ($parameters as $index => $parameter) {
            $paramName = $parameter->getName();
            $paramType = $parameter->getType()->getName();
            if (in_array($paramName, ["arg", "args"]) && $paramType === "array") {
                $functionParameters[] = []; // ?
            } elseif (in_array($paramName, ["playerId", "player_id", "currentPlayerId", "current_player_id"]) && $paramType === "int") {
                $functionParameters[] = $privateStatePlayerId;
            } elseif (in_array($paramName, ["activePlayerId", "active_player_id"]) && $paramType === "int") {
                $functionParameters[] = (int) $this->game->getActivePlayerId();
            } else {
                die("Unknown $paramType $paramName for $functionName");
            }
        }

        $result = $state->$functionName(...$functionParameters);

        return $result;
    }

    public function emitGameStateChange(): void {
        $currentPlayerId = $this->game->_getCurrentPlayerId();
        $gamedatas = $this->getGameStateArgs();
        $newGamestate = $gamedatas["gamestate"];
        $gsArgs = $newGamestate["args"] ?? [];
        if (isset($gsArgs["_private"]) && is_array($gsArgs["_private"])) {
            $activeKey = (string) $newGamestate["active_player"];
            $gsArgs["_private"] = $gsArgs["_private"][$activeKey] ?? ($gsArgs["_private"][$currentPlayerId] ?? reset($gsArgs["_private"]));
        }

        $this->game->notify->all("gameStateChange", "", [
            "id" => $newGamestate["id"] ?? 0,
            "name" => $newGamestate["name"] ?? "",
            "active_player" => (string) $newGamestate["active_player"],
            "type" => "activeplayer",
            "args" => $gsArgs,
        ]);
    }

    // ── Run ──────────────────────────────────────────────────────────────────

    public function runStep(string $endpoint, array $data): void {
        $op = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->game->debugLog("  → Starting step op " . ($op ? $op->getType() : "EMPTY"));
        $this->dispatchEndpoint($endpoint, $data);
        $this->runDispatchLoop();
        $this->emitGameStateChange();
    }

    public function runSteps(array $steps): void {
        echo "Loading " . count($steps) . " steps\n";
        foreach ($steps as $i => $step) {
            $endpoint = $step["endpoint"] ?? "";
            echo "Step " . ($i + 1) . ": $endpoint\n";
            $this->runStep($endpoint, $step);

            if ($step["reload"] ?? false) {
                $gamedatas = $this->game_getAllDatas();
                self::saveJson("$this->stagingDir/gamedatas.json", $gamedatas);
                echo "  → Wrote staging/gamedatas.json (reload after step)\n";
            }

            $op = $this->game->machine->createTopOperationFromDbForOwner(null);
            $this->game->debugLog("  → Final step op " . ($op ? $op->getType() : "EMPTY"));
        }
    }

    public function runDebug(string $debugFunction): void {
        echo "Calling: $debugFunction\n";
        $this->runStep($debugFunction, []);
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
