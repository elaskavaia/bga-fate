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
require_once __DIR__ . "/GameDriver.php";

// ── Parse CLI args ───────────────────────────────────────────────────────────

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
$writeDir = $debugFunction ? "$stagingDir/plays/debug" : $stateDir;

echo "Running play: $playName" . ($debugFunction ? " (debug: $debugFunction)" : "") . "\n";

// ── Auto-seed staging script from example ────────────────────────────────────

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

// ── Load script ──────────────────────────────────────────────────────────────

if ($debugFunction) {
    $currentPlayerId = 10;
    $steps = [];
    $reset = $resetFlag;
} else {
    $script = GameDriver::loadJson($stagingScript);
    echo "Loading $stagingScript\n";
    if ($script === null) {
        die("No script.json found at $stagingScript\n");
    }
    $currentPlayerId = (int) ($script["current_player_id"] ?? 10);
    $steps = $script["steps"] ?? [];
    $reset = (bool) ($script["reset"] ?? false);
}

// ── Run ──────────────────────────────────────────────────────────────────────

$driver = new GameDriver($stagingDir, $writeDir, $currentPlayerId);

if ($reset) {
    echo "Reset mode: starting fresh.\n";
} else {
    $driver->loadState($stateDir);
}

$driver->runSteps($steps);

if ($debugFunction) {
    $driver->runDebug($debugFunction);
}

$driver->saveGamedatas();
$driver->saveNotifications();
$driver->saveState();

$writeName = $debugFunction ? "debug" : $playName;
echo "Wrote staging/plays/$writeName/db.json\n";
echo "Done.\n";
