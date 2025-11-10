<?php

declare(strict_types=1);

namespace Laf\Core\Database;

use PDO;

/**
 * Database Configuration
 * 
 * Immutable configuration for database connections
 */
readonly class DatabaseConfig
{
    /**
     * @param DatabaseDriver $driver Database driver
     * @param string $host Database host
     * @param int $port Database port
     * @param string $database Database name
     * @param string $username Database username
     * @param string $password Database password
     * @param string $charset Character set
     * @param array<int, mixed> $options PDO options
     * @param string|null $schema Schema name (for PostgreSQL)
     * @param string|null $unixSocket Unix socket path
     */
    public function __construct(
        public DatabaseDriver $driver,
        public string $host = 'localhost',
        public int $port = 0,
        public string $database = '',
        public string $username = '',
        public string $password = '',
        public string $charset = 'utf8mb4',
        public array $options = [],
        public ?string $schema = null,
        public ?string $unixSocket = null,
    ) {}

    /**
     * Get the DSN string for PDO connection
     *
     * @return string
     */
    public function getDsn(): string
    {
        $port = $this->port ?: $this->driver->getDefaultPort();

        return match($this->driver) {
            DatabaseDriver::MYSQL => $this->getMySqlDsn($port),
            DatabaseDriver::POSTGRESQL => $this->getPostgreSqlDsn($port),
            DatabaseDriver::SQLITE => $this->getSqliteDsn(),
            DatabaseDriver::SQLSERVER => $this->getSqlServerDsn($port),
        };
    }

    /**
     * Get MySQL DSN
     *
     * @param int $port
     * @return string
     */
    private function getMySqlDsn(int $port): string
    {
        $dsn = "mysql:dbname={$this->database}";
        
        if ($this->unixSocket) {
            $dsn .= ";unix_socket={$this->unixSocket}";
        } else {
            $dsn .= ";host={$this->host};port={$port}";
        }
        
        $dsn .= ";charset={$this->charset}";
        
        return $dsn;
    }

    /**
     * Get PostgreSQL DSN
     *
     * @param int $port
     * @return string
     */
    private function getPostgreSqlDsn(int $port): string
    {
        $dsn = "pgsql:dbname={$this->database};host={$this->host};port={$port}";
        
        if ($this->schema) {
            $dsn .= ";options='--search_path={$this->schema}'";
        }
        
        return $dsn;
    }

    /**
     * Get SQLite DSN
     *
     * @return string
     */
    private function getSqliteDsn(): string
    {
        return "sqlite:{$this->database}";
    }

    /**
     * Get SQL Server DSN
     *
     * @param int $port
     * @return string
     */
    private function getSqlServerDsn(int $port): string
    {
        return "sqlsrv:Server={$this->host},{$port};Database={$this->database}";
    }

    /**
     * Create from array configuration
     *
     * @param array<string, mixed> $config
     * @return self
     */
    public static function fromArray(array $config): self
    {
        $driver = is_string($config['driver'] ?? null)
            ? DatabaseDriver::from($config['driver'])
            : ($config['driver'] ?? DatabaseDriver::MYSQL);

        return new self(
            driver: $driver,
            host: $config['host'] ?? 'localhost',
            port: $config['port'] ?? 0,
            database: $config['database'] ?? '',
            username: $config['username'] ?? '',
            password: $config['password'] ?? '',
            charset: $config['charset'] ?? 'utf8mb4',
            options: $config['options'] ?? [],
            schema: $config['schema'] ?? null,
            unixSocket: $config['unix_socket'] ?? null,
        );
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'driver' => $this->driver->value,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'charset' => $this->charset,
            'schema' => $this->schema,
            'unix_socket' => $this->unixSocket,
        ];
    }

    /**
     * Create a new instance with different database
     *
     * @param string $database
     * @return self
     */
    public function withDatabase(string $database): self
    {
        return new self(
            driver: $this->driver,
            host: $this->host,
            port: $this->port,
            database: $database,
            username: $this->username,
            password: $this->password,
            charset: $this->charset,
            options: $this->options,
            schema: $this->schema,
            unixSocket: $this->unixSocket,
        );
    }

    /**
     * Create a new instance with different schema
     *
     * @param string $schema
     * @return self
     */
    public function withSchema(string $schema): self
    {
        return new self(
            driver: $this->driver,
            host: $this->host,
            port: $this->port,
            database: $this->database,
            username: $this->username,
            password: $this->password,
            charset: $this->charset,
            options: $this->options,
            schema: $schema,
            unixSocket: $this->unixSocket,
        );
    }
}
