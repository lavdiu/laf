<?php

namespace Laf\Generator;

use Laf\Util\Settings;

/**
 * Class TableInspectorFactory
 * @package Laf\Generator
 *
 * Manages the creation and caching of TableInspector instances to prevent
 * redundant database schema queries during a single request.
 */
class TableInspectorFactory
{
    /**
     * @var TableInspectorInterface[]
     */
    private static array $inspectors = [];

    /**
     * Get a TableInspector for a given table.
     * If it has been created before, return the cached instance.
     * Otherwise, create a new one, inspect the table, cache it, and return it.
     *
     * @param string $tableName
     * @return TableInspectorInterface
     */
    public static function getInspector(string $tableName): TableInspectorInterface
    {
        if (!isset(self::$inspectors[$tableName])) {
            $inspector = (Settings::get('database.engine') == 'postgres')
                ? new PostgresTableInspector($tableName)
                : new TableInspector($tableName);

            $inspector->inspect();
            self::$inspectors[$tableName] = $inspector;
        }
        return self::$inspectors[$tableName];
    }
}