<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * End-to-end tests for the harness CLI (play.php).
 * These test the external contract (files produced) not internal classes,
 * so they survive refactoring of GameDriver/GameWrapper internals.
 */
final class HarnessTest extends TestCase {
    private string $outputDir;

    protected function setUp(): void {
        $this->outputDir = sys_get_temp_dir() . "/_test_" . getmypid();
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }
    }

    protected function tearDown(): void {
        // Clean up output files
        foreach (glob("$this->outputDir/*") as $file) {
            unlink($file);
        }
        if (is_dir($this->outputDir)) {
            rmdir($this->outputDir);
        }
    }

    private function runPlayPhp(string $args = ""): int {
        $playPhp = __DIR__ . "/play.php";
        $cmd = "php8.4 $playPhp --output $this->outputDir $args 2>&1";
        exec($cmd, $output, $exitCode);
        return $exitCode;
    }

    private function readJson(string $filename): array {
        $path = "$this->outputDir/$filename";
        $this->assertFileExists($path, "$filename should be created");
        $data = json_decode(file_get_contents($path), true);
        $this->assertNotNull($data, "$filename should be valid JSON");
        return $data;
    }

    // -------------------------------------------------------------------------
    // Setup scenario produces all output files
    // -------------------------------------------------------------------------

    public function testSetupScenarioProducesOutputFiles(): void {
        $scenarioPath = __DIR__ . "/plays/setup.json";
        $exitCode = $this->runPlayPhp("--scenario $scenarioPath");
        $this->assertEquals(0, $exitCode, "play.php should exit cleanly");

        $this->assertFileExists("$this->outputDir/gamedatas.json");
        $this->assertFileExists("$this->outputDir/notifications.json");
        $this->assertFileExists("$this->outputDir/db.json");
    }

    // -------------------------------------------------------------------------
    // gamedatas.json structure
    // -------------------------------------------------------------------------

    public function testGamedatasHasRequiredKeys(): void {
        $scenarioPath = __DIR__ . "/plays/setup.json";
        $this->runPlayPhp("--scenario $scenarioPath");
        $gamedatas = $this->readJson("gamedatas.json");

        $this->assertArrayHasKey("gamestate", $gamedatas);
        $this->assertArrayHasKey("players", $gamedatas);
        $this->assertArrayHasKey("tokens", $gamedatas);
    }

    public function testGamestateHasRequiredFields(): void {
        $scenarioPath = __DIR__ . "/plays/setup.json";
        $this->runPlayPhp("--scenario $scenarioPath");
        $gamedatas = $this->readJson("gamedatas.json");

        $gs = $gamedatas["gamestate"];
        $this->assertArrayHasKey("id", $gs);
        $this->assertArrayHasKey("name", $gs);
        $this->assertArrayHasKey("active_player", $gs);
        $this->assertIsInt($gs["id"]);
        $this->assertNotEmpty($gs["name"]);
    }

    // -------------------------------------------------------------------------
    // notifications.json structure
    // -------------------------------------------------------------------------

    public function testNotificationsIsNonEmptyArray(): void {
        $scenarioPath = __DIR__ . "/plays/setup.json";
        $this->runPlayPhp("--scenario $scenarioPath");
        $notifs = $this->readJson("notifications.json");

        $this->assertNotEmpty($notifs);
        $this->assertIsList($notifs);
    }

    public function testLastNotificationIsGameStateChange(): void {
        $scenarioPath = __DIR__ . "/plays/setup.json";
        $this->runPlayPhp("--scenario $scenarioPath");
        $notifs = $this->readJson("notifications.json");

        $last = end($notifs);
        $this->assertEquals("gameStateChange", $last["type"]);
    }

    // -------------------------------------------------------------------------
    // db.json structure and round-trip
    // -------------------------------------------------------------------------

    public function testDbJsonHasRequiredKeys(): void {
        $scenarioPath = __DIR__ . "/plays/setup.json";
        $this->runPlayPhp("--scenario $scenarioPath");
        $db = $this->readJson("db.json");

        $this->assertArrayHasKey("tokens", $db);
        $this->assertArrayHasKey("machine", $db);
        $this->assertArrayHasKey("gamestate", $db);
        $this->assertArrayHasKey("players", $db);
    }

    public function testDbRoundTripPreservesState(): void {
        $scenarioPath = __DIR__ . "/plays/setup.json";
        $this->runPlayPhp("--scenario $scenarioPath");
        $db1 = $this->readJson("db.json");

        // Load the saved db.json and save again (no steps, no debug)
        $dbPath = "$this->outputDir/db.json";
        $this->runPlayPhp("--db $dbPath");
        $db2 = $this->readJson("db.json");

        $this->assertEquals($db1["gamestate"]["state_id"], $db2["gamestate"]["state_id"], "State ID should survive round-trip");
        $this->assertCount(count($db1["tokens"]), $db2["tokens"], "Token count should survive round-trip");
        $this->assertCount(count($db1["players"]), $db2["players"], "Player count should survive round-trip");
    }

    // -------------------------------------------------------------------------
    // Debug function: behavioral check
    // -------------------------------------------------------------------------

    public function testDebugGainXpAddsYellowCrystals(): void {
        $scenarioPath = __DIR__ . "/plays/setup.json";
        // Run setup first
        $this->runPlayPhp("--scenario $scenarioPath");
        $baselineGamedatas = $this->readJson("gamedatas.json");
        $baselineYellow = count(
            array_filter(
                $baselineGamedatas["tokens"],
                fn($t) => str_starts_with($t["key"], "crystal_yellow") && str_starts_with($t["location"], "tableau_")
            )
        );

        // Run with debug_Op_gainXp on top
        $this->runPlayPhp("--scenario $scenarioPath --debug debug_Op_gainXp");
        $gamedatas = $this->readJson("gamedatas.json");
        $afterYellow = count(
            array_filter(
                $gamedatas["tokens"],
                fn($t) => str_starts_with($t["key"], "crystal_yellow") && str_starts_with($t["location"], "tableau_")
            )
        );

        $this->assertGreaterThan($baselineYellow, $afterYellow, "debug_Op_gainXp should add yellow crystals to tableau");
    }
}
