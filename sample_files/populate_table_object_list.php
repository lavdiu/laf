<?php

/**
 * Populate the object_list table with all database tables.
 * Run this after setting up your database connection.
 *
 * Usage: php sample_files/populate_table_object_list.php (bootstrap.php configures the connection)
 */

declare(strict_types=1);

use Laf\Database\BaseObject;

require_once __DIR__ . '/bootstrap.php';

$inserted = BaseObject::populateTableObjectList();
echo "Inserted {$inserted} new table(s) into object_list.\n";
