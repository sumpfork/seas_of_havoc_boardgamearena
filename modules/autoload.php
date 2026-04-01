<?php

define("APP_GAMEMODULE_PATH", getenv('APP_GAMEMODULE_PATH')); 

spl_autoload_register(function ($class_name) {
    // Only try to load if the file exists
    $file = $class_name . ".php";
    if (file_exists($file)) {
        include $file;
    }
});

?>
