<?php

declare(strict_types=1);

namespace Laf\Core\Database;

use PDOException;

/**
 * Connection Event Dispatcher
 * 
 * Dispatches events for database operations (logging, monitoring, etc.)
 */
interface ConnectionEventDispatcher
{
    /**
     * Event fired before connecting to database
     *
     * @param DatabaseConfig $config
     * @return void
     */
    public function connecting(DatabaseConfig $config): void;

    /**
     * Event fired after successful connection
     *
     * @param DatabaseConfig $config
     * @param float $connectionTime
     * @return void
     */
    public function connected(DatabaseConfig $config, float $connectionTime): void;

    /**
     * Event fired when connection fails
     *
     * @param DatabaseConfig $config
     * @param PDOException $exception
     * @return void
     */
    public function connectionFailed(DatabaseConfig $config, PDOException $exception): void;

    /**
     * Event fired when disconnecting
     *
     * @return void
     */
    public function disconnected(): void;

    /**
     * Event fired before executing a query
     *
     * @param string $query
     * @param array<mixed> $bindings
     * @return void
     */
    public function beforeQuery(string $query, array $bindings): void;

    /**
     * Event fired after successful query execution
     *
     * @param string $query
     * @param array<mixed> $bindings
     * @param float $executionTime
     * @return void
     */
    public function afterQuery(string $query, array $bindings, float $executionTime): void;

    /**
     * Event fired when query fails
     *
     * @param string $query
     * @param array<mixed> $bindings
     * @param PDOException $exception
     * @return void
     */
    public function queryFailed(string $query, array $bindings, PDOException $exception): void;

    /**
     * Event fired when transaction begins
     *
     * @return void
     */
    public function transactionBegan(): void;

    /**
     * Event fired when transaction is committed
     *
     * @return void
     */
    public function transactionCommitted(): void;

    /**
     * Event fired when transaction is rolled back
     *
     * @return void
     */
    public function transactionRolledBack(): void;
}
