<?php

namespace Laf\Database;

use Laf\Exception\DbConnectException;
use Laf\Util\Settings;
use Laf\Util\Util;
use Psr\Log\LoggerInterface;

/**
 * PDO connection wrapper: one connection per name (the default one is built
 * from the `database.*` settings), statement helpers that bind values by
 * type, error capture, optional SQL error logging and a PSR-3 logger.
 *
 *   $db = Db::getInstance();                        // connects on first use
 *   $rows = Db::getAllAssoc('SELECT ... WHERE id = :id', ['id' => 5]);
 *   $stmt = $db->statement('UPDATE ... WHERE id = :id', [':id' => [5, \PDO::PARAM_INT]]);
 *   $db->transaction(function (Db $db) { ... });
 *   Db::getInstance('reports')                       // from the database.reports.* settings
 *
 * Every statement failure is rethrown as the driver's PDOException (after
 * recording it and, when configured, writing it to the SQL error log); a
 * failed connection is a DbConnectException (a PDOException subclass).
 *
 * Settings (prefix `database.`): hostname, database_name, username, password,
 * port (default by engine), engine (mysql | mariadb | postgres | postgresql | pgsql),
 * charset (MySQL, default utf8mb4), socket (MySQL unix socket), persistent (bool),
 * connect_timeout (seconds), options (PDO attribute => value), dsn (full DSN override),
 * dsn_extra (appended, e.g. "sslmode=require"), timezone (session time zone),
 * schema (PostgreSQL search_path), init_commands (SQL run after connecting),
 * empty_string_as_null (default true: '' fetched as null, as LAF always did).
 */
class Db
{
    public const DEFAULT_INSTANCE = 'default';

    /** @var array<string, int> engine => default port */
    public const DEFAULT_PORTS = [Identifier::MYSQL => 3306, Identifier::POSTGRES => 5432];

    private ?string $hostName;
    private ?string $database;
    private ?string $userName;
    private ?string $password;
    private string $engine;
    private int $port;

    private ?string $charset = null;
    private ?string $socket = null;
    private bool $persistent = false;
    private ?int $connectTimeout = null;
    /** @var array<int, mixed> PDO attribute => value, passed to the constructor and applied after connecting */
    private array $options = [];
    private ?string $dsn = null;
    private ?string $dsnExtra = null;
    private ?string $timezone = null;
    private ?string $schema = null;
    /** @var string[] */
    private array $initCommands = [];
    private bool $emptyStringAsNull = true;

    private ?\PDO $connection = null;

    private ?string $sql = null;
    private float $timeStart = 0.0;
    private float $timeEnd = 0.0;
    private int $queryCount = 0;
    private float $totalExecTime = 0.0;
    private bool $queryLogEnabled = false;
    /** @var array<int, array{sql: string, time: float, params: array<int|string, mixed>}> */
    private array $queryLog = [];

    private ?string $errorMessage = null;
    private ?string $errorTraceString = null;
    /** @var array<int, mixed> */
    private array $errorTrace = [];
    private bool $hasError = false;

    private ?SqlErrorLoggerInterface $sqlErrorLogger = null;
    private bool $sqlErrorLoggerDisabled = false;
    private bool $loggingError = false;
    private ?LoggerInterface $logger = null;

    /** @var array<string, Db> */
    private static array $instances = [];

    /**
     * Positional arguments as always: (host, database, user, password, port, engine).
     * The port may be a numeric string or empty (default for the engine); the engine
     * accepts mysql, mariadb, postgres, postgresql, pgsql.
     * @param string|null $hostName
     * @param string|null $database
     * @param string|null $userName
     * @param string|null $password
     * @param int|string|null $port
     * @param string|null $engine
     */
    public function __construct($hostName = null, $database = null, $userName = null, $password = null, $port = null, $engine = 'mysql')
    {
        $this->hostName = $hostName === null ? null : (string)$hostName;
        $this->database = $database === null ? null : (string)$database;
        $this->userName = $userName === null ? null : (string)$userName;
        $this->password = $password === null ? null : (string)$password;
        $this->engine = self::normalizeEngine($engine === null ? null : (string)$engine);
        $this->port = self::normalizePort($port, $this->engine);
        $this->setHasError(false);
    }

