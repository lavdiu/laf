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
// optional: engine (mysql | postgres, default mysql), port (default 3306 / 5432), charset, socket, persistent,
// connect_timeout, options, dsn, dsn_extra, timezone, schema, init_commands, empty_string_as_null
// $settings->setProperty('database.engine', 'mysql');
// $settings->setProperty('database.port', 3306);
// $settings->setProperty('database.timezone', '+00:00');