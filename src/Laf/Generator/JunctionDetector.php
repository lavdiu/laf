<?php

declare(strict_types=1);

namespace Laf\Generator;

/**
 * Recognises a linker (junction) table of a many-to-many relation from its inspected columns:
 * exactly two foreign keys to two different tables and at most two other columns besides the
 * primary key and the row metadata. person_group(id, person_id → person, group_id → group) is
 * one; a child table with its own data (invoice_line) is not.
 */
final class JunctionDetector
{
    /** Columns that never count as data of a linker table */
    public const METADATA_COLUMNS = ['id', 'created_on', 'created_by', 'updated_on', 'updated_by', 'is_deleted', 'deleted', 'record_status_id'];

    /** Most data columns a linker table may carry besides its keys */
    public const MAX_EXTRA_COLUMNS = 2;

    /**
     * The relation the linker table gives $currentTable, null when the table is no linker of it
     * @param TableInspectorInterface $junction the inspected candidate table
     * @param string $currentTable the table on whose side the relation is read
     * @param string|null $viaColumn the linker column that must point at $currentTable
     * @return array{junction_table: string, other_table: string, fk_to_current: string, fk_to_other: string,
     *               other_column: array<string, mixed>, insertable: bool}|null insertable = every data column
     *               may be left out of an INSERT (nullable, a default or auto-increment)
     */
    public static function detect(TableInspectorInterface $junction, string $currentTable, ?string $viaColumn = null): ?array
    {
        $foreignKeys = [];
        $extraColumns = 0;
        $insertable = true;
        foreach ($junction->getColumns() as $column) {
            if (in_array($column['COLUMN_NAME'], self::METADATA_COLUMNS, true)) {
                // row metadata, also when it is a foreign key (record_status_id → record_status,
                // created_by → person): never one of the two linker keys, never data
                if (!self::canBeOmitted($column)) {
                    $insertable = false;
                }
                continue;
            }
            if (self::isForeignKey($column)) {
                $foreignKeys[] = $column;
                continue;
            }
            if (!self::canBeOmitted($column)) {
                $insertable = false;
            }
            if (in_array($column['COLUMN_NAME'], self::METADATA_COLUMNS, true) || ($column['COLUMN_KEY'] ?? '') === 'PRI') {
                continue;
            }
            $extraColumns++;
        }
        if (count($foreignKeys) !== 2 || $extraColumns > self::MAX_EXTRA_COLUMNS) {
            return null;
        }

        $fkToCurrent = null;
        $fkToOther = null;
        foreach ($foreignKeys as $fk) {
            $pointsAtCurrent = $fk['FOREIGN_KEY']['referenced_table_name'] === $currentTable
                && ($viaColumn === null || $fk['COLUMN_NAME'] === $viaColumn);
            if ($pointsAtCurrent && $fkToCurrent === null) {
                $fkToCurrent = $fk;
            } else {
                $fkToOther = $fk;
            }
        }
        if ($fkToCurrent === null || $fkToOther === null) {
            return null;
        }
        if ($fkToOther['FOREIGN_KEY']['referenced_table_name'] === $currentTable) {
            // both keys point here (from/to on the same parent): a plain child table, one tab per key
            return null;
        }
        return [
            'junction_table' => $junction->getTable(),
            'other_table' => (string)$fkToOther['FOREIGN_KEY']['referenced_table_name'],
            'fk_to_current' => (string)$fkToCurrent['COLUMN_NAME'],
            'fk_to_other' => (string)$fkToOther['COLUMN_NAME'],
            'other_column' => $fkToOther,
            'insertable' => $insertable,
        ];
    }

    /**
     * @param array<string, mixed> $column
     */
    public static function isForeignKey(array $column): bool
    {
        return !empty($column['FOREIGN_KEY']) && is_array($column['FOREIGN_KEY'])
            && !empty($column['FOREIGN_KEY']['referenced_table_name']);
    }

    /**
     * Whether an INSERT may leave the column out: nullable, with a default, or generated
     * @param array<string, mixed> $column
     */
    private static function canBeOmitted(array $column): bool
    {
        if (($column['IS_NULLABLE'] ?? 'YES') !== 'NO') {
            return true;
        }
        if (array_key_exists('COLUMN_DEFAULT', $column) && $column['COLUMN_DEFAULT'] !== null) {
            return true;
        }
        $extra = strtolower((string)($column['EXTRA'] ?? ''));
        return str_contains($extra, 'auto_increment') || str_contains($extra, 'identity') || str_contains($extra, 'generated');
    }
}
