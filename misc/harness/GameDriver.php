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
    private int $currentPlayerId;
    public array $states;

    public function __construct(string $stagingDir, string $writeDir, int $currentPlayerId = 10) {
        $this->stagingDir = $stagingDir;
        $this->writeDir = $writeDir;
        $this->currentPlayerId = $currentPlayerId;
        $this->game = new GameHarness();
        $this->game->curid = $currentPlayerId;
        $this->states = $this->buildStateNameMap();
    }

    /** @return array<int, GameState> map of state id => state instance */
    public function buildStateNameMap(): array {
        $map = [];
        $statesDir = __DIR__ . "/../../modules/php/States";
        foreach (glob("$statesDir/*.php") as $file) {
            $className = "Bga\\Games\\Fate\\States\\" . basename($file, ".php"); // XXX namespace ref
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
        foreach ($db["tokens"] ?? [] as $rec) {
            $this->game->tokens->DbCreateTokens([[$rec["key"], $rec["location"], $rec["state"]]]);
        }
        $this->game->machine->db->loadRows($db["machine"] ?? []);
        if (isset($db["gamestate"]["active_player"])) {
            $this->game->gamestate->changeActivePlayer($db["gamestate"]["active_player"]);
        }
        if (isset($db["gamestate"]["state_id"])) {
            $this->game->gamestate->jumpToState($db["gamestate"]["state_id"]);
        }
        if (isset($db["players"])) {
            $this->game->_colors = array_column($db["players"], "player_color");
        }
        $this->game->curid = $this->currentPlayerId;
    }

    public function saveState(): void {
        $finalDb = [
            "tokens" => $this->game->tokens->getAllTokens(),
            "machine" => array_values($this->game->machine->db->all()),
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

    public function game_getAllDatas() {
        $stateId = $this->game->gamestate->getCurrentMainStateId();
        $activePlayer = (int) $this->game->getActivePlayerId();

        $stateNameMap = $this->states;
        /** @var GameState */
        $stateInst = $stateNameMap[$stateId];
        $this->game->systemAssert("state not found $stateId", $stateInst);
        $stateName = $stateInst->name;

        $stateArgs = [];
        try {
            $stateArgs = $stateInst->getArgs($activePlayer);
        } catch (\Throwable $e) {
            echo "Warning: getArgs for state $stateName failed: " . $e->getMessage() . "\n";
        }

        $gamedatas = $this->game->getAllDatas();
        $gamedatas["gamestate"] = [
            "id" => $stateId,
            "name" => $stateName,
            "active_player" => $activePlayer,
            "args" => $stateArgs,
        ];
        return $gamedatas;
    }

    public function saveNotifications(): void {
        self::saveJson("$this->stagingDir/notifications.json", $this->game->notify->log);
        echo "Wrote staging/notifications.json (" . count($this->game->notify->log) . " notifications)\n";
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
        $state = $this->game->machine->dispatchAll();
        $this->game->gamestate->jumpToState($this->getStateId($state));
        echo "  → Dispatched → state: " . $this->game->gamestate->getCurrentMainStateId() . " ($state)\n";
    }

    public function emitGameStateChange(): void {
        $newGamestate = $this->game_getAllDatas()["gamestate"] ?? []; // XXX bad
        $gsArgs = $newGamestate["args"] ?? [];
        if (isset($gsArgs["_private"]) && is_array($gsArgs["_private"])) {
            $activeKey = (string) ($newGamestate["active_player"] ?? $this->currentPlayerId);
            $gsArgs["_private"] =
                $gsArgs["_private"][$activeKey] ?? ($gsArgs["_private"][$this->currentPlayerId] ?? reset($gsArgs["_private"]));
        }

        $this->game->notify->all("gameStateChange", "", [
            "id" => $newGamestate["id"] ?? 0,
            "name" => $newGamestate["name"] ?? "",
            "active_player" => (string) ($newGamestate["active_player"] ?? $this->currentPlayerId),
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
