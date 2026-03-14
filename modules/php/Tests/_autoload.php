<?php
define("APP_GAMEMODULE_PATH", getenv("APP_GAMEMODULE_PATH"));
require_once APP_GAMEMODULE_PATH . "/php/stubs/BgaFrameworkStubs.php";
spl_autoload_register(function ($class_name) {
    // Define your base namespace and its corresponding base directory
    $namespacePrefix = "Bga\\Games\\Fate\\";
    $baseDirectory = __DIR__ . "/..";

    // Check if the class belongs to the defined namespace
    if (strpos($class_name, $namespacePrefix) === 0) {
        // Remove the namespace prefix from the class name
        $relativeClass = substr($class_name, strlen($namespacePrefix));

        // Replace namespace separators with directory separators and append .php
        $filePath = $baseDirectory . "/" . str_replace("\\", "/", $relativeClass) . ".php";

        // Include the file if it exists
        if (file_exists($filePath)) {
            require_once $filePath;
            return;
        }
    }
});

?>
