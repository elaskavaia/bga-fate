<?php

/**
 * Harness PHP runner: loads a play scenario, runs it against GameUT, outputs
 * gamedatas.json, notifications.json, and db.json to the output directory.
 *
 * Usage:
 *   php8.4 misc/harness/play.php [options]
 *
 * Options:
 *   --debug <function>      Call a single debug_* function on GameHarness
 *   --script <path.json>    Load a scenario script (steps + current_player_id)
 *   --db <path.json>        Load saved state from this db.json before running
 *   --output <dir>          Output directory (default: staging/)
 *
 * With no --script and no --debug, defaults to misc/harness/plays/setup.json.
 */

declare(strict_types=1);

// Bootstrap — same autoloader as PHPUnit tests
require_once __DIR__ . "/../../modules/php/Tests/_autoload.php";
require_once __DIR__ . "/../../modules/php/Tests/Stubs/MachineInMem.php";
require_once __DIR__ . "/../../modules/php/Tests/Stubs/TokensInMem.php";
require_once __DIR__ . "/../../modules/php/Tests/Stubs/GameUT.php";
require_once __DIR__ . "/GameHarness.php";
require_once __DIR__ . "/GameDriver.php";

// ── Parse CLI args ───────────────────────────────────────────────────────────

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

// Default: setup script from source
if (!$scriptPath && !$debugFunction) {
    $scriptPath = __DIR__ . "/plays/setup.json";
}

$stagingDir = $outputDir ?? __DIR__ . "/../../staging";

// ── Load script ──────────────────────────────────────────────────────────────

$currentPlayerId = 10;
$steps = [];

if ($scriptPath) {
    $script = GameDriver::loadJson($scriptPath);
    if ($script === null) {
        die("Script not found: $scriptPath\n");
    }
    echo "Loading script: $scriptPath\n";
    $currentPlayerId = (int) ($script["current_player_id"] ?? 10);
    $steps = $script["steps"] ?? [];
}

// ── Run ──────────────────────────────────────────────────────────────────────

$driver = new GameDriver("Fate", $stagingDir, $currentPlayerId);

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
