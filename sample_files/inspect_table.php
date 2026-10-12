<?php

/**
 * Prints the column metadata the generators work from: `php sample_files/inspect_table.php person`.
 * TableInspectorFactory picks the MySQL or PostgreSQL inspector from Settings 'database.engine'; both return
 * the same column shape (upper-case information_schema keys plus FOREIGN_KEY).
 */

declare(strict_types=1);

use Laf\Generator\TableInspectorFactory;

require_once __DIR__ . '/bootstrap.php';

$table = $argv[1] ?? '';
if ($table === '') {
    fwrite(STDERR, "Usage: php inspect_table.php <table_name>\n");
    exit(2);
}

$inspector = TableInspectorFactory::create($table);
$inspector->inspect();
foreach ($inspector->getColumns() as $column) {
    printf(
        "%-30s %-20s %s%s\n",
        $column['COLUMN_NAME'],
        $column['COLUMN_TYPE'] ?? $column['DATA_TYPE'],
        $column['IS_NULLABLE'] === 'NO' ? 'NOT NULL' : 'NULL',
        !empty($column['FOREIGN_KEY']['referenced_table_name']) ? ' FK -> ' . $column['FOREIGN_KEY']['referenced_table_name'] . '.' . $column['FOREIGN_KEY']['referenced_column_name'] : ''
    );
}
