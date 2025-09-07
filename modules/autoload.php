<?php

define("APP_GAMEMODULE_PATH", "/Users/pgorniak/src/p/bga-sharedcode/misc/"); 

spl_autoload_register(function ($class_name) {
    switch ($class_name) {
        case "APP_GameClass":
            include APP_GAMEMODULE_PATH."/module/table/table.game.php";
            break;
        default:
            include $class_name . ".php";
            break;
    }
});

?>
