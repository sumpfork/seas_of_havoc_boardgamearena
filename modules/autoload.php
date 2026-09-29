<?php

define("APP_GAMEMODULE_PATH", getenv("APP_GAMEMODULE_PATH"));

if (APP_GAMEMODULE_PATH === false || APP_GAMEMODULE_PATH === "") {
    throw new RuntimeException("APP_GAMEMODULE_PATH must point to bga-sharedcode/misc/ for tests");
}

require_once rtrim(APP_GAMEMODULE_PATH, "/") . "/php/stubs/BgaFrameworkStubs.php";

// What the framework does on BGA: the game's namespace lives in modules/php/ (PSR-4).
spl_autoload_register(function ($class_name) {
    $prefix = "Bga\\Games\\SeasOfHavoc\\";
    if (str_starts_with($class_name, $prefix)) {
        $file = __DIR__ . "/php/" . str_replace("\\", "/", substr($class_name, strlen($prefix))) . ".php";
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

spl_autoload_register(function ($class_name) {
    // Only try to load if the file exists
    $file = $class_name . ".php";
    if (file_exists($file)) {
        include $file;
    }
});

?>
