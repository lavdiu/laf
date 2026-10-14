<?php

/**
 * Every setting LAF reads, in one array; bootstrap.php loads it with Settings::loadFromFile(). Nested keys
 * become dotted keys ('database' => ['hostname' => ...] is database.hostname). The LAF_DB_* and APP_ENV
 * environment variables override the values below. Keep this file out of version control when it holds
 * production credentials (or load them from the environment).
 */

return [
    'app' => [
        'env' => 'development',           // anything but 'production' shows errors
        'timezone' => 'UTC',
    ],
    'project' => [
        'package_name' => 'MyApp',        // namespace of the generated classes: app/lib/MyApp/
    ],
    'database' => [
        'engine' => 'mysql',              // mysql | postgres
        'hostname' => 'localhost',
        'port' => 3306,                   // 5432 for PostgreSQL
        'database_name' => 'myapp',
        'username' => 'root',
        'password' => '',
        // optional: charset, socket, persistent, connect_timeout, options, dsn, dsn_extra, timezone,
        // schema (PostgreSQL search_path), init_commands, empty_string_as_null, reconnect_on_loss
    ],
    'settings' => [
        'use_pretty_url' => true,         // /module/submodule/action/id instead of ?module=...
        'label' => [
            'translations' => [],         // page labels, e.g. 'add-new' => 'Regjistro' (PageGenerator::LABEL_DEFAULTS)
        ],
    ],
    'form' => [
        'restrict_to_rendered_fields' => false,   // ignore posted fields the form did not draw
        'native_date_inputs' => false,            // <input type="date"> instead of the locale format + picker
        'select_autocomplete_threshold' => 0,     // foreign keys to tables with more rows get an Autocomplete (0 = off)
    ],
    'orm' => [
        'validate_on_store' => false,             // field rules + validateRecord() inside store()/insert()/update()
    ],
    'generator' => [
        'many_to_many' => 'marked',               // linker tables that become pickers: marked ({"linker": true}) | auto | off
    ],
    'upload' => [
        'documents_directory' => __DIR__ . '/../public/document/',
        'allowed_mime_types' => ['image/*', 'application/pdf'],
        'max_image_size' => 1200,
        'thumbnail_size' => 300,
        'cache_max_age' => 31536000,
        'not_found_url' => '/errors/404',
    ],
    'locale' => [
        'language' => 'en',
        'date' => ['format' => 'Y-m-d'],
        'datetime' => ['format' => 'Y-m-d H:i'],
        'time' => ['format' => 'H:i'],
        'decimal_separator' => '.',
        'thousands_separator' => ',',
    ],
];
