<?php

/**
 * building database connection logic
 */
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
        $db_name = 'intrepicure';
        break;
    case "intrepicurebeta.com":
    case "my.intrepicurebeta.com":
        $db_host = 'databases.intrepicure.com';
        $db_username = 'intrepicureuname';
        $db_password = '!ntrepiCur3Pwd?';
        $db_name = 'comintrepicurebetasite';
        break;
    case "intrepicure.com":
    case "my.intrepicure.com":
    case "intrepicure.net":
    case "my.intrepicure.net":
    case "intrepicure.org":
    case "my.intrepicure.org":
        $db_host = 'databases.intrepicure.com';
        $db_username = 'intrepicureuname';
        $db_password = '!ntrepiCur3Pwd?';
        $db_name = 'comintrepicuresite';
        break;
}

/**
 * Database settings
 */
$settings = \Laf\Util\Settings::getInstance();
$settings->setProperty('db_hostname', $db_host);
$settings->setProperty('db_databasename', $db_name);
$settings->setProperty('db_username', $db_username);
$settings->setProperty('db_password', $db_password);