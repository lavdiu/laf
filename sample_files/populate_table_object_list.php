<?php

/**
 * Populate the object_list table with all database tables.
 * Run this after setting up your database connection.
 *
 * Usage:
 *   require_once 'path/to/config.php';
 *   require_once 'path/to/populate_table_object_list.php';
 */

use Laf\Database\BaseObject;

$inserted = BaseObject::populateTableObjectList();
echo "Inserted {$inserted} new table(s) into object_list.\n";
