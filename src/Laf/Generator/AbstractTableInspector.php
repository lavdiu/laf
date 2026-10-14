<?php

declare(strict_types=1);

namespace Laf\Generator;

/**
 * Shared state and rules of the engine inspectors: column rows are stored
 * with upper-case keys under their column name, primary key columns are
 * collected in order, foreign keys are attached to their column, and the
 * display column follows the JSON column comment before the name heuristics.
 */
abstract class AbstractTableInspector implements TableInspectorInterface
{
    protected string $table;

    /** @var array<string, array<string, mixed>> column name => metadata row */
    protected array $columns = [];

    /** @var string[] primary key columns in key order */
    protected array $primaryColumnNames = [];

    protected bool $hasForeignKeys = false;

    /** @var array<int, array<string, string>> */
    protected array $referencingTables = [];

    /** @var array<string, string[]> unique index name => columns in index order (primary key excluded) */
    protected array $uniqueKeys = [];

    /** The table's own comment (MySQL TABLE_COMMENT, PostgreSQL COMMENT ON TABLE), null when it has none */
    protected ?string $tableComment = null;

    protected bool $inspected = false;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function inspect(): void
    {
        $this->columns = [];
        $this->primaryColumnNames = [];
        $this->hasForeignKeys = false;
        $this->referencingTables = [];
        $this->uniqueKeys = [];
        $this->tableComment = null;

        $this->populateColumnsData();
        $this->populateForeignKeyData();
        $this->populateReferencingTables();
        $this->populateUniqueKeys();
        $this->populateTableComment();
        $this->inspected = true;
    }

