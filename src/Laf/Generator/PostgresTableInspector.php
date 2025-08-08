<?php

namespace Laf\Generator;

use Laf\Database\Db;
use Laf\Exception\MissingConfigParamException;
use Laf\Util\Settings;

class PostgresTableInspector
{
    /**
     * @var string
     */
    private $table = null;

    /**
     * @var array[]
     */
    private $columns = [];

    /**
     * @var null|string
     */
    private $primaryColumnName = null;

    /**
     * @var bool
     */
    private $hasForeignKeys = false;

    /**
     * @var array
     */
    private $referencingTables = [];

    public function __construct(string $table)
    {
        $this->table = $table;
        $this->inspect();
    }

    /**
     * @return bool
     */
    public function hasForeignKeys(): bool
    {
        return $this->hasForeignKeys;
    }

    /**
     * @return bool
     */
    public function hasReferencingTables(): bool
    {
        return count($this->referencingTables) > 0;
    }

    /**
     * @return array
     */
    public function getReferencingTables(): array
    {
        return $this->referencingTables;
    }

    /**
     * @return null|string
     */
    public function getPrimaryColumnName()
    {
        return $this->primaryColumnName;
    }

    /**
     * @param null|string $primaryColumnName
     * @return PostgresTableInspector
     */
    public function setPrimaryColumnName($primaryColumnName)
    {
        $this->primaryColumnName = $primaryColumnName;
        return $this;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @param string $table
     * @return PostgresTableInspector
     */
    public function setTable(string $table): PostgresTableInspector
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @return array[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @param array[] $columns
     * @return PostgresTableInspector
     */
    public function setColumns(array $columns): PostgresTableInspector
    {
        $this->columns = $columns;
        return $this;
    }

    public function inspect(): void
    {
        $this->populateColumnsData();
        $this->populateForeignKeyData();
        $this->populateReferencingTables();
    }

    /**
     * Populate column metadata from information_schema and mark the primary key column.
     * Normalizes keys to match MySQL inspector (upper-case keys and COLUMN_KEY for PRI).
     * @throws MissingConfigParamException
     */
    private function populateColumnsData(): void
    {
        $db = Db::getInstance();

        // Prefer current_schema() to avoid relying on MySQL-specific settings mapping.
        $sql = "
            SELECT c.*
            FROM information_schema.columns c
            WHERE c.table_schema = current_schema()
              AND c.table_name = :table
            ORDER BY c.ordinal_position
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([':table' => $this->getTable()]);

        $this->columns = [];
        while ($col = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            // Normalize keys to upper-case to mirror MySQL inspector usage
            $normalized = [];
            foreach ($col as $k => $v) {
                $normalized[strtoupper($k)] = $v;
            }
            $name = $normalized['COLUMN_NAME'];
            $this->columns[$name] = $normalized;
        }

        // Determine primary key column(s) and set COLUMN_KEY = 'PRI' accordingly
        $pkSql = "
            SELECT a.attname AS column_name
            FROM pg_index i
            JOIN pg_class t ON t.oid = i.indrelid
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(i.indkey)
            JOIN pg_namespace n ON n.oid = t.relnamespace
            WHERE i.indisprimary = true
              AND n.nspname = current_schema()
              AND t.relname = :table
            ORDER BY a.attnum
        ";
        $pkStmt = $db->prepare($pkSql);
        $pkStmt->execute([':table' => $this->getTable()]);
        $firstPk = null;
        while ($r = $pkStmt->fetch(\PDO::FETCH_ASSOC)) {
            $colName = $r['column_name'];
            if (isset($this->columns[$colName])) {
                $this->columns[$colName]['COLUMN_KEY'] = 'PRI';
            }
            if ($firstPk === null) {
                $firstPk = $colName;
            }
        }
        if ($firstPk !== null) {
            $this->setPrimaryColumnName($firstPk);
        }
    }

    /**
     * Populate foreign key data using information_schema.
     * Adds a FOREIGN_KEY structure similar to MySQL inspector.
     * @throws MissingConfigParamException
     */
    private function populateForeignKeyData(): void
    {
        $db = Db::getInstance();

        $sql = "
            SELECT
                kcu.column_name,
                ccu.table_name AS referenced_table_name,
                ccu.column_name AS referenced_column_name,
                tc.constraint_name
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.key_column_usage AS kcu
              ON tc.constraint_name = kcu.constraint_name
             AND tc.constraint_schema = kcu.constraint_schema
            JOIN information_schema.constraint_column_usage AS ccu
              ON ccu.constraint_name = tc.constraint_name
             AND ccu.constraint_schema = tc.constraint_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_schema = current_schema()
              AND tc.table_name = :table
            ORDER BY kcu.ordinal_position
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([':table' => $this->getTable()]);

        while ($r = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $this->hasForeignKeys = true;
            $col = $r->column_name;
            if (!isset($this->columns[$col])) {
                // Ensure column exists in metadata; create minimal entry if not
                $this->columns[$col] = ['COLUMN_NAME' => $col];
            }
            $this->columns[$col]['FOREIGN_KEY'] = [
                'column_name' => $r->column_name,
                'constraint_name' => $r->constraint_name,
                'referenced_table_name' => $r->referenced_table_name,
                'referenced_column_name' => $r->referenced_column_name,
            ];
        }
    }

    /**
     * Choose a display column similar to the MySQL inspector.
     * @return string
     */
    public function getDisplayColumnName(): string
    {
        if (array_key_exists('label', $this->getColumns())) {
            return 'label';
        }
        if (array_key_exists('name', $this->getColumns())) {
            return 'name';
        }

        $cols = $this->getColumns();
        $first = array_shift($cols); // discard
        $second = array_shift($cols);
        return $second['COLUMN_NAME'] ?? $this->primaryColumnName ?? 'id';
    }

    /**
     * Populate list of tables referencing this table's primary key.
     * @throws MissingConfigParamException
     */
    public function populateReferencingTables(): void
    {
        $db = Db::getInstance();

        $sql = "
            SELECT DISTINCT tc.table_name AS table_name
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.key_column_usage AS kcu
              ON tc.constraint_name = kcu.constraint_name
             AND tc.constraint_schema = kcu.constraint_schema
            JOIN information_schema.constraint_column_usage AS ccu
              ON ccu.constraint_name = tc.constraint_name
             AND ccu.constraint_schema = tc.constraint_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_schema = current_schema()
              AND ccu.table_schema = current_schema()
              AND ccu.table_name = :ref_table
              AND ccu.column_name = :ref_column
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':ref_table' => $this->getTable(),
            ':ref_column' => $this->primaryColumnName,
        ]);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        // Normalize to match Db::getAllAssoc(MySQL) structure => array of associative rows with TABLE_NAME key
        $this->referencingTables = array_map(function ($r) {
            return ['TABLE_NAME' => $r['table_name']];
        }, $rows);
    }
}
