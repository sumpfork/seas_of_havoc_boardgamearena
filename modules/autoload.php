<?php

define("APP_GAMEMODULE_PATH", getenv('APP_GAMEMODULE_PATH')); 

spl_autoload_register(function ($class_name) {
  
    switch ($class_name) {
        case "APP_DbObject":
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
