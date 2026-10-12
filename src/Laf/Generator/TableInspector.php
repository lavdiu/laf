<?php

namespace Laf\Generator;

use Laf\Database\Db;

/**
 * MySQL / MariaDB table inspector: information_schema of the connected database
 */
class TableInspector extends AbstractTableInspector
{
    protected function populateColumnsData(): void
    {
        $sql = "
        SELECT *
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
            AND table_name = :t
        ORDER BY ordinal_position
        ";
        foreach (Db::getAllAssoc($sql, [':t' => $this->getTable()]) as $column) {
            $this->storeColumn($column);
        }
        $this->detectMariaDbJsonColumns();
    }

    /**
     * MariaDB has no native JSON type: `JSON` is an alias for LONGTEXT plus a
     * CHECK (json_valid(col)) constraint, and information_schema reports the
     * column as longtext. Read the check constraints and re-label such columns
     * as DATA_TYPE 'json' so they get TypeJson. Harmless on MySQL 8 (native
     * json) and silently skipped on servers without CHECK_CONSTRAINTS.
     */
    private function detectMariaDbJsonColumns(): void
    {
        $sql = "
        SELECT cc.CHECK_CLAUSE
        FROM information_schema.CHECK_CONSTRAINTS cc
        JOIN information_schema.TABLE_CONSTRAINTS tc
            ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
        WHERE tc.TABLE_SCHEMA = DATABASE()
            AND tc.TABLE_NAME = :t
            AND cc.CHECK_CLAUSE LIKE '%json_valid(%'
        ";
        try {
            foreach (Db::getAllAssoc($sql, [':t' => $this->getTable()]) as $row) {
                $clause = (string)reset($row);
                if (preg_match('/json_valid\s*\(\s*`?([A-Za-z0-9_]+)`?\s*\)/i', $clause, $m) && isset($this->columns[$m[1]])) {
                    $this->columns[$m[1]]['DATA_TYPE'] = 'json';
                }
            }
        } catch (\Throwable $ex) {
            // no CHECK_CONSTRAINTS view (MySQL < 8.0.16, MariaDB < 10.2): nothing to detect
        }
    }

    protected function populateForeignKeyData(): void
    {
        $sql = "
        SELECT
            kcu.COLUMN_NAME AS column_name,
            kcu.REFERENCED_TABLE_NAME AS referenced_table_name,
            kcu.REFERENCED_COLUMN_NAME AS referenced_column_name,
            kcu.CONSTRAINT_NAME AS constraint_name,
            rc.DELETE_RULE AS delete_rule,
            rc.UPDATE_RULE AS update_rule
        FROM information_schema.KEY_COLUMN_USAGE kcu
        LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
            ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
        WHERE kcu.TABLE_SCHEMA = DATABASE()
            AND kcu.TABLE_NAME = :t
            AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION
        ";
        foreach (Db::getAllAssoc($sql, [':t' => $this->getTable()]) as $row) {
            $this->storeForeignKey($row);
        }
    }

    public function populateReferencingTables(): void
    {
        $this->referencingTables = [];
        $primary = $this->getPrimaryColumnName();
        if ($primary === null) {
            return;
        }
        $sql = "
        SELECT
            TABLE_NAME AS table_name,
            COLUMN_NAME AS column_name,
            CONSTRAINT_NAME AS constraint_name
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
            AND REFERENCED_TABLE_SCHEMA = DATABASE()
            AND REFERENCED_TABLE_NAME = :t
            AND REFERENCED_COLUMN_NAME = :pk
        ORDER BY TABLE_NAME, COLUMN_NAME
        ";
        foreach (Db::getAllAssoc($sql, [':t' => $this->getTable(), ':pk' => $primary]) as $row) {
            $this->storeReferencingTable($row);
        }
    }
}
