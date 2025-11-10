<?php

declare(strict_types=1);

namespace Laf\Repository;

use Laf\Core\Database\ConnectionInterface;

/**
 * Abstract Repository
 * 
 * Base class for all repositories with common CRUD operations
 */
abstract class AbstractRepository
{
    /**
     * @param ConnectionInterface $connection Database connection
     * @param class-string $modelClass Model class name
     * @param string $tableName Database table name
     */
    public function __construct(
        protected readonly ConnectionInterface $connection,
        protected readonly string $modelClass,
        protected readonly string $tableName,
    ) {}

    /**
     * Find records matching criteria
     *
     * @param array<string, mixed> $criteria
     * @param array<string, string> $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return array<object>
     */
    protected function find(
        array $criteria = [],
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $sql = "SELECT * FROM {$this->tableName}";
        $bindings = [];

        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $column => $value) {
                $conditions[] = "{$column} = ?";
                $bindings[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        if (!empty($orderBy)) {
            $orderClauses = [];
            foreach ($orderBy as $column => $direction) {
                $orderClauses[] = "{$column} " . strtoupper($direction);
            }
            $sql .= " ORDER BY " . implode(', ', $orderClauses);
        }

        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }

        if ($offset !== null) {
            $sql .= " OFFSET {$offset}";
        }

        return $this->connection->fetchAllObjects($sql, $bindings, $this->modelClass);
    }

    /**
     * Find a single record matching criteria
     *
     * @param array<string, mixed> $criteria
     * @return object|null
     */
    protected function findOne(array $criteria): ?object
    {
        $results = $this->find($criteria, [], 1);
        return $results[0] ?? null;
    }

    /**
     * Build INSERT query
     *
     * @param array<string, mixed> $data
     * @return string
     */
    protected function buildInsertQuery(array $data): string
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($data), '?');

        return sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->tableName,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
    }

    /**
     * Build UPDATE query
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $criteria
     * @return string
     */
    protected function buildUpdateQuery(array $data, array $criteria): string
    {
        $setClauses = [];
        foreach (array_keys($data) as $column) {
            $setClauses[] = "{$column} = ?";
        }

        $whereClauses = [];
        foreach (array_keys($criteria) as $column) {
            $whereClauses[] = "{$column} = ?";
        }

        return sprintf(
            "UPDATE %s SET %s WHERE %s",
            $this->tableName,
            implode(', ', $setClauses),
            implode(' AND ', $whereClauses)
        );
    }

    /**
     * Execute a raw query and return objects
     *
     * @param string $sql
     * @param array<mixed> $bindings
     * @return array<object>
     */
    protected function query(string $sql, array $bindings = []): array
    {
        return $this->connection->fetchAllObjects($sql, $bindings, $this->modelClass);
    }

    /**
     * Execute a raw query and return a single object
     *
     * @param string $sql
     * @param array<mixed> $bindings
     * @return object|null
     */
    protected function queryOne(string $sql, array $bindings = []): ?object
    {
        return $this->connection->fetchObject($sql, $bindings, $this->modelClass);
    }

    /**
     * Begin a transaction
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit a transaction
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Rollback a transaction
     *
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->connection->rollback();
    }
}
