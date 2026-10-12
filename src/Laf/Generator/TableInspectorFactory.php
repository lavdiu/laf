<?php

declare(strict_types=1);

namespace Laf\Generator;

use Laf\Database\Db;
use Laf\Database\Identifier;

/**
 * The one place that picks the inspector for the database engine
 */
final class TableInspectorFactory
{
    /**
     * @param string|null $engine mysql|mariadb|postgres|postgresql|pgsql; null = the registered
     *                            default connection's engine, else Settings 'database.engine'
     */
    public static function create(string $table, ?string $engine = null): TableInspectorInterface
    {
        $engine = Db::normalizeEngine($engine ?? Identifier::engine());
        if (Identifier::isPostgres($engine)) {
            return new PostgresTableInspector($table);
        }
        return new MysqlTableInspector($table);
    }
}
