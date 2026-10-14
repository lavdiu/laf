<?php

namespace Laf\Generator;

use Laf\Database\Db;

/**
 * PostgreSQL table inspector for the current schema (search_path): the rows are
 * shaped like MySQL's information_schema so the generators need no engine branch.
 * COLUMN_KEY is 'PRI' for primary key columns and 'UNI' for a single-column
 * unique index (a column of a composite unique index is not 'UNI', as on MySQL);
 * EXTRA is 'auto_increment' for serial and identity columns; COLUMN_COMMENT is
 * the column's comment (COMMENT ON COLUMN).
 */
class PostgresTableInspector extends AbstractTableInspector
{
    protected function populateColumnsData(): void
    {
        $sql = "
        SELECT
            c.*,
            pg_catalog.col_description(format('%I.%I', c.table_schema, c.table_name)::regclass, c.ordinal_position) AS column_comment,
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM pg_catalog.pg_index i
                    JOIN pg_catalog.pg_class cl ON cl.oid = i.indrelid
                    JOIN pg_catalog.pg_namespace n ON n.oid = cl.relnamespace
                    JOIN pg_catalog.pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY (i.indkey)
                    WHERE i.indisprimary
                        AND n.nspname = c.table_schema
                        AND cl.relname = c.table_name
                        AND a.attname = c.column_name
                ) THEN 'PRI'
                WHEN EXISTS (
                    SELECT 1
                    FROM pg_catalog.pg_index i
                    JOIN pg_catalog.pg_class cl ON cl.oid = i.indrelid
                    JOIN pg_catalog.pg_namespace n ON n.oid = cl.relnamespace
                    JOIN pg_catalog.pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = i.indkey[0]
                    WHERE i.indisunique
                        AND NOT i.indisprimary
                        AND i.indnkeyatts = 1
                        AND i.indpred IS NULL
                        AND n.nspname = c.table_schema
                        AND cl.relname = c.table_name
                        AND a.attname = c.column_name
                ) THEN 'UNI'
                ELSE ''
            END AS column_key,
            CASE
                WHEN c.is_identity = 'YES' OR c.column_default LIKE 'nextval(%' THEN 'auto_increment'
                ELSE NULL
            END AS extra
        FROM information_schema.columns AS c
        WHERE c.table_schema = current_schema()
            AND c.table_name = :t
        ORDER BY c.ordinal_position
        ";
        foreach (Db::getAllAssoc($sql, [':t' => $this->getTable()]) as $column) {
            $column = array_change_key_case($column, CASE_UPPER);
            if (($column['DATA_TYPE'] ?? '') === 'USER-DEFINED' && !empty($column['UDT_NAME'])) {
                $column['ENUM_OPTIONS'] = $this->fetchEnumLabels((string)$column['UDT_NAME']);
            }
            $this->storeColumn($column);
        }
    }

    /**
     * Labels of a PostgreSQL enum type in declaration order.
     * Returns an empty array for user-defined types that are not enums (domains, composites).
     * @return string[]
     */
    private function fetchEnumLabels(string $udtName): array
    {
        $sql = "
        SELECT e.enumlabel
        FROM pg_catalog.pg_enum e
        JOIN pg_catalog.pg_type t ON e.enumtypid = t.oid
        WHERE t.typname = :udt
        ORDER BY e.enumsortorder
        ";
        $labels = [];
        foreach (Db::getAllAssoc($sql, [':udt' => $udtName]) as $row) {
            $labels[] = (string)reset($row);
        }
        return $labels;
    }

    protected function populateTableComment(): void
    {
        $sql = "
        SELECT pg_catalog.obj_description(cl.oid, 'pg_class')
        FROM pg_catalog.pg_class cl
        JOIN pg_catalog.pg_namespace n ON n.oid = cl.relnamespace
        WHERE n.nspname = current_schema()
            AND cl.relname = :t
        ";
        $comment = Db::getOne($sql, [':t' => $this->getTable()]);
        $this->setTableComment(is_string($comment) ? $comment : null);
    }

    protected function populateUniqueKeys(): void
    {
        // key columns only (INCLUDE columns are not part of the rule); attnum 0 = an expression,
        // which drops the index (column_name null); partial indexes (WHERE ...) are left out
        $sql = "
        SELECT
            ic.relname AS index_name,
            a.attname AS column_name
        FROM pg_catalog.pg_index i
        JOIN pg_catalog.pg_class cl ON cl.oid = i.indrelid
        JOIN pg_catalog.pg_namespace n ON n.oid = cl.relnamespace
        JOIN pg_catalog.pg_class ic ON ic.oid = i.indexrelid
        CROSS JOIN LATERAL unnest(i.indkey::int2[]) WITH ORDINALITY AS k(attnum, ord)
        LEFT JOIN pg_catalog.pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = k.attnum AND k.attnum > 0
        WHERE i.indisunique
            AND NOT i.indisprimary
            AND i.indpred IS NULL
            AND k.ord <= i.indnkeyatts
            AND n.nspname = current_schema()
            AND cl.relname = :t
        ORDER BY ic.relname, k.ord
        ";
        $this->storeUniqueKeyRows(Db::getAllAssoc($sql, [':t' => $this->getTable()]));
    }

    /**
     * Foreign keys from pg_constraint: one row per column, composite keys
     * matched position by position (information_schema would cross-join them)
     */
    protected function populateForeignKeyData(): void
    {
        $sql = "
        SELECT
            a.attname AS column_name,
            ref.relname AS referenced_table_name,
            ra.attname AS referenced_column_name,
            k.conname AS constraint_name,
            CASE k.confdeltype WHEN 'a' THEN 'NO ACTION' WHEN 'r' THEN 'RESTRICT' WHEN 'c' THEN 'CASCADE' WHEN 'n' THEN 'SET NULL' WHEN 'd' THEN 'SET DEFAULT' END AS delete_rule,
            CASE k.confupdtype WHEN 'a' THEN 'NO ACTION' WHEN 'r' THEN 'RESTRICT' WHEN 'c' THEN 'CASCADE' WHEN 'n' THEN 'SET NULL' WHEN 'd' THEN 'SET DEFAULT' END AS update_rule
        FROM pg_catalog.pg_constraint k
        JOIN pg_catalog.pg_class cl ON cl.oid = k.conrelid
        JOIN pg_catalog.pg_namespace n ON n.oid = cl.relnamespace
        JOIN pg_catalog.pg_class ref ON ref.oid = k.confrelid
        JOIN LATERAL unnest(k.conkey, k.confkey) WITH ORDINALITY AS cols(attnum, refattnum, ord) ON true
        JOIN pg_catalog.pg_attribute a ON a.attrelid = k.conrelid AND a.attnum = cols.attnum
        JOIN pg_catalog.pg_attribute ra ON ra.attrelid = k.confrelid AND ra.attnum = cols.refattnum
        WHERE k.contype = 'f'
            AND n.nspname = current_schema()
            AND cl.relname = :t
        ORDER BY k.conname, cols.ord
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
            cl.relname AS table_name,
            a.attname AS column_name,
            k.conname AS constraint_name
        FROM pg_catalog.pg_constraint k
        JOIN pg_catalog.pg_class cl ON cl.oid = k.conrelid
        JOIN pg_catalog.pg_namespace n ON n.oid = cl.relnamespace
        JOIN pg_catalog.pg_class ref ON ref.oid = k.confrelid
        JOIN pg_catalog.pg_namespace rn ON rn.oid = ref.relnamespace
        JOIN LATERAL unnest(k.conkey, k.confkey) WITH ORDINALITY AS cols(attnum, refattnum, ord) ON true
        JOIN pg_catalog.pg_attribute a ON a.attrelid = k.conrelid AND a.attnum = cols.attnum
        JOIN pg_catalog.pg_attribute ra ON ra.attrelid = k.confrelid AND ra.attnum = cols.refattnum
        WHERE k.contype = 'f'
            AND n.nspname = current_schema()
            AND rn.nspname = current_schema()
            AND ref.relname = :t
            AND ra.attname = :pk
        ORDER BY cl.relname, a.attname
        ";
        foreach (Db::getAllAssoc($sql, [':t' => $this->getTable(), ':pk' => $primary]) as $row) {
            $this->storeReferencingTable($row);
        }
    }
}
