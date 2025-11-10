<?php

declare(strict_types=1);

namespace Laf\Core\Database;

use PDO;
use PDOStatement;
use PDOException;

/**
 * Database Connection Implementation
 * 
 * Wraps PDO with additional functionality and statistics tracking
 */
class Connection implements ConnectionInterface
{
    private ?PDO $pdo = null;
    private ConnectionStats $stats;
    private bool $connected = false;

    public function __construct(
        private readonly DatabaseConfig $config,
        private readonly ?ConnectionEventDispatcher $eventDispatcher = null,
    ) {
        $this->stats = new ConnectionStats(
            connectedAt: new \DateTimeImmutable()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPdo(): PDO
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        return $this->pdo;
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $query, array $bindings = []): PDOStatement
    {
        $startTime = microtime(true);

        try {
            $this->eventDispatcher?->beforeQuery($query, $bindings);

            $stmt = $this->prepare($query);
            $stmt->execute($bindings);

            $queryTime = microtime(true) - $startTime;
            $this->stats = $this->stats->withQuery($queryTime);

            $this->eventDispatcher?->afterQuery($query, $bindings, $queryTime);

            return $stmt;
        } catch (PDOException $e) {
            $this->eventDispatcher?->queryFailed($query, $bindings, $e);
            throw new DatabaseException(
                "Query failed: {$e->getMessage()}",
                (int)$e->getCode(),
                $e,
                $query,
                $bindings
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function execute(string $query, array $bindings = []): int
    {
        $stmt = $this->query($query, $bindings);
        return $stmt->rowCount();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchOne(string $query, array $bindings = []): ?array
    {
        $stmt = $this->query($query, $bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result === false ? null : $result;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(string $query, array $bindings = []): array
    {
        $stmt = $this->query($query, $bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn(string $query, array $bindings = []): mixed
    {
        $stmt = $this->query($query, $bindings);
        $result = $stmt->fetchColumn();
        
        return $result === false ? null : $result;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchObject(string $query, array $bindings = [], ?string $class = null): ?object
    {
        $stmt = $this->query($query, $bindings);
        
        if ($class !== null) {
            $stmt->setFetchMode(PDO::FETCH_CLASS, $class);
            $result = $stmt->fetch();
        } else {
            $result = $stmt->fetch(PDO::FETCH_OBJ);
        }
        
        return $result === false ? null : $result;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllObjects(string $query, array $bindings = [], ?string $class = null): array
    {
        $stmt = $this->query($query, $bindings);
        
        if ($class !== null) {
            return $stmt->fetchAll(PDO::FETCH_CLASS, $class);
        }
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): bool
    {
        $result = $this->getPdo()->beginTransaction();
        
        if ($result) {
            $this->stats = $this->stats->withTransactionStarted();
            $this->eventDispatcher?->transactionBegan();
        }
        
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): bool
    {
        $result = $this->getPdo()->commit();
        
        if ($result) {
            $this->stats = $this->stats->withTransactionCommitted();
            $this->eventDispatcher?->transactionCommitted();
        }
        
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(): bool
    {
        $result = $this->getPdo()->rollBack();
        
        if ($result) {
            $this->stats = $this->stats->withTransactionRolledBack();
            $this->eventDispatcher?->transactionRolledBack();
        }
        
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function inTransaction(): bool
    {
        return $this->isConnected() && $this->pdo->inTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId(?string $name = null): string
    {
        return $this->getPdo()->lastInsertId($name);
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $query): PDOStatement
    {
        try {
            return $this->getPdo()->prepare($query);
        } catch (PDOException $e) {
            throw new DatabaseException(
                "Failed to prepare statement: {$e->getMessage()}",
                (int)$e->getCode(),
                $e,
                $query
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDriver(): DatabaseDriver
    {
        return $this->config->driver;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabaseName(): string
    {
        return $this->config->database;
    }

    /**
     * {@inheritdoc}
     */
    public function quote(mixed $value, int $type = PDO::PARAM_STR): string
    {
        return $this->getPdo()->quote((string)$value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function getStats(): ConnectionStats
    {
        return $this->stats;
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(): void
    {
        $this->pdo = null;
        $this->connected = false;
        $this->eventDispatcher?->disconnected();
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected(): bool
    {
        return $this->connected && $this->pdo !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function reconnect(): void
    {
        $this->disconnect();
        $this->connect();
    }

    /**
     * Establish database connection
     *
     * @return void
     * @throws DatabaseException
     */
    private function connect(): void
    {
        $startTime = microtime(true);

        try {
            $this->eventDispatcher?->connecting($this->config);

            $this->pdo = new PDO(
                $this->config->getDsn(),
                $this->config->username,
                $this->config->password,
                $this->config->options
            );

            // Set default PDO attributes
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            $connectionTime = microtime(true) - $startTime;
            $this->connected = true;

            $this->stats = new ConnectionStats(
                connectionTime: $connectionTime,
                connectedAt: new \DateTimeImmutable()
            );

            $this->eventDispatcher?->connected($this->config, $connectionTime);
        } catch (PDOException $e) {
            $this->eventDispatcher?->connectionFailed($this->config, $e);
            
            throw new DatabaseException(
                "Failed to connect to database: {$e->getMessage()}",
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
