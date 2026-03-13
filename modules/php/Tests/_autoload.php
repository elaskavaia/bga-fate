<?php
define("APP_GAMEMODULE_PATH", getenv("APP_GAMEMODULE_PATH"));
spl_autoload_register(function ($class_name) {
    switch ($class_name) {
        case "Table":
        case "Notify":
        case "Bga\\GameFramework\\Notify":
        case "Bga\\GameFramework\\Table":
            // contact on purpose
            include APP_GAMEMODULE_PATH . "/module/table" . "/table.game.php";
            return;
        case "Deck":
            include APP_GAMEMODULE_PATH . "/module/common/deck.game.php";
            return;
    }

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
