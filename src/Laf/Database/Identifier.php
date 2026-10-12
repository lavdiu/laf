<?php

namespace Laf\Database;

use Laf\Util\Settings;

/**
 * Engine-aware SQL identifier quoting, shared by BaseObject, PrimaryKey,
 * ForeignKey and the FK-backed inputs so there is exactly one quoting rule.
 *
 * MySQL/MariaDB use backticks, PostgreSQL uses double quotes. Names are
 * whitelisted to [A-Za-z0-9_] first, so a quoted identifier can never carry
 * injected characters whatever its source.
 */
final class Identifier
{
    public const MYSQL = 'mysql';
    public const POSTGRES = 'postgres';

    /**
     * Normalised engine name: the registered default connection's engine when
     * there is one (never connects), else Settings 'database.engine' ('mysql'
     * when unset or unknown).
     */
    public static function engine(): string
    {
        if (Db::hasInstance()) {
            return Db::getInstance()->getEngine() === self::POSTGRES ? self::POSTGRES : self::MYSQL;
        }
        $engine = '';
        try {
            $engine = strtolower(trim((string)Settings::get('database.engine')));
        } catch (\Throwable $ex) {
        }
        return in_array($engine, ['postgres', 'postgresql', 'pgsql'], true) ? self::POSTGRES : self::MYSQL;
    }

    public static function isMysql(?string $engine = null): bool
    {
        return ($engine ?? self::engine()) === self::MYSQL;
    }

    public static function isPostgres(?string $engine = null): bool
    {
        return ($engine ?? self::engine()) === self::POSTGRES;
    }

    /** Strip anything that is not a plain identifier character. */
    public static function sanitize(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '', $name) ?? '';
    }

    /** `name` on MySQL, "name" on PostgreSQL. */
    public static function quote(string $name, ?string $engine = null): string
    {
        $name = self::sanitize($name);
        return self::isPostgres($engine) ? '"' . $name . '"' : '`' . $name . '`';
    }

    /** schema-less table.column reference, both parts quoted. */
    public static function quoteQualified(string $table, string $column, ?string $engine = null): string
    {
        return self::quote($table, $engine) . '.' . self::quote($column, $engine);
    }
}