    /* ------------------------------------------------------------------ */
    /* instances                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * The shared connection: built from the `database.*` settings on first use
     * (another name reads `database.<name>.*` or an instance registered with
     * setInstance()). Nothing is cached until the connection succeeded, so a
     * failed first call throws again on the next one instead of handing out a
     * dead instance.
     * @throws DbConnectException
     * @throws \Laf\Exception\MissingConfigParamException
     */
    public static function getInstance(string $name = self::DEFAULT_INSTANCE): Db
    {
        if (!isset(self::$instances[$name])) {
            $db = self::fromSettings($name === self::DEFAULT_INSTANCE ? 'database' : 'database.' . $name);
            $db->connect();
            self::$instances[$name] = $db;
        }
        return self::$instances[$name];
    }

    /** Register a connection under a name (tests, a second database). */
    public static function setInstance(Db $db, string $name = self::DEFAULT_INSTANCE): void
    {
        self::$instances[$name] = $db;
    }

    public static function hasInstance(string $name = self::DEFAULT_INSTANCE): bool
    {
        return isset(self::$instances[$name]);
    }

    /** Forget (and disconnect) one named instance, or all of them. */
    public static function resetInstance(?string $name = null): void
    {
        $names = $name === null ? array_keys(self::$instances) : [$name];
        foreach ($names as $key) {
            if (isset(self::$instances[$key])) {
                self::$instances[$key]->disconnect();
                unset(self::$instances[$key]);
            }
        }
    }