    /**
     * Whether inspect() has run
     */
    public function isInspected(): bool
    {
        return $this->inspected;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function setTable(string $table): static
    {
        $this->table = $table;
        $this->inspected = false;
        return $this;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @param array<string, array<string, mixed>> $columns
     */
    public function setColumns(array $columns): static
    {
        $this->columns = $columns;
        return $this;
    }

    public function hasColumn(string $name): bool
    {
        return array_key_exists($name, $this->columns);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getColumn(string $name): ?array
    {
        return $this->columns[$name] ?? null;
    }

    /**
     * Whether the column is a foreign key (has a non-empty FOREIGN_KEY entry)
     */
    public function isForeignKeyColumn(string $name): bool
    {
        return !empty($this->columns[$name]['FOREIGN_KEY']);
    }

    /**
     * The FOREIGN_KEY entry of a column, null when it is not a foreign key
     * @return array<string, string|null>|null
     */
    public function getForeignKey(string $name): ?array
    {
        $fk = $this->columns[$name]['FOREIGN_KEY'] ?? null;
        return is_array($fk) && $fk !== [] ? $fk : null;
    }

    public function hasForeignKeys(): bool
    {
        return $this->hasForeignKeys;
    }

    public function hasReferencingTables(): bool
    {
        return count($this->referencingTables) > 0;
    }

    public function getReferencingTables(): array
    {
        return $this->referencingTables;
    }

    public function getPrimaryColumnName(): ?string
    {
        return $this->primaryColumnNames[0] ?? null;
    }

    public function getPrimaryColumnNames(): array
    {
        return $this->primaryColumnNames;
    }

    /**
     * Replace the primary key column(s)
     * @param string|string[]|null $primaryColumnName
     */
    public function setPrimaryColumnName($primaryColumnName): static
    {
        if ($primaryColumnName === null || $primaryColumnName === '') {
            $this->primaryColumnNames = [];
        } else {
            $this->primaryColumnNames = array_values(array_map('strval', (array)$primaryColumnName));
        }
        return $this;
    }

    public function getDisplayColumnName(): string
    {
        if ($this->columns === []) {
            throw new \RuntimeException(sprintf('Table "%s" has no columns (does it exist in the connected database?)', $this->table));
        }
        $names = array_keys($this->columns);
        foreach ($this->columns as $name => $column) {
            $fromComment = ColumnComment::displayField((string)($column['COLUMN_COMMENT'] ?? ''), (string)$name, $names);
            if ($fromComment !== null) {
                return $fromComment;
            }
        }
        foreach (['label', 'name'] as $candidate) {
            if (array_key_exists($candidate, $this->columns)) {
                return $candidate;
            }
        }
        return (string)($names[1] ?? $names[0]);
    }

    /**
     * Store a column row (keys upper-cased) and remember primary key columns
     * @param array<string, mixed> $column
     */
    protected function storeColumn(array $column): void
    {
        $column = array_change_key_case($column, CASE_UPPER);
        $name = (string)$column['COLUMN_NAME'];
        $this->columns[$name] = $column;
        if (($column['COLUMN_KEY'] ?? '') === 'PRI' && !in_array($name, $this->primaryColumnNames, true)) {
            $this->primaryColumnNames[] = $name;
        }
    }

    /**
     * Attach a foreign key row (lower-case keys as selected by the engine queries) to its column
     * @param array<string, mixed> $row
     */
    protected function storeForeignKey(array $row): void
    {
        $row = array_change_key_case($row, CASE_LOWER);
        $name = (string)$row['column_name'];
        if (!array_key_exists($name, $this->columns)) {
            return;
        }
        $this->hasForeignKeys = true;
        $this->columns[$name]['FOREIGN_KEY'] = [
            'column_name' => $name,
            'constraint_name' => (string)$row['constraint_name'],
            'referenced_table_name' => (string)$row['referenced_table_name'],
            'referenced_column_name' => (string)$row['referenced_column_name'],
            'delete_rule' => isset($row['delete_rule']) ? (string)$row['delete_rule'] : null,
            'update_rule' => isset($row['update_rule']) ? (string)$row['update_rule'] : null,
        ];
    }

    /**
     * Store a referencing-table row with both key spellings
     * @param array<string, mixed> $row
     */
    protected function storeReferencingTable(array $row): void
    {
        $row = array_change_key_case($row, CASE_LOWER);
        $entry = [
            'table_name' => (string)$row['table_name'],
            'column_name' => (string)($row['column_name'] ?? ''),
            'constraint_name' => (string)($row['constraint_name'] ?? ''),
        ];
        $this->referencingTables[] = $entry + array_change_key_case($entry, CASE_UPPER);
    }

    /**
     * The table's unique indexes on plain columns (primary key, partial, prefix and expression
     * indexes left out), single-column ones included; ClassGenerator writes the ones over
     * several columns as Table::addUniqueKey()
     * @return array<string, string[]> index name => columns in index order
     */
    public function getUniqueKeys(): array
    {
        return $this->uniqueKeys;
    }

    /**
     * @param array<string, string[]> $uniqueKeys index name => columns
     */
    public function setUniqueKeys(array $uniqueKeys): static
    {
        $this->uniqueKeys = $uniqueKeys;
        return $this;
    }

    /**
     * Collect index rows (index_name, column_name; null column = an expression or prefix part,
     * which drops the whole index) in index order into $uniqueKeys
     * @param array<int, array<string, mixed>> $rows
     */
    protected function storeUniqueKeyRows(array $rows): void
    {
        $keys = $dropped = [];
        foreach ($rows as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            $index = (string)$row['index_name'];
            $column = $row['column_name'] ?? null;
            if ($column === null || $column === '' || !array_key_exists((string)$column, $this->columns)) {
                $dropped[$index] = true;
                continue;
            }
            $keys[$index][] = (string)$column;
        }
        foreach ($keys as $index => $columns) {
            if (!isset($dropped[$index])) {
                $this->uniqueKeys[(string)$index] = $columns;
            }
        }
    }

    /**
     * Load the unique indexes (engine query); the default reads none, so an inspector written
     * before this method existed keeps working
     */
    protected function populateUniqueKeys(): void
    {
    }

    /**
     * The table's comment; {"linker": true} marks a linker table (ColumnComment::linker())
     */
    public function getTableComment(): ?string
    {
        return $this->tableComment;
    }

    public function setTableComment(?string $comment): static
    {
        $this->tableComment = $comment === null || trim($comment) === '' ? null : $comment;
        return $this;
    }

    /**
     * Load the table comment (engine query); the default reads none
     */
    protected function populateTableComment(): void
    {
    }

    abstract protected function populateColumnsData(): void;

    abstract protected function populateForeignKeyData(): void;

    /**
     * Load the tables (and columns) whose foreign keys point at this table's primary key
     */
    abstract public function populateReferencingTables(): void;
}
