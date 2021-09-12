<?php

use Laf\Util\Settings;

date_default_timezone_set("America/New_York");
require_once(__DIR__ . '/functions.php');


/**
 * including autoloaders
 */
$_autoloads = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../app/lib/MyApp/autoload.php', #set the generated autoloader here
];
foreach ($_autoloads as $_file) {
    if (file_exists($_file)) {
        require_once($_file);
    }
}

/**
 * this should be included after autoloaders, because it uses Settings class
 */
require_once(__DIR__ . '/database.php');
require_once(__DIR__ . '/constants.php');


/**
 * Setting Global settings
 */
$settings = Settings::getInstance();
$settings->setProperty('debug_level', 0);
