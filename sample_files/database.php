<?php

use Laf\Util\Settings;

/**
 * Database settings
 */
$settings = Settings::getInstance();
$settings->setProperty('database.hostname','localhost');
$settings->setProperty('database.database_name', 'lafshell');
$settings->setProperty('database.username', 'root');
$settings->setProperty('database.password', '');