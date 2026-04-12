<?php
define("APP_GAMEMODULE_PATH", getenv("APP_GAMEMODULE_PATH"));
require_once APP_GAMEMODULE_PATH . "/php/stubs/BgaFrameworkStubs.php";
// Load test-only base class lazily; only when PHPUnit is on the classpath.
if (class_exists("PHPUnit\\Framework\\TestCase", true) && file_exists(__DIR__ . "/Operations/AbstractOpTestCase.php")) {
    require_once __DIR__ . "/Operations/AbstractOpTestCase.php";
}
if (class_exists("PHPUnit\\Framework\\TestCase", true) && file_exists(__DIR__ . "/Cards/AbstractCardTestCase.php")) {
    require_once __DIR__ . "/Cards/AbstractCardTestCase.php";
}
spl_autoload_register(function ($class_name) {
    $namespacePrefix = "Bga\\Games\\Fate\\";
    if (strpos($class_name, $namespacePrefix) !== 0) {
        return;
    }
    $relativeClass = substr($class_name, strlen($namespacePrefix));
    $relativePath = str_replace("\\", "/", $relativeClass) . ".php";

    // Search in modules/php first, then tests/
    foreach ([__DIR__ . "/../modules/php", __DIR__] as $baseDir) {
        $filePath = "$baseDir/$relativePath";
        if (file_exists($filePath)) {
            require_once $filePath;
            return;
        }
    }
});
