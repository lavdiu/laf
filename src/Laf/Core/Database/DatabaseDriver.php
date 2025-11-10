<?php

declare(strict_types=1);

namespace Laf\Core\Database;

/**
 * Database Driver Enum
 * 
 * Supported database drivers
 */
enum DatabaseDriver: string
{
    case MYSQL = 'mysql';
    case POSTGRESQL = 'pgsql';
    case SQLITE = 'sqlite';
    case SQLSERVER = 'sqlsrv';

    /**
     * Get the DSN prefix for this driver
     *
     * @return string
     */
    public function getDsnPrefix(): string
    {
        return match($this) {
            self::MYSQL => 'mysql',
            self::POSTGRESQL => 'pgsql',
            self::SQLITE => 'sqlite',
            self::SQLSERVER => 'sqlsrv',
        };
    }

    /**
     * Get the default port for this driver
     *
     * @return int
     */
    public function getDefaultPort(): int
    {
        return match($this) {
            self::MYSQL => 3306,
            self::POSTGRESQL => 5432,
            self::SQLITE => 0,
            self::SQLSERVER => 1433,
        };
    }

    /**
     * Check if this driver supports schemas
     *
     * @return bool
     */
    public function supportsSchemas(): bool
    {
        return match($this) {
            self::POSTGRESQL, self::SQLSERVER => true,
            self::MYSQL, self::SQLITE => false,
        };
    }

    /**
     * Get the quote character for identifiers
     *
     * @return string
     */
    public function getIdentifierQuote(): string
    {
        return match($this) {
            self::MYSQL => '`',
            self::POSTGRESQL, self::SQLITE => '"',
            self::SQLSERVER => '[',
        };
    }

    /**
     * Get the closing quote character for identifiers
     *
     * @return string
     */
    public function getIdentifierQuoteEnd(): string
    {
        return match($this) {
            self::SQLSERVER => ']',
            default => $this->getIdentifierQuote(),
        };
    }

    /**
     * Check if driver supports returning clause
     *
     * @return bool
     */
    public function supportsReturning(): bool
    {
        return match($this) {
            self::POSTGRESQL => true,
            default => false,
        };
    }

    /**
     * Get the limit clause syntax
     *
     * @param int $limit
     * @param int $offset
     * @return string
     */
    public function getLimitClause(int $limit, int $offset = 0): string
    {
        return match($this) {
            self::MYSQL, self::POSTGRESQL, self::SQLITE => 
                $offset > 0 ? "LIMIT {$limit} OFFSET {$offset}" : "LIMIT {$limit}",
            self::SQLSERVER => 
                $offset > 0 ? "OFFSET {$offset} ROWS FETCH NEXT {$limit} ROWS ONLY" : "TOP {$limit}",
        };
    }

    /**
     * Check if driver supports JSON operations
     *
     * @return bool
     */
    public function supportsJson(): bool
    {
        return match($this) {
            self::MYSQL, self::POSTGRESQL => true,
            default => false,
        };
    }

    /**
     * Get the current timestamp function
     *
     * @return string
     */
    public function getCurrentTimestampFunction(): string
    {
        return match($this) {
            self::MYSQL => 'NOW()',
            self::POSTGRESQL => 'CURRENT_TIMESTAMP',
            self::SQLITE => "datetime('now')",
            self::SQLSERVER => 'GETDATE()',
        };
    }
}
