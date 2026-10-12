<?php

namespace Laf\Database\Field;

/**
 * Maps a database column to a FieldType.
 *
 * One table drives both getClass() (runtime instance) and getClassLiteral()
 * (PHP source emitted by the generator), so the two can never disagree.
 *
 * Pass the whole information_schema row as $column when you have it: the
 * factory then reads COLUMN_TYPE (MySQL: "tinyint(1)", "enum('a','b')",
 * "int unsigned"), NUMERIC_PRECISION / NUMERIC_SCALE, and ENUM_OPTIONS
 * (PostgreSQL inspector) to pick sizes, scales and option lists.
 */
class FieldTypeFactory
{
    /** DB type name (lower case) => short type name */
    private const MAP = [
        // whole numbers
        'int' => 'integer', 'integer' => 'integer', 'int2' => 'integer', 'int4' => 'integer', 'int8' => 'integer',
        'smallint' => 'integer', 'mediumint' => 'integer', 'bigint' => 'integer',
        'serial' => 'integer', 'smallserial' => 'integer', 'bigserial' => 'integer',
        'year' => 'integer',
        'tinyint' => 'tinyint',   // width decides: tinyint(1) is bool
        'bit' => 'bit',           // length decides: bit(1) is bool
        // booleans
        'bool' => 'bool', 'boolean' => 'bool',
        // exact numbers
        'decimal' => 'decimal', 'numeric' => 'decimal', 'money' => 'decimal', 'dec' => 'decimal', 'fixed' => 'decimal',
        // approximate numbers
        'float' => 'float', 'double' => 'float', 'real' => 'float', 'double precision' => 'float',
        'float4' => 'float', 'float8' => 'float',
        // strings
        'varchar' => 'varchar', 'character varying' => 'varchar', 'citext' => 'varchar',
        'interval' => 'varchar', 'inet' => 'varchar', 'cidr' => 'varchar', 'macaddr' => 'varchar', 'macaddr8' => 'varchar',
        'char' => 'char', 'character' => 'char', 'bpchar' => 'char',
        'text' => 'text', 'tinytext' => 'text', 'mediumtext' => 'text', 'longtext' => 'text',
        'name' => 'text', 'xml' => 'text', 'array' => 'text', 'tsvector' => 'text',
        // temporal
        'date' => 'date',
        'datetime' => 'datetime', 'timestamp' => 'datetime', 'timestamp without time zone' => 'datetime',
        'timestamp with time zone' => 'datetimetz', 'timestamptz' => 'datetimetz',
        'time' => 'time', 'time without time zone' => 'time', 'time with time zone' => 'time', 'timetz' => 'time',
        // structured / binary
        'json' => 'json', 'jsonb' => 'json',
        'blob' => 'blob', 'tinyblob' => 'blob', 'mediumblob' => 'blob', 'longblob' => 'blob',
        'binary' => 'blob', 'varbinary' => 'blob', 'bytea' => 'blob',
        // choice
        'enum' => 'enum', 'set' => 'set',
        'user-defined' => 'enum',   // PostgreSQL enum types, options come from ENUM_OPTIONS
        // identifiers
        'uuid' => 'uuid',
    ];

    private const CLASSES = [
        'integer' => TypeInteger::class,
        'decimal' => TypeDecimal::class,
        'float' => TypeFloat::class,
        'bool' => TypeBool::class,
        'varchar' => TypeVarchar::class,
        'char' => TypeChar::class,
        'text' => TypeText::class,
        'date' => TypeDate::class,
        'datetime' => TypeDateTime::class,
        'datetimetz' => TypeDateTime::class,
        'time' => TypeTime::class,
        'json' => TypeJson::class,
        'blob' => TypeBlob::class,
        'enum' => TypeEnum::class,
        'set' => TypeEnum::class,
        'uuid' => TypeUuid::class,
    ];

    /**
     * Decide class and constructor arguments for a column.
     *
     * @param string $dataType information_schema DATA_TYPE (any case)
     * @param array $column optional full column row (COLUMN_TYPE, NUMERIC_PRECISION, NUMERIC_SCALE, ENUM_OPTIONS, …)
     * @return array{class: class-string<FieldType>, args: array}
     */
    public static function resolve(string $dataType, array $column = []): array
    {
        $column = array_change_key_case($column, CASE_UPPER);
        $dataType = strtolower(trim($dataType));
        $columnType = strtolower(trim((string)($column['COLUMN_TYPE'] ?? '')));
        $short = self::MAP[$dataType] ?? null;

        if ($short === null) {
            // e.g. "int unsigned", "numeric(12,2)", "timestamp(6)" when only COLUMN_TYPE was passed
            $base = strtolower(trim(preg_replace('/[\s(].*$/', '', $dataType) ?? ''));
            $short = self::MAP[$base] ?? 'text';
            if ($columnType === '') {
                $columnType = $dataType;
            }
        }

        switch ($short) {
            case 'tinyint':
                $width = self::displayWidth($columnType);
                if ($width === 1 || $width === null) {
                    return ['class' => TypeBool::class, 'args' => []];
                }
                return ['class' => TypeInteger::class, 'args' => self::integerArgs('tinyint', $columnType)];

            case 'bit':
                $width = self::displayWidth($columnType);
                if ($width === null || $width === 1) {
                    return ['class' => TypeBool::class, 'args' => []];
                }
                return ['class' => TypeInteger::class, 'args' => self::integerArgs('bigint', $columnType)];

            case 'integer':
                $size = self::integerSize($dataType);
                return ['class' => TypeInteger::class, 'args' => self::integerArgs($size, $columnType)];

            case 'decimal':
                $precision = self::intOrNull($column['NUMERIC_PRECISION'] ?? null);
                $scale = self::intOrNull($column['NUMERIC_SCALE'] ?? null);
                if (($precision === null || $scale === null) && preg_match('/\((\d+)\s*,\s*(\d+)\)/', $columnType, $m)) {
                    $precision = (int)$m[1];
                    $scale = (int)$m[2];
                }
                if ($dataType === 'money' && $scale === null) {
                    $scale = 2;
                }
                return ['class' => TypeDecimal::class, 'args' => [$precision, $scale]];

            case 'datetimetz':
                return ['class' => TypeDateTime::class, 'args' => [true]];

            case 'enum':
            case 'set':
                $options = $column['ENUM_OPTIONS'] ?? self::enumOptions($columnType);
                if (!is_array($options)) {
                    $options = [];
                }
                if ($short === 'enum' && $dataType === 'user-defined' && count($options) === 0) {
                    // unknown user-defined type (composite, domain, …): fall back to text
                    return ['class' => TypeText::class, 'args' => []];
                }
                return ['class' => TypeEnum::class, 'args' => [array_values($options), $short === 'set']];

            default:
                return ['class' => self::CLASSES[$short], 'args' => []];
        }
    }

    /**
     * @param string $type DATA_TYPE
     * @param array $column optional full column row
     */
    public static function getClass(string $type, array $column = []): FieldType
    {
        $resolved = self::resolve($type, $column);
        return new $resolved['class'](...$resolved['args']);
    }

    /**
     * PHP source for the generator, e.g. "new Database\Field\TypeDecimal(12, 2)".
     * Relies on the generated file importing "use Laf\Database;".
     *
     * @param string $type DATA_TYPE
     * @param array $column optional full column row
     */
    public static function getClassLiteral(string $type, array $column = []): string
    {
        $resolved = self::resolve($type, $column);
        $shortClass = substr($resolved['class'], strrpos($resolved['class'], '\\') + 1);
        $args = [];
        foreach ($resolved['args'] as $arg) {
            $args[] = self::exportArg($arg);
        }
        // drop trailing default arguments so the common case stays "new TypeX()"
        while (count($args) > 0 && in_array(strtolower(end($args)), ['null', 'false'], true)) {
            array_pop($args);
        }
        return 'new Database\\Field\\' . $shortClass . '(' . implode(', ', $args) . ')';
    }

    /* ───────────── helpers ───────────── */

    private static function integerSize(string $dataType): string
    {
        return match ($dataType) {
            'smallint', 'int2', 'smallserial' => 'smallint',
            'mediumint' => 'mediumint',
            'bigint', 'int8', 'bigserial' => 'bigint',
            'year' => 'year',
            default => 'int',
        };
    }

    /** @return array{0: string, 1: bool} */
    private static function integerArgs(string $size, string $columnType): array
    {
        $unsigned = str_contains($columnType, 'unsigned');
        return [$size, $unsigned];
    }

    private static function displayWidth(string $columnType): ?int
    {
        if (preg_match('/^\w+\((\d+)\)/', $columnType, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    /** "enum('a','b''c')" → ['a', "b'c"] */
    private static function enumOptions(string $columnType): array
    {
        if (!preg_match('/^(enum|set)\s*\((.*)\)\s*$/is', $columnType, $m)) {
            return [];
        }
        preg_match_all("/'((?:[^']|'')*)'/", $m[2], $matches);
        return array_map(fn($o) => str_replace("''", "'", $o), $matches[1]);
    }

    private static function intOrNull(mixed $v): ?int
    {
        return ($v === null || $v === '') ? null : (int)$v;
    }

    private static function exportArg(mixed $arg): string
    {
        if (is_array($arg)) {
            return '[' . implode(', ', array_map(fn($a) => self::exportArg($a), $arg)) . ']';
        }
        return var_export($arg, true);
    }
}
