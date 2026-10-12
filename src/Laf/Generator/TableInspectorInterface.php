<?php

namespace Laf\Generator;

/**
 * Reads the metadata of one table (columns, keys, foreign keys, tables that
 * reference it) for the generators. One implementation per engine; get one
 * through TableInspectorFactory::create().
 *
 * Column rows use the upper-case information_schema names on both engines
 * (COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION,
 * NUMERIC_SCALE, COLUMN_DEFAULT, COLUMN_KEY 'PRI'|'UNI'|'', EXTRA 'auto_increment',
 * COLUMN_COMMENT, ...) plus FOREIGN_KEY (column_name, constraint_name,
 * referenced_table_name, referenced_column_name, delete_rule, update_rule) when
 * the column is a foreign key.
 */
interface TableInspectorInterface
{
    /**
     * Load the metadata (columns, foreign keys, referencing tables)
     */
    public function inspect(): void;

    /**
     * The inspected table name
     */
    public function getTable(): string;

    /**
     * Column name => metadata row (see the class docblock)
     * @return array<string, array<string, mixed>>
     */
    public function getColumns(): array;

    /**
     * Whether any column is a foreign key
     */
    public function hasForeignKeys(): bool;

    /**
     * Whether another table has a foreign key to this table's primary key
     */
    public function hasReferencingTables(): bool;

    /**
     * One row per foreign key column that points at this table's primary key:
     * ['table_name' => ..., 'column_name' => ..., 'constraint_name' => ...]
     * (the upper-case TABLE_NAME / COLUMN_NAME keys are present too)
     * @return array<int, array<string, string>>
     */
    public function getReferencingTables(): array;

    /**
     * First primary key column, null when the table has none
     */
    public function getPrimaryColumnName(): ?string;

    /**
     * Every primary key column in order (several for a composite key)
     * @return string[]
     */
    public function getPrimaryColumnNames(): array;

    /**
     * The column that represents a row in lists and selects: a JSON column
     * comment {"fields":{"setDisplayField":...}}, else "label", else "name",
     * else the second column, else the first
     */
    public function getDisplayColumnName(): string;
}
