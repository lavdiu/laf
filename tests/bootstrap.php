<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Laf\Util\Settings;

/**
 * Configure database from environment variables (for integration tests).
 * These are optional - unit tests don't need them.
 */
$dbEngine = getenv('LAF_DB_ENGINE') ?: 'mysql';
$dbHost = getenv('LAF_DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('LAF_DB_PORT') ?: ($dbEngine === 'postgres' ? '5432' : '3306');
$dbName = getenv('LAF_DB_NAME') ?: 'laf_test';
$dbUser = getenv('LAF_DB_USER') ?: 'root';
$dbPass = getenv('LAF_DB_PASS') ?: '';

Settings::set('database.hostname', $dbHost);
Settings::set('database.database_name', $dbName);
Settings::set('database.username', $dbUser);
Settings::set('database.password', $dbPass);
Settings::set('database.port', $dbPort);
Settings::set('database.engine', $dbEngine);
Settings::set('project.package_name', 'LafShell');
