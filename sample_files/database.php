<?php

use Laf\Util\Settings;

$db_host = $db_username = $db_password = $db_name = null;
if(isset($_SERVER['SERVER_NAME'])){
    $_server_name = $_SERVER['SERVER_NAME'];
}

if(php_sapi_name() == 'cli'){
    $_server_name = 'localhost';
}

switch ($_server_name) {
    case "localhost":
    case "intrepicure.local":
    case "my.intrepicure.local":
        $db_host = 'localhost';
        $db_username = 'root';
        $db_password = '';
        $db_name = 'lafshell';
        break;
}

/**
 * Database settings
 */
$settings = Settings::getInstance();
$settings->setProperty('database.hostname', $db_host);
$settings->setProperty('database.database_name', $db_name);
$settings->setProperty('database.username', $db_username);
$settings->setProperty('database.password', $db_password);