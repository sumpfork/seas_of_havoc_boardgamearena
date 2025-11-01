<?php

define("APP_GAMEMODULE_PATH", "/Users/pgorniak/src/p/bga-sharedcode/misc/"); 

spl_autoload_register(function ($class_name) {
    // Skip namespaced classes (like PHPUnit classes) - they should be handled by composer
    if (strpos($class_name, '\\') !== false) {
        return;
    }
    
    switch ($class_name) {
        case "APP_GameClass":
            include APP_GAMEMODULE_PATH."/module/table/table.game.php";
            break;
        default:
            // Only try to load if the file exists
            $file = $class_name . ".php";
            if (file_exists($file)) {
                include $file;
            }
            break;
    }
});

?>
