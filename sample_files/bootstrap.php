<?php

/**
 * Application bootstrap shared by the web front controller, `vendor/bin/laf-generate --bootstrap=app/bootstrap.php`
 * and any script: the Composer autoloader, every setting from settings.php, environment overrides, and (web
 * requests only) the session and the user the audit log records. Copy it next to settings.php and adjust the paths.
 */

declare(strict_types=1);

use Laf\Database\BaseObject;
use Laf\Util\Settings;

require_once __DIR__ . '/../vendor/autoload.php';

// 1. every setting from one array; nested keys become dotted keys (database.hostname, upload.max_image_size)
if (!Settings::getInstance()->loadFromFile(__DIR__ . '/settings.php')) {
    throw new RuntimeException('settings.php is missing or does not return an array');
}

// 2. environment overrides, so one bootstrap serves the workstation, CI and production
foreach ([
    'APP_ENV' => 'app.env',
    'LAF_DB_ENGINE' => 'database.engine',
    'LAF_DB_HOST' => 'database.hostname',
    'LAF_DB_PORT' => 'database.port',
    'LAF_DB_NAME' => 'database.database_name',
    'LAF_DB_USER' => 'database.username',
    'LAF_DB_PASS' => 'database.password',
] as $variable => $key) {
    $value = getenv($variable);
    if ($value !== false) {
        Settings::set($key, $value);
    }
}

date_default_timezone_set(Settings::getString('app.timezone', 'UTC'));

if (Settings::getString('app.env', 'production') !== 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// 3. web requests only
if (PHP_SAPI !== 'cli') {
    session_start();
    if (isset($_SESSION['user_id'])) {
        BaseObject::setActiveUserId((int)$_SESSION['user_id']);
    }
}
