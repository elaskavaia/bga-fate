<?php

/**
 * Harness PHP runner: loads a play scenario, runs it against GameUT, outputs
 * gamedatas.json and notifications.json to staging/.
 *
 * Usage: php8.4 misc/harness/play.php [play_name]
 *   play_name: subfolder under staging/plays/ (default: setup)
 *
 * All play files (script.json, db.json) are read/written from staging/plays/<name>/.
 * Example scripts to copy in are in misc/harness/plays/.
 */

declare(strict_types=1);

// Bootstrap — same autoloader as PHPUnit tests
require_once __DIR__ . "/../../modules/php/Tests/_autoload.php";
require_once __DIR__ . "/../../modules/php/Tests/MachineInMem.php";
require_once __DIR__ . "/../../modules/php/Tests/TokensInMem.php";
require_once __DIR__ . "/../../modules/php/Tests/GameUT.php";
require_once __DIR__ . "/GameHarness.php";

// ── Helpers ───────────────────────────────────────────────────────────────────

function loadJson(string $path): mixed {
    if (!file_exists($path)) {
        return null;
    }
    $data = json_decode(file_get_contents($path), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("JSON parse error in $path: " . json_last_error_msg() . "\n");
    }
    return $data;
}

function saveJson(string $path, mixed $data): void {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function matchArgs(ReflectionMethod $ref, array $data): array {
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

function getStateId(GameHarness $game, mixed $targetClass) {
    if ($targetClass instanceof \Bga\GameFramework\States\GameState) {
        $stateId = $targetClass->id;
    } elseif (is_numeric($targetClass)) {
        $stateId = $targetClass;
    } else {
        $ref = new ReflectionClass($targetClass);
        $inst = $ref->newInstance($game);
        $stateId = $inst->id;
    }
    return $stateId;
}

function dispatchEndpoint(GameHarness $game, string $endpoint, array $data): void {
    $states = $game->states;
    if (str_starts_with($endpoint, "action_")) {
        $stateId = $game->gamestate->getCurrentMainStateId();
        $stateInst = $states[$stateId];
        $ref = new ReflectionMethod($stateInst, $endpoint);
        $state = $ref->invokeArgs($stateInst, matchArgs($ref, $data));
        $game->gamestate->jumpToState(getStateId($game, $state));
    } elseif (str_starts_with($endpoint, "debug_")) {
        // Call debug method on game via reflection with named params
        $methodName = $endpoint;
        if (!method_exists($game, $methodName)) {
            die("Unknown debug endpoint: $endpoint\n");
        }
        $ref = new ReflectionMethod($game, $methodName);

        $ref->invokeArgs($game, matchArgs($ref, $data));
    } else {
        die("Unknown endpoint: $endpoint\n");
    }
}

// ── Main ──────────────────────────────────────────────────────────────────────

// Parse args: php play.php [-debug <function>] [-reset] [play_name]
$debugFunction = null;
$resetFlag = false;
$args = array_slice($argv, 1);
if (($idx = array_search("-debug", $args)) !== false) {
    $debugFunction = $args[$idx + 1] ?? null;
    if (!$debugFunction) {
        die("Usage: php play.php -debug <function_name> [play_name]\n");
    }
    array_splice($args, $idx, 2);
}
if (($idx = array_search("-reset", $args)) !== false) {
    $resetFlag = true;
    array_splice($args, $idx, 1);
}
$playName = $args[0] ?? ($debugFunction ? "debug" : "setup");
$stagingDir = __DIR__ . "/../../staging";
$stateDir = "$stagingDir/plays/$playName";
// In debug mode, always write output to staging/plays/debug/ to avoid corrupting the source scenario
$writeDir = $debugFunction ? "$stagingDir/plays/debug" : $stateDir;

echo "Running play: $playName" . ($debugFunction ? " (debug: $debugFunction)" : "") . "\n";

// Auto-seed staging script from misc/harness/plays/<name>.json if staging copy is missing or older
$exampleScript = __DIR__ . "/plays/$playName.json";
$stagingScript = "$stateDir/script.json";
if (file_exists($exampleScript)) {
    $exampleMtime = filemtime($exampleScript);
    $stagingMtime = file_exists($stagingScript) ? filemtime($stagingScript) : 0;
    if ($stagingMtime < $exampleMtime) {
        if (!is_dir($stateDir)) {
            mkdir($stateDir, 0777, true);
        }
        copy($exampleScript, $stagingScript);
        echo "Seeded $stagingScript from example.\n";
    }
}

// Load script (not needed in debug mode)
if ($debugFunction) {
    $currentPlayerId = 10;
    $steps = [];
    $reset = $resetFlag;
} else {
    $script = loadJson($stagingScript);
    echo "Loading $stagingScript\n";
    if ($script === null) {
        die("No script.json found at $stagingScript\n");
    }
    $currentPlayerId = (int) ($script["current_player_id"] ?? 10);
    $steps = $script["steps"] ?? [];
    $reset = (bool) ($script["reset"] ?? false);
}

// Boot GameUT
$game = new GameHarness();
$game->curid = $currentPlayerId;

// Load db state if present (skipped when reset=true)
$db = $reset ? null : loadJson("$stateDir/db.json");
if ($reset) {
    echo "Reset mode: starting fresh.\n";
} elseif ($db !== null) {
    echo "Loading db.json...\n";
    // Restore tokens
    foreach ($db["tokens"] ?? [] as $rec) {
        $game->tokens->DbCreateTokens([[$rec["key"], $rec["location"], $rec["state"]]]);
    }
    // Restore machine via loadRows() to preserve the internal reference in MachineInMem
    $game->machine->db->loadRows($db["machine"] ?? []);
    // Restore gamestate
    if (isset($db["gamestate"]["active_player"])) {
        $game->gamestate->changeActivePlayer($db["gamestate"]["active_player"]);
    }
    if (isset($db["gamestate"]["state_id"])) {
        $game->gamestate->jumpToState($db["gamestate"]["state_id"]);
    }
    // Restore players
    if (isset($db["players"])) {
        $game->_colors = array_column($db["players"], "player_color");
    }
    $game->curid = $currentPlayerId;
} else {
    echo "No db.json found, starting fresh.\n";
}

// ── Helpers that run after each dispatched step ───────────────────────────────

function runDispatchLoop(GameHarness $game): void {
    $state = $game->machine->dispatchAll();
    $game->gamestate->jumpToState(getStateId($game, $state));
    echo "  → Dispatched → state: " . $game->gamestate->getCurrentMainStateId() . " ($state)\n";
}

function emitGameStateChange(GameHarness $game, int $currentPlayerId): void {
    // Emit a synthetic gameStateChange notification so the JS renderer can call onEnteringState.
    // Shape mirrors the real BGA gameStateChange push message:
    //   args: { id, name, active_player, type, args: { description, _private: <unwrapped opInfo> } }
    $newGamestate = $game->getAllDatas()["gamestate"] ?? [];
    $gsArgs = $newGamestate["args"] ?? [];
    // Unwrap _private to the active player's data (BGA does this before pushing to that player)
    if (isset($gsArgs["_private"]) && is_array($gsArgs["_private"])) {
        $activeKey = (string) ($newGamestate["active_player"] ?? $currentPlayerId);
        $gsArgs["_private"] = $gsArgs["_private"][$activeKey] ?? ($gsArgs["_private"][$currentPlayerId] ?? reset($gsArgs["_private"]));
    }

    $game->notify->all("gameStateChange", "", [
        "id" => $newGamestate["id"] ?? 0,
        "name" => $newGamestate["name"] ?? "",
        "active_player" => (string) ($newGamestate["active_player"] ?? $currentPlayerId),
        "type" => "activeplayer",
        "args" => $gsArgs,
    ]);
}

// ── Run steps ─────────────────────────────────────────────────────────────────

$x = count($steps);
echo "Loading $x steps\n";

foreach ($steps as $i => $step) {
    $endpoint = $step["endpoint"] ?? "";
    echo "Step " . ($i + 1) . ": $endpoint\n";
    $op = $game->machine->createTopOperationFromDbForOwner(null); // null means any
    $game->debugLog("  → Starting step op " . ($op ? $op->getType() : "EMPTY"));
    dispatchEndpoint($game, $endpoint, $step);
    runDispatchLoop($game);
    emitGameStateChange($game, $currentPlayerId);

    if ($step["reload"] ?? false) {
        $gamedatas = $game->getAllDatas();
        saveJson("$stagingDir/gamedatas.json", $gamedatas);
        echo "  → Wrote staging/gamedatas.json (reload after step)\n";
    }

    $op = $game->machine->createTopOperationFromDbForOwner(null); // null means any
    $game->debugLog("  → Final step op " . $op->getType());
}

// ── Debug mode: run a single function and capture state ───────────────────────

if ($debugFunction) {
    echo "Calling: $debugFunction\n";
    dispatchEndpoint($game, $debugFunction, []);
    runDispatchLoop($game);
    emitGameStateChange($game, $currentPlayerId);
}

// Always write gamedatas.json (debug mode doesn't have a reload step)
$gamedatas = $game->getAllDatas();
saveJson("$stagingDir/gamedatas.json", $gamedatas);
echo "Wrote staging/gamedatas.json\n";

// Save notifications
saveJson("$stagingDir/notifications.json", $game->notify->log);
echo "Wrote staging/notifications.json (" . count($game->notify->log) . " notifications)\n";

// Save final db state back to play dir
$finalDb = [
    "tokens" => $game->tokens->getAllTokens(),
    "machine" => array_values($game->machine->db->all()),
    "gamestate" => [
        "state_id" => $game->gamestate->getCurrentMainStateId(),
        "active_player" => (int) $game->getActivePlayerId(),
    ],
    "players" => array_values($game->loadPlayersBasicInfos()),
];
saveJson("$writeDir/db.json", $finalDb);
$writeName = $debugFunction ? "debug" : $playName;
echo "Wrote staging/plays/$writeName/db.json\n";

echo "Done.\n";