    /**
     * Build (without connecting) from Settings keys under $prefix: hostname,
     * database_name and username are required; everything else is optional.
     * @throws \Laf\Exception\MissingConfigParamException
     */
    public static function fromSettings(string $prefix = 'database'): Db
    {
        $settings = Settings::getInstance();
        $prefix = rtrim($prefix, '.') . '.';

        $db = new Db(
            $settings->getProperty($prefix . 'hostname'),
            $settings->getProperty($prefix . 'database_name'),
            $settings->getProperty($prefix . 'username'),
            self::setting($settings, $prefix . 'password', ''),
            self::setting($settings, $prefix . 'port', null),
            self::setting($settings, $prefix . 'engine', Identifier::MYSQL)
        );

        $charset = self::setting($settings, $prefix . 'charset', null);
        if (is_string($charset) && $charset !== '') {
            $db->setCharset($charset);
        }
        $socket = self::setting($settings, $prefix . 'socket', null);
        if (is_string($socket) && $socket !== '') {
            $db->setSocket($socket);
        }
        $db->setPersistent((bool)self::setting($settings, $prefix . 'persistent', false));
        $timeout = self::setting($settings, $prefix . 'connect_timeout', null);
        if (is_numeric($timeout)) {
            $db->setConnectTimeout((int)$timeout);
        }
        $options = self::setting($settings, $prefix . 'options', []);
        if (is_array($options)) {
            $db->setOptions($options);
        }
        $dsn = self::setting($settings, $prefix . 'dsn', null);
        if (is_string($dsn) && $dsn !== '') {
            $db->setDsn($dsn);
        }
        $dsnExtra = self::setting($settings, $prefix . 'dsn_extra', null);
        if (is_string($dsnExtra) && $dsnExtra !== '') {
            $db->setDsnExtra($dsnExtra);
        }
        $timezone = self::setting($settings, $prefix . 'timezone', null);
        if (is_string($timezone) && $timezone !== '') {
            $db->setTimezone($timezone);
        }
        $schema = self::setting($settings, $prefix . 'schema', null);
        if (is_string($schema) && $schema !== '') {
            $db->setSchema($schema);
        }
        $initCommands = self::setting($settings, $prefix . 'init_commands', []);
        if (is_array($initCommands)) {
            $db->setInitCommands(array_values(array_map('strval', $initCommands)));
        }
        $db->setEmptyStringAsNull((bool)self::setting($settings, $prefix . 'empty_string_as_null', true));
        return $db;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private static function setting(Settings $settings, string $key, $default)
    {
        return $settings->propertyExists($key) ? $settings->getProperty($key) : $default;
    }

    /** mysql for mysql/mariadb, postgres for postgres/postgresql/pgsql; anything else is kept and refused by connect(). */
    public static function normalizeEngine(?string $engine): string
    {
        $engine = strtolower(trim((string)$engine));
        if ($engine === '' || in_array($engine, ['mysql', 'mariadb'], true)) {
            return Identifier::MYSQL;
        }
        if (in_array($engine, ['postgres', 'postgresql', 'pgsql'], true)) {
            return Identifier::POSTGRES;
        }
        return $engine;
    }

    /**
     * @param mixed $port numeric string, int, null or false (unset getenv)
     */
    private static function normalizePort($port, string $engine): int
    {
        if (is_int($port) && $port > 0) {
            return $port;
        }
        if (is_string($port) && is_numeric($port) && (int)$port > 0) {
            return (int)$port;
        }
        return self::DEFAULT_PORTS[$engine] ?? 3306;
    }

    /* ------------------------------------------------------------------ */
    /* connection                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Open the connection (no-op when already open) and return the PDO handle.
     * @throws DbConnectException
     */
    public function connect(): \PDO
    {
        if ($this->connection !== null) {
            return $this->connection;
        }
        if (!isset(self::DEFAULT_PORTS[$this->engine])) {
            throw new DbConnectException(sprintf('Unsupported database engine "%s" (use mysql or postgres)', $this->engine));
        }

        $dsn = $this->getDsn();
        $options = $this->options;
        if ($this->persistent) {
            $options[\PDO::ATTR_PERSISTENT] = true;
        }
        if ($this->connectTimeout !== null) {
            $options[\PDO::ATTR_TIMEOUT] = $this->connectTimeout;
        }

        $this->startTimer();
        try {
            $connection = new \PDO($dsn, $this->userName, $this->password, $options);
            $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            if (!array_key_exists(\PDO::ATTR_EMULATE_PREPARES, $this->options)) {
                $connection->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            }
            if ($this->emptyStringAsNull) {
                $connection->setAttribute(\PDO::ATTR_ORACLE_NULLS, \PDO::NULL_EMPTY_STRING);
            }
            $this->connection = $connection;
            $this->runInitCommands();
        } catch (\PDOException $ex) {
            $this->connection = null;
            $this->recordError($ex);
            $this->stopTimer();
            $this->log('error', 'Database connection failed', ['dsn' => $dsn, 'error' => $ex->getMessage()]);
            throw $ex instanceof DbConnectException ? $ex : DbConnectException::fromPdo($ex, $dsn);
        }
        $this->stopTimer();
        $this->setHasError(false);
        $this->log('debug', 'Database connected', ['dsn' => $dsn, 'time' => $this->getExecTime()]);
        return $this->connection;
    }

    private function runInitCommands(): void
    {
        $connection = $this->connection;
        if ($connection === null) {
            return;
        }
        $commands = [];
        if ($this->isPostgres()) {
            if ($this->charset !== null) {
                $commands[] = 'SET NAMES ' . $connection->quote($this->charset);
            }
            if ($this->schema !== null) {
                $commands[] = 'SET search_path TO ' . Identifier::quote($this->schema, Identifier::POSTGRES);
            }
            if ($this->timezone !== null) {
                $commands[] = 'SET TIME ZONE ' . $connection->quote($this->timezone);
            }
        } else if ($this->timezone !== null) {
            $commands[] = 'SET time_zone = ' . $connection->quote($this->timezone);
        }
        foreach (array_merge($commands, $this->initCommands) as $command) {
            $connection->exec($command);
        }
    }

    /** Close the connection; the next statement (or connect()) reopens it. */
    public function disconnect(): void
    {
        $this->connection = null;
    }

    /**
     * @throws DbConnectException
     */
    public function reconnect(): \PDO
    {
        $this->disconnect();
        return $this->connect();
    }

    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    /** True when the server answers; false when the connection is closed or gone away. */
    public function ping(): bool
    {
        if ($this->connection === null) {
            return false;
        }
        try {
            return $this->connection->query('SELECT 1') !== false;
        } catch (\Throwable $ex) {
            return false;
        }
    }

    /**
     * The DSN for the engine (or the configured override), credentials excluded.
     */
    public function getDsn(): string
    {
        if ($this->dsn !== null) {
            return $this->dsn;
        }
        if ($this->engine === Identifier::POSTGRES) {
            $dsn = sprintf('pgsql:dbname=%s;host=%s;port=%d', (string)$this->database, (string)$this->hostName, $this->port);
        } else if ($this->socket !== null) {
            $dsn = sprintf('mysql:dbname=%s;unix_socket=%s;charset=%s', (string)$this->database, $this->socket, $this->charset ?? 'utf8mb4');
        } else {
            $dsn = sprintf('mysql:dbname=%s;host=%s;port=%d;charset=%s', (string)$this->database, (string)$this->hostName, $this->port, $this->charset ?? 'utf8mb4');
        }
        if ($this->dsnExtra !== null) {
            $dsn .= ';' . ltrim($this->dsnExtra, ';');
        }
        return $dsn;
    }

    /**
     * The PDO handle, opened on demand.
     * @throws DbConnectException
     */
    public function getConnection(): \PDO
    {
        return $this->connection ?? $this->connect();
    }

    /**
     * @param mixed $value
     */
    public function setAttribute(int $attribute, $value): bool
    {
        return $this->getConnection()->setAttribute($attribute, $value);
    }

    /**
     * @return mixed
     */
    public function getAttribute(int $attribute)
    {
        return $this->getConnection()->getAttribute($attribute);
    }

    /** Identifier::MYSQL or Identifier::POSTGRES */
    public function getEngine(): string
    {
        return $this->engine;
    }

    public function isMysql(): bool
    {
        return $this->engine === Identifier::MYSQL;
    }

    public function isPostgres(): bool
    {
        return $this->engine === Identifier::POSTGRES;
    }

    /** PDO driver name of the open connection (mysql, pgsql) */
    public function getDriverName(): string
    {
        return (string)$this->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    public function getServerVersion(): string
    {
        return (string)$this->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }

    /* ------------------------------------------------------------------ */
    /* statements                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Runs a query and returns the result set
     * @throws \PDOException
     */
    public function query(string $sql): \PDOStatement
    {
        $connection = $this->getConnection();
        return $this->timed($sql, [], function () use ($connection, $sql): \PDOStatement {
            $statement = $connection->query($sql);
            if ($statement === false) {
                throw new \PDOException('Query failed: ' . $sql);
            }
            return $statement;
        });
    }

    /**
     * Execute a statement and return the number of affected rows
     * @throws \PDOException
     */
    public function execute(string $sql): int
    {
        $connection = $this->getConnection();
        return $this->timed($sql, [], function () use ($connection, $sql): int {
            $count = $connection->exec($sql);
            return $count === false ? 0 : $count;
        });
    }

    /**
     * Prepare a statement (execution errors are only captured when the
     * statement is run through statement(); plain prepare() + execute()
     * raises the driver's PDOException untouched).
     * @throws \PDOException
     */
    public function prepare(string $sql): \PDOStatement
    {
        $connection = $this->getConnection();
        return $this->timed($sql, [], function () use ($connection, $sql): \PDOStatement {
            $statement = $connection->prepare($sql);
            if ($statement === false) {
                throw new \PDOException('Prepare failed: ' . $sql);
            }
            return $statement;
        });
    }

    /**
     * Prepare, bind and execute in one go, with timing and error capture.
     * Parameters: ':name' or 'name' keys (or 0-based positional), values bound
     * by PHP type (int → PARAM_INT, bool → 0/1, null → PARAM_NULL, else string),
     * or an explicit [value, PDO::PARAM_*] pair.
     * @param array<int|string, mixed> $params
     * @throws \PDOException
     */
    public function statement(string $sql, array $params = []): \PDOStatement
    {
        $connection = $this->getConnection();
        return $this->timed($sql, $params, function () use ($connection, $sql, $params): \PDOStatement {
            $statement = $connection->prepare($sql);
            if ($statement === false) {
                throw new \PDOException('Prepare failed: ' . $sql);
            }
            foreach ($params as $key => $param) {
                [$value, $type] = self::binding($param);
                $statement->bindValue(is_int($key) ? $key + 1 : $key, $value, $type);
            }
            $statement->execute();
            return $statement;
        });
    }

    /**
     * @param mixed $param a value, or [value, PDO::PARAM_*]
     * @return array{0: mixed, 1: int}
     */
    public static function binding($param): array
    {
        if (is_array($param) && array_keys($param) === [0, 1] && is_int($param[1])) {
            $value = $param[0];
            if ($param[1] === \PDO::PARAM_INT && is_bool($value)) {
                $value = $value ? 1 : 0;
            }
            return [$value, $param[1]];
        }
        if ($param === null) {
            return [null, \PDO::PARAM_NULL];
        }
        if (is_bool($param)) {
            return [$param ? 1 : 0, \PDO::PARAM_INT];
        }
        if (is_int($param)) {
            return [$param, \PDO::PARAM_INT];
        }
        if ($param instanceof \DateTimeInterface) {
            return [$param->format('Y-m-d H:i:s'), \PDO::PARAM_STR];
        }
        if (is_scalar($param) || (is_object($param) && method_exists($param, '__toString'))) {
            return [(string)$param, \PDO::PARAM_STR];
        }
        throw new \InvalidArgumentException('Cannot bind a value of type ' . gettype($param));
    }

    /**
     * Time one statement, record failures, write the SQL error log, rethrow.
     * @param array<int|string, mixed> $params
     * @param callable(): mixed $run
     * @return mixed
     * @throws \PDOException
     */
    private function timed(string $sql, array $params, callable $run)
    {
        $this->sql = $sql;
        $this->startTimer();
        try {
            $result = $run();
        } catch (\PDOException $ex) {
            $this->stopTimer();
            $this->recordError($ex);
            $this->log('error', 'Statement failed', ['sql' => $sql, 'params' => self::paramsForLog($params), 'error' => $ex->getMessage()]);
            $this->handleSqlErrorLogging(null, $sql, $ex);
            throw $ex;
        }
        $this->stopTimer();
        $this->queryCount++;
        $this->totalExecTime += $this->getExecTime();
        if ($this->queryLogEnabled) {
            $this->queryLog[] = ['sql' => $sql, 'time' => $this->getExecTime(), 'params' => self::paramsForLog($params)];
        }
        $this->log('debug', 'Statement', ['sql' => $sql, 'time' => $this->getExecTime()]);
        return $result;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int|string, mixed>
     */
    private static function paramsForLog(array $params): array
    {
        $plain = [];
        foreach ($params as $key => $param) {
            $plain[$key] = is_array($param) && array_keys($param) === [0, 1] && is_int($param[1]) ? $param[0] : $param;
        }
        return $plain;
    }

    /**
     * Get the ID of the new record after an insert.
     * On PostgreSQL pass the sequence name, or rely on INSERT ... RETURNING
     * instead: without a name lastInsertId() means lastval(), which fails when
     * no sequence was used in the session and can belong to another table.
     */
    public function getInsertId(?string $sequence = null): string
    {
        $id = $this->getConnection()->lastInsertId($sequence);
        return $id === false ? '' : (string)$id;
    }

    /**
     * Quote a value as an SQL literal (prefer bound parameters)
     * @param mixed $string
     */
    public function quote($string): string
    {
        $quoted = $this->getConnection()->quote((string)$string);
        return $quoted === false ? "'" . str_replace("'", "''", (string)$string) . "'" : $quoted;
    }

    /* ------------------------------------------------------------------ */
    /* transactions                                                        */
    /* ------------------------------------------------------------------ */

    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    public function rollBack(): bool
    {
        return $this->getConnection()->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->connection !== null && $this->connection->inTransaction();
    }

    /**
     * Run $fn inside a transaction: commit when it returns, roll back and
     * rethrow when it throws. Nest-aware: when a transaction is already open
     * (yours or the application's) $fn simply runs inside it and the outer
     * transaction decides.
     *
     *   Db::getInstance()->transaction(function (Db $db) use ($order) { $order->store(); ... });
     *
     * @param callable(Db): mixed $fn
     * @return mixed what $fn returned
     * @throws \Throwable whatever $fn threw, after the rollback
     */
    public function transaction(callable $fn)
    {
        if ($this->inTransaction()) {
            return $fn($this);
        }
        $this->beginTransaction();
        try {
            $result = $fn($this);
            $this->commit();
            return $result;
        } catch (\Throwable $ex) {
            if ($this->connection !== null && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $ex;
        }
    }

    /* ------------------------------------------------------------------ */
    /* fetch helpers (instance)                                            */
    /* ------------------------------------------------------------------ */

    /**
     * First row as column => value, [] when nothing matches
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>
     */
    public function fetchAssoc(string $sql, array $params = []): array
    {
        $row = $this->statement($sql, $params)->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    /**
     * Every row as column => value
     * @param array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchAllAssoc(string $sql, array $params = []): array
    {
        return $this->statement($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * First row as a positional list, [] when nothing matches
     * @param array<int|string, mixed> $params
     * @return array<int, mixed>
     */
    public function fetchNum(string $sql, array $params = []): array
    {
        $row = $this->statement($sql, $params)->fetch(\PDO::FETCH_NUM);
        return is_array($row) ? $row : [];
    }

    /**
     * Every row as a positional list
     * @param array<int|string, mixed> $params
     * @return array<int, array<int, mixed>>
     */
    public function fetchAllNum(string $sql, array $params = []): array
    {
        return $this->statement($sql, $params)->fetchAll(\PDO::FETCH_NUM);
    }

    /**
     * One value of the first row as a string ('1'/'0' for booleans), null when
     * nothing matches or the value is NULL
     * @param array<int|string, mixed> $params
     */
    public function fetchOne(string $sql, array $params = [], int $columnIndex = 0): ?string
    {
        // fetch the row, not the column: fetchColumn() returns false both for "no row" and
        // for a PostgreSQL boolean false, which pdo_pgsql hands back as a real PHP false
        $row = $this->statement($sql, $params)->fetch(\PDO::FETCH_NUM);
        if (!is_array($row) || !array_key_exists($columnIndex, $row)) {
            return null;
        }
        $value = $row[$columnIndex];
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return (string)$value;
    }

    /**
     * One column of every row
     * @param array<int|string, mixed> $params
     * @return array<int, mixed>
     */
    public function fetchColumn(string $sql, array $params = [], int $columnIndex = 0): array
    {
        return $this->statement($sql, $params)->fetchAll(\PDO::FETCH_COLUMN, $columnIndex);
    }

    /**
     * First column => second column of every row
     * @param array<int|string, mixed> $params
     * @return array<int|string, mixed>
     */
    public function fetchPairs(string $sql, array $params = []): array
    {
        return $this->statement($sql, $params)->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /**
     * First row as an object of $className, null when nothing matches
     * @param array<int|string, mixed> $params
     */
    public function fetchObject(string $sql, array $params = [], string $className = 'stdClass'): ?object
    {
        $object = $this->statement($sql, $params)->fetchObject($className);
        return $object === false ? null : $object;
    }

    /* ------------------------------------------------------------------ */
    /* fetch helpers (static, on the default connection)                   */
    /* ------------------------------------------------------------------ */

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed> [] when nothing matches
     * @throws \PDOException
     */
    public static function getRowAssoc(string $sql, array $params = []): array
    {
        return self::getInstance()->fetchAssoc($sql, $params);
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     * @throws \PDOException
     */
    public static function getAllAssoc(string $sql, array $params = []): array
    {
        return self::getInstance()->fetchAllAssoc($sql, $params);
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int, mixed> [] when nothing matches
     * @throws \PDOException
     */
    public static function getRowNum(string $sql, array $params = []): array
    {
        return self::getInstance()->fetchNum($sql, $params);
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int, array<int, mixed>>
     * @throws \PDOException
     */
    public static function getAllNum(string $sql, array $params = []): array
    {
        return self::getInstance()->fetchAllNum($sql, $params);
    }

    /**
     * @param array<int|string, mixed> $params
     * @param int $fetchType PDO::FETCH_* (default assoc)
     * @return array<int|string, mixed> [] when nothing matches
     * @throws \PDOException
     */
    public static function getRow(string $sql, array $params = [], int $fetchType = \PDO::FETCH_ASSOC): array
    {
        $row = self::getInstance()->statement($sql, $params)->fetch($fetchType);
        return is_array($row) ? $row : [];
    }

    /**
     * @param array<int|string, mixed> $params
     * @return object|null null when nothing matches
     * @throws \PDOException
     */
    public static function getRowObject(string $sql, array $params = [], string $className = 'stdClass'): ?object
    {
        return self::getInstance()->fetchObject($sql, $params, $className);
    }

    /**
     * @param array<int|string, mixed> $params
     * @throws \PDOException
     */
    public static function getOne(string $sql, array $params = [], int $columnIndex = 0): ?string
    {
        return self::getInstance()->fetchOne($sql, $params, $columnIndex);
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int, mixed>
     * @throws \PDOException
     */
    public static function getColumn(string $sql, array $params = [], int $columnIndex = 0): array
    {
        return self::getInstance()->fetchColumn($sql, $params, $columnIndex);
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int|string, mixed>
     * @throws \PDOException
     */
    public static function getPairs(string $sql, array $params = []): array
    {
        return self::getInstance()->fetchPairs($sql, $params);
    }

    /**
     * Runs a statement on the default connection without returning anything
     * @throws \PDOException
     */
    public static function run(string $sql): void
    {
        self::getInstance()->execute($sql);
    }

    /**
     * Quote a value as an SQL literal on the default connection (prefer bound parameters)
     * @param mixed $value
     */
    public static function escape($value): string
    {
        return self::getInstance()->quote($value);
    }

    /* ------------------------------------------------------------------ */
    /* connection settings                                                 */
    /* ------------------------------------------------------------------ */

    public function getHostName(): ?string
    {
        return $this->hostName;
    }

    public function setHostName(?string $hostName): static
    {
        $this->hostName = $hostName;
        return $this;
    }

    public function getDatabase(): ?string
    {
        return $this->database;
    }

    public function setDatabase(?string $database): static
    {
        $this->database = $database;
        return $this;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(?string $userName): static
    {
        $this->userName = $userName;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @param int|string|null $port empty = default for the engine
     */
    public function setPort($port): static
    {
        $this->port = self::normalizePort($port, $this->engine);
        return $this;
    }

    public function setEngine(string $engine): static
    {
        $this->engine = self::normalizeEngine($engine);
        return $this;
    }

    public function getCharset(): ?string
    {
        return $this->charset;
    }

    /** MySQL DSN charset (default utf8mb4); PostgreSQL client encoding via SET NAMES */
    public function setCharset(?string $charset): static
    {
        $this->charset = $charset;
        return $this;
    }

    public function getSocket(): ?string
    {
        return $this->socket;
    }

    /** MySQL unix socket path (replaces host/port in the DSN) */
    public function setSocket(?string $socket): static
    {
        $this->socket = $socket;
        return $this;
    }

    public function isPersistent(): bool
    {
        return $this->persistent;
    }

    public function setPersistent(bool $persistent): static
    {
        $this->persistent = $persistent;
        return $this;
    }

    public function getConnectTimeout(): ?int
    {
        return $this->connectTimeout;
    }

    public function setConnectTimeout(?int $seconds): static
    {
        $this->connectTimeout = $seconds;
        return $this;
    }

    /** @return array<int, mixed> */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * PDO attributes / driver options passed when connecting (PDO::ATTR_* or PDO::MYSQL_ATTR_* => value)
     * @param array<int, mixed> $options
     */
    public function setOptions(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    /** Full DSN override (credentials still come from user/password) */
    public function setDsn(?string $dsn): static
    {
        $this->dsn = $dsn;
        return $this;
    }

    /** Appended to the generated DSN, e.g. "sslmode=require" */
    public function setDsnExtra(?string $dsnExtra): static
    {
        $this->dsnExtra = $dsnExtra;
        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    /** Session time zone set after connecting (MySQL SET time_zone, PostgreSQL SET TIME ZONE) */
    public function setTimezone(?string $timezone): static
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function getSchema(): ?string
    {
        return $this->schema;
    }

    /** PostgreSQL search_path set after connecting */
    public function setSchema(?string $schema): static
    {
        $this->schema = $schema;
        return $this;
    }

    /** @return string[] */
    public function getInitCommands(): array
    {
        return $this->initCommands;
    }

    /**
     * SQL statements run right after connecting (sql_mode, session variables, ...)
     * @param string[] $commands
     */
    public function setInitCommands(array $commands): static
    {
        $this->initCommands = array_values(array_map('strval', $commands));
        return $this;
    }

    public function isEmptyStringAsNull(): bool
    {
        return $this->emptyStringAsNull;
    }

    /** Whether '' fetched from the database is handed back as null (PDO::NULL_EMPTY_STRING, LAF's default) */
    public function setEmptyStringAsNull(bool $emptyStringAsNull): static
    {
        $this->emptyStringAsNull = $emptyStringAsNull;
        return $this;
    }

    /* ------------------------------------------------------------------ */
    /* last statement, timing, query log                                   */
    /* ------------------------------------------------------------------ */

    /** The last statement this connection ran */
    public function getSql(): ?string
    {
        return $this->sql;
    }

    public function setSql(?string $sql): void
    {
        $this->sql = $sql;
    }

    public function getTimeStart(): float
    {
        return $this->timeStart;
    }

    public function setTimeStart(float $timeStart): void
    {
        $this->timeStart = $timeStart;
    }

    private function startTimer(): void
    {
        $this->timeStart = microtime(true);
        $this->timeEnd = 0.0;
    }

    private function stopTimer(): void
    {
        $this->timeEnd = microtime(true);
    }

    /** Seconds the last statement (or connection) took */
    public function getExecTime(): float
    {
        if ($this->timeEnd == 0.0) {
            $this->stopTimer();
        }
        return $this->timeEnd - $this->timeStart;
    }

    /** Statements run through this connection since it was created */
    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    /** Seconds spent in statements since the connection was created */
    public function getTotalExecTime(): float
    {
        return $this->totalExecTime;
    }

    /** Keep every statement with its time and parameters (debugging; off by default) */
    public function enableQueryLog(bool $enable = true): static
    {
        $this->queryLogEnabled = $enable;
        return $this;
    }

    /** @return array<int, array{sql: string, time: float, params: array<int|string, mixed>}> */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    public function clearQueryLog(): static
    {
        $this->queryLog = [];
        return $this;
    }

    /* ------------------------------------------------------------------ */
    /* error state                                                         */
    /* ------------------------------------------------------------------ */

    private function recordError(\Throwable $ex): void
    {
        $this->hasError = true;
        $this->errorMessage = $ex->getMessage();
        $this->errorTrace = $ex->getTrace();
        $this->errorTraceString = $ex->getTraceAsString();
    }

    public function hasError(): bool
    {
        return $this->hasError;
    }

    public function getHasError(): bool
    {
        return $this->hasError;
    }

    public function setHasError(bool $hasError): void
    {
        $this->hasError = $hasError;
    }

    /** Message of the last failed statement or connection attempt */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getErrorTraceString(): ?string
    {
        return $this->errorTraceString;
    }

    public function setErrorTraceString(?string $errorTraceString): void
    {
        $this->errorTraceString = $errorTraceString;
    }

    /** @return array<int, mixed> */
    public function getErrorTrace(): array
    {
        return $this->errorTrace;
    }

    /** @param array<int, mixed> $errorTrace */
    public function setErrorTrace(array $errorTrace): void
    {
        $this->errorTrace = $errorTrace;
    }

    public function clearError(): void
    {
        $this->hasError = false;
        $this->errorMessage = null;
        $this->errorTrace = [];
        $this->errorTraceString = null;
    }

    /* ------------------------------------------------------------------ */
    /* loggers                                                             */
    /* ------------------------------------------------------------------ */

    public function getSqlErrorLogger(): ?SqlErrorLoggerInterface
    {
        return $this->sqlErrorLogger;
    }

    /** Store every failed statement through the application's error log table; null switches it off */
    public function setSqlErrorLogger(?SqlErrorLoggerInterface $sqlErrorLogger): void
    {
        $this->sqlErrorLogger = $sqlErrorLogger;
        $this->sqlErrorLoggerDisabled = false;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /** PSR-3 logger: every statement at debug level with its time, failures at error level */
    public function setLogger(?LoggerInterface $logger): static
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * Write a failed statement to the SQL error log. Never throws and never
     * replaces the original exception: a missing log table switches the
     * logger off for this connection (reported once through the PSR logger
     * or error_log), and a failure while logging is ignored.
     */
    private function handleSqlErrorLogging(?string $customMessage, ?string $sqlQuery, \Throwable $ex): void
    {
        $logger = $this->sqlErrorLogger;
        if ($logger === null || $this->sqlErrorLoggerDisabled || $this->loggingError || $this->connection === null) {
            return;
        }
        $this->loggingError = true;
        try {
            if (!$this->tableExists($logger->getTableName())) {
                $this->sqlErrorLoggerDisabled = true;
                $warning = sprintf('Missing sql logger table %s: SQL error logging is off for this connection', $logger->getTableName());
                if ($this->logger !== null) {
                    $this->logger->warning($warning);
                } else {
                    error_log($warning);
                }
                return;
            }
            $logger->setCustomMessage($customMessage);
            $logger->setSqlQuery($sqlQuery);
            $logger->setErrorMessage($ex->getMessage());
            $logger->setFile($ex->getFile());
            $logger->setLineNumber((string)$ex->getLine());
            $logger->setTraceAsString($ex->getTraceAsString());
            $logger->storeLogEntry();
        } catch (\Throwable $loggingFailure) {
            $this->log('warning', 'Writing the SQL error log failed', ['error' => $loggingFailure->getMessage()]);
        } finally {
            $this->loggingError = false;
        }
    }

    /** Whether a table exists in the connected database / current schema (both engines) */
    public function tableExists(string $tableName): bool
    {
        $tableName = Identifier::sanitize($tableName);
        if ($tableName === '') {
            return false;
        }
        if ($this->isPostgres()) {
            $sql = 'SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = :t';
        } else {
            $sql = 'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t';
        }
        $statement = $this->getConnection()->prepare($sql);
        $statement->bindValue(':t', $tableName, \PDO::PARAM_STR);
        $statement->execute();
        return $statement->fetchColumn() !== false;
    }

    /* ------------------------------------------------------------------ */
    /* name helpers (kept for compatibility; see Laf\Util\Util)            */
    /* ------------------------------------------------------------------ */

    /**
     * ServiceOrder -> service_order
     * @param string $className
     */
    public static function convertClassNameToTableName($className): string
    {
        return ltrim(strtolower((string)preg_replace('/([A-Z])/', '_$1', (string)$className)), '_');
    }

    /**
     * service_order -> ServiceOrder
     * @param string $tableName
     */
    public static function convertTableNameToClassName($tableName): string
    {
        return Util::tableNameToClassName((string)$tableName);
    }

    /**
     * Field name to its public (rot13) form
     * @param string $fieldName
     */
    public static function encodeFieldNameToHash($fieldName): string
    {
        return str_rot13((string)$fieldName);
    }

    /**
     * Public (rot13) form back to the field name
     * @param string $hash
     */
    public static function getFieldNameFromHash($hash): ?string
    {
        return str_rot13((string)$hash);
    }
}
