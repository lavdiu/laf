<?php

declare(strict_types=1);

namespace Laf\Core\Database;

use PDO;
use PDOStatement;

/**
 * Database Connection Interface
 * 
 * Abstraction over PDO for database operations with dependency injection
 */
interface ConnectionInterface
{
    /**
     * Get the underlying PDO instance
     *
     * @return PDO
     */
    public function getPdo(): PDO;

    /**
     * Execute a query and return the statement
     *
     * @param string $query
     * @param array<mixed> $bindings
     * @return PDOStatement
     */
    public function query(string $query, array $bindings = []): PDOStatement;

    /**
     * Execute a statement and return affected rows
     *
     * @param string $query
     * @param array<mixed> $bindings
     * @return int
     */
    public function execute(string $query, array $bindings = []): int;

    /**
     * Fetch a single row as associative array
     *
     * @param string $query
     * @param array<mixed> $bindings
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $query, array $bindings = []): ?array;

    /**
     * Fetch all rows as associative arrays
     *
     * @param string $query
     * @param array<mixed> $bindings
     * @return array<array<string, mixed>>
     */
    public function fetchAll(string $query, array $bindings = []): array;

    /**
     * Fetch a single column value
     *
     * @param string $query
     * @param array<mixed> $bindings
     * @return mixed
     */
    public function fetchColumn(string $query, array $bindings = []): mixed;

    /**
     * Fetch a single row as an object
     *
     * @template T
     * @param string $query
     * @param array<mixed> $bindings
     * @param class-string<T>|null $class
     * @return T|object|null
     */
    public function fetchObject(string $query, array $bindings = [], ?string $class = null): ?object;

    /**
     * Fetch all rows as objects
     *
     * @template T
     * @param string $query
     * @param array<mixed> $bindings
     * @param class-string<T>|null $class
     * @return array<T|object>
     */
    public function fetchAllObjects(string $query, array $bindings = [], ?string $class = null): array;

    /**
     * Begin a transaction
     *
     * @return bool
     */
    public function beginTransaction(): bool;

    /**
     * Commit a transaction
     *
     * @return bool
     */
    public function commit(): bool;

    /**
     * Rollback a transaction
     *
     * @return bool
     */
    public function rollback(): bool;

    /**
     * Check if in a transaction
     *
     * @return bool
     */
    public function inTransaction(): bool;

    /**
     * Get the last inserted ID
     *
     * @param string|null $name
     * @return string
     */
    public function lastInsertId(?string $name = null): string;

    /**
     * Prepare a statement
     *
     * @param string $query
     * @return PDOStatement
     */
    public function prepare(string $query): PDOStatement;

    /**
     * Get the database driver name
     *
     * @return DatabaseDriver
     */
    public function getDriver(): DatabaseDriver;

    /**
     * Get the database name
     *
     * @return string
     */
    public function getDatabaseName(): string;

    /**
     * Quote a value for safe SQL usage
     *
     * @param mixed $value
     * @param int $type
     * @return string
     */
    public function quote(mixed $value, int $type = PDO::PARAM_STR): string;

    /**
     * Get connection statistics
     *
     * @return ConnectionStats
     */
    public function getStats(): ConnectionStats;

    /**
     * Disconnect from the database
     *
     * @return void
     */
    public function disconnect(): void;

    /**
     * Check if connected
     *
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * Reconnect to the database
     *
     * @return void
     */
    public function reconnect(): void;
}
