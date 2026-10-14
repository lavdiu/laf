# Database access with Db

`Laf\Database\Db` is the PDO wrapper underneath every model, query and grid in LAF. You use it directly when a
model is the wrong tool: reports with joins, bulk updates, tables that have no generated class, schema checks,
or a second database. It opens one connection per name from the `database.*` settings, binds values by their PHP
type, nests transactions with savepoints, reopens a dropped connection, and reports every statement to an
optional PSR-3 logger and SQL error log. `Laf\Database\Identifier` is the single quoting rule for table and column
names on MySQL/MariaDB and PostgreSQL.

On this page:

- [Connecting](#connecting)
- [Connection settings reference](#connection-settings-reference)
- [Named connections](#named-connections)
- [Reading data](#reading-data)
- [Typed bindings](#typed-bindings)
- [Running statements](#running-statements)
- [Writing plain tables: insert, update, delete](#writing-plain-tables-insert-update-delete)
- [Transactions and savepoints](#transactions-and-savepoints)
- [Errors](#errors)
- [Lost connections](#lost-connections)
- [Schema helpers](#schema-helpers)
- [SQL error log](#sql-error-log)
- [PSR-3 logger, query log and timing](#psr-3-logger-query-log-and-timing)
- [Identifier quoting](#identifier-quoting)
- [PostgreSQL specifics](#postgresql-specifics)
- [Method reference](#method-reference)

## Connecting

You rarely construct a `Db` yourself. Configure the `database.*` settings once in your bootstrap (see
[01-getting-started.md](01-getting-started.md) and [16-settings-locale-utilities.md](16-settings-locale-utilities.md))
and ask for the shared instance:

```php
use Laf\Database\Db;
use Laf\Util\Settings;

Settings::load([
    'database' => [
        'engine' => 'mysql',          // mysql | mariadb | postgres | postgresql | pgsql
        'hostname' => '127.0.0.1',
        'port' => 3306,
        'database_name' => 'laf_docs',
        'username' => 'root',
        'password' => '',
    ],
]);

$db = Db::getInstance();              // connects on first use
echo $db->getDsn();                   // mysql:dbname=laf_docs;host=127.0.0.1;port=3306;charset=utf8mb4
echo $db->getEngine();                // mysql      (Identifier::MYSQL)
echo $db->getDriverName();            // mysql      (PDO driver of the open connection)
echo $db->getPort();                  // 3306
```

`getInstance()` builds the connection with `Db::fromSettings()`, connects, and only then caches it. A failed
first attempt throws `Laf\Exception\DbConnectException` and is *not* cached, so the next call tries again instead
of handing out a dead object.

What happens on connect:

- `PDO::ATTR_ERRMODE` is set to `ERRMODE_EXCEPTION`: every failure is an exception.
- `PDO::ATTR_EMULATE_PREPARES` is set to `false` (native prepares) unless you pass it in `database.options`.
  Native prepares mean a `LIMIT ?` binds as an integer and integers come back as PHP `int` on MySQL.
- `PDO::ATTR_ORACLE_NULLS` is set to `PDO::NULL_EMPTY_STRING` while `database.empty_string_as_null` is true (the
  default): an empty string read from the database comes back as `null`. LAF has always behaved this way and the
  form/model code relies on it.
- The session commands run: time zone, PostgreSQL `search_path` / client encoding, then `database.init_commands`.
  `getSessionCommands()` shows them without connecting.

> **Note:** `empty_string_as_null` affects reads only. Writing `''` stores `''`; reading it back gives `null`.
> Turn it off (`'empty_string_as_null' => false`) only for a connection that no LAF model or form uses.

## Connection settings reference

Every key lives under `database.` (or `database.<name>.` for a named connection). `hostname`, `database_name` and
`username` are required; the rest is optional.

| Key | Default | What it does |
|---|---|---|
| `engine` | `mysql` | `mysql` / `mariadb` (both mean MySQL) or `postgres` / `postgresql` / `pgsql`. Anything else is refused by `connect()`. |
| `hostname` | required | Server host. |
| `port` | 3306 / 5432 | Integer or numeric string; empty means the engine's default. |
| `database_name` | required | Database (MySQL) or database name (PostgreSQL). |
| `username` | required | |
| `password` | `''` | |
| `charset` | `utf8mb4` | MySQL: part of the DSN. PostgreSQL: sent as `SET NAMES '...'` when set. |
| `socket` | none | MySQL unix socket; replaces host and port in the DSN. |
| `persistent` | `false` | `PDO::ATTR_PERSISTENT`. |
| `connect_timeout` | none | Seconds, `PDO::ATTR_TIMEOUT`. |
| `options` | `[]` | PDO attribute => value, passed to the PDO constructor (`PDO::MYSQL_ATTR_SSL_CA => '/path/ca.pem'`). |
| `dsn` | none | Full DSN override; credentials still come from `username` / `password`. |
| `dsn_extra` | none | Appended to the generated DSN, e.g. `sslmode=require`. |
| `timezone` | none | Session time zone: `SET time_zone = '...'` (MySQL), `SET TIME ZONE '...'` (PostgreSQL). |
| `schema` | none | PostgreSQL `search_path`; a comma list such as `app, public` is allowed. |
| `init_commands` | `[]` | SQL statements run after connecting (`SET sql_mode = ...`). |
| `empty_string_as_null` | `true` | `''` fetched as `null` (see above). |
| `reconnect_on_loss` | `true` | Reopen a dropped connection and retry the statement once (see [Lost connections](#lost-connections)). |

`sample_files/settings.php` lists every key LAF reads; [16-settings-locale-utilities.md](16-settings-locale-utilities.md)
reproduces it.

## Named connections

A second database (a reporting replica, a legacy system) is configured under `database.<name>.*` and reached
with `Db::getInstance('<name>')`. Models always use the default connection; named connections are for your own
`Db` calls.

```php
Settings::load([
    'database' => [
        'reports' => [
            'engine' => 'pgsql',
            'hostname' => 'db2',
            'database_name' => 'reports',
            'username' => 'ro',
            'schema' => 'app, public',
            'timezone' => 'UTC',
            'dsn_extra' => 'sslmode=require',
        ],
    ],
]);

$reports = Db::fromSettings('database.reports');   // built, not connected
echo $reports->getDsn();
// pgsql:dbname=reports;host=db2;port=5432;sslmode=require
print_r($reports->getSessionCommands());
// [0] => SET search_path TO "app", "public"
// [1] => SET TIME ZONE 'UTC'

$rows = Db::getInstance('reports')->fetchAllAssoc('SELECT ...');   // connects and caches under "reports"
```

Other instance helpers:

- `Db::setInstance($db, $name = 'default')` registers a connection you built yourself (tests, a DSN from a
  secrets store).
- `Db::hasInstance($name)` tells whether one is registered, without connecting.
- `Db::resetInstance($name)` disconnects and forgets one; `Db::resetInstance()` forgets all of them.

You can also build a connection by hand. The constructor keeps its historical positional order
`(host, database, user, password, port, engine)`, and every setting has a fluent setter:

```php
$legacy = (new Db('10.0.0.5', 'erp_old', 'reader', 'secret', '3307', 'mariadb'))
    ->setConnectTimeout(2)
    ->setInitCommands(["SET SESSION sql_mode = 'ANSI_QUOTES'"]);
Db::setInstance($legacy, 'legacy');
```

## Reading data

There are two families of fetch helpers. The **static** ones run on the default connection and are what most code
uses; the **instance** ones run on whichever `Db` you call them on. All of them prepare the statement, bind the
parameters and return plain PHP arrays.

| Static (default connection) | Instance | Returns |
|---|---|---|
| `Db::getAllAssoc($sql, $params)` | `fetchAllAssoc()` | every row as column => value |
| `Db::getRowAssoc($sql, $params)` | `fetchAssoc()` | first row, `[]` when nothing matches |
| `Db::getAllKeyed($sql, $params, $keyColumn)` | `fetchAllKeyed()` | every row, keyed by a column (the first one by default) |
| `Db::getAllNum($sql, $params)` | `fetchAllNum()` | every row as a positional list |
| `Db::getRowNum($sql, $params)` | `fetchNum()` | first row as a positional list, `[]` when nothing matches |
| `Db::getRow($sql, $params, $fetchType)` | | first row in any `PDO::FETCH_*` mode, `[]` when nothing matches |
| `Db::getRowObject($sql, $params, $class)` | `fetchObject()` | first row as an object, `null` when nothing matches |
| `Db::getOne($sql, $params, $columnIndex)` | `fetchOne()` | one value as a string (`'1'`/`'0'` for booleans), `null` when no row or NULL |
| `Db::getColumn($sql, $params, $columnIndex)` | `fetchColumn()` | one column of every row |
| `Db::getPairs($sql, $params)` | `fetchPairs()` | first column => second column (select exactly two) |

Joins are where `Db` earns its place, because a model query covers one table
(see [04-queries.md](04-queries.md)):

```php
use Laf\Database\Db;

$rows = Db::getAllAssoc(
    'SELECT e.id, e.first_name, e.last_name, c.name AS company
       FROM employee e
       LEFT JOIN company c ON c.id = e.company_id
      WHERE e.is_active = :active AND c.name LIKE :name
      ORDER BY e.last_name',
    ['active' => true, 'name' => 'Acme%']
);
```

What the helpers returned against the example database (two companies created for the demo):

```php
Db::getRowAssoc('SELECT id, name, email, vat_number FROM company WHERE id = :id', ['id' => 8]);
// ['id' => 8, 'name' => 'ch13 Acme', 'email' => 'info@ch13-acme.test', 'vat_number' => null]

Db::getOne('SELECT COUNT(*) FROM company WHERE name LIKE :p', [':p' => 'ch13%']);   // "1"  (a string)
Db::getOne('SELECT name FROM company WHERE id = ?', [-1]);                           // null (no row)

Db::getPairs('SELECT id, name FROM company WHERE name LIKE ? ORDER BY id', ['ch13%']);
// [8 => 'ch13 Acme', 9 => 'ch13 Globex']

Db::getColumn('SELECT name FROM company WHERE name LIKE ? ORDER BY id', ['ch13%']);
// ['ch13 Acme', 'ch13 Globex']

Db::getAllKeyed('SELECT id, name FROM company WHERE name LIKE ? ORDER BY id', ['ch13%']);
// [8 => ['id' => 8, 'name' => 'ch13 Acme'], 9 => ['id' => 9, 'name' => 'ch13 Globex']]
```

Parameter keys may be written `':name'` or `'name'`; a list (`[0 => ...]`) binds to positional `?` placeholders.

> **Note:** `getOne()` always returns a string or null, never an int, so compare with `(int)` or `===` on the
> string. It reads the whole first row rather than using `fetchColumn()`, because a PostgreSQL boolean `false`
> would otherwise be indistinguishable from "no row".

## Typed bindings

`Db::binding($value)` decides how each parameter is bound, and every helper (and `statement()`) goes through it:

| PHP value | Bound as | Example result |
|---|---|---|
| `null` | `PDO::PARAM_NULL` | `[null, PDO::PARAM_NULL]` |
| `bool` | `PDO::PARAM_INT`, 1 / 0 | `Db::binding(false)` gives `[0, PDO::PARAM_INT]` |
| `int` | `PDO::PARAM_INT` | |
| `DateTimeInterface` | `PDO::PARAM_STR`, `Y-m-d H:i:s` | `'2026-10-14 08:30:00'` |
| float, other scalar, `Stringable` | `PDO::PARAM_STR` | `Db::binding(1.5)` gives `['1.5', PDO::PARAM_STR]` |
| `[value, PDO::PARAM_*]` | the type you name | `[':n' => ['42', PDO::PARAM_INT]]` |
| array, resource, other object | `InvalidArgumentException` | |

Binding booleans as integers is what makes the same code work on MySQL `tinyint(1)` and PostgreSQL `boolean`
columns. Use the explicit pair when the PHP type is not what the column needs (a numeric string for an integer
`LIMIT`, a large object):

```php
Db::getOne('SELECT ? + 0', [true]);                         // "1"
Db::getOne('SELECT :n', [':n' => ['42', PDO::PARAM_INT]]);  // "42"
```

## Running statements

| Method | Use it for | Returns |
|---|---|---|
| `$db->statement($sql, $params)` | any statement with parameters: prepare, bind (typed), execute, timing, logging, reconnect | the executed `PDOStatement` |
| `$db->query($sql)` | a statement without parameters whose rows you want | `PDOStatement` |
| `$db->execute($sql)` | a statement without parameters (DDL, a fixed UPDATE) | affected row count |
| `Db::run($sql)` | `execute()` on the default connection, no return value | `void` |
| `$db->prepare($sql)` | a statement you bind and execute yourself (loops) | `PDOStatement` |

```php
$stmt = Db::getInstance()->statement(
    'UPDATE employee SET is_active = :active WHERE company_id = :company',
    ['active' => false, 'company' => 8]
);
echo $stmt->rowCount();
```

`statement()` is the method every other helper is built on. `prepare()` hands you a plain `PDOStatement`: a
failure while executing *that* statement is the driver's untouched `PDOException`; it is not timed, logged or
retried.

> **Note:** `quote()` and `Db::escape()` exist for the rare case where a literal must be written into SQL text
> (a DDL default). Prefer parameters everywhere else.

## Writing plain tables: insert, update, delete

For tables without a model (log tables, imports, link tables you fill in bulk) `Db` has small write helpers. Table
and column names are checked to be plain identifiers (`[A-Za-z0-9_]`) and quoted for the engine; values bind like
`statement()` parameters.

```php
$db = Db::getInstance();

$id = $db->insertGetId('company', [
    'name' => 'ch13 Acme',
    'email' => 'info@ch13-acme.test',
    'record_status_id' => 1,
]);
// "8" (a string, like PDO::lastInsertId())

$affected = $db->update('company', ['vat_number' => 'DE123', 'phone' => null], ['id' => [8, 9]]);
// 2  -- UPDATE `company` SET `vat_number` = ?, `phone` = ? WHERE `id` IN (?, ?)

$deleted = $db->delete('company', ['name' => ['ch13 Acme', 'ch13 Globex']]);
// 2
```

| Method | What it does |
|---|---|
| `insert($table, $data)` | INSERT one row, returns the affected row count. |
| `insertGetId($table, $data, $idColumn = 'id')` | INSERT and return the new key as a string: `RETURNING "id"` on PostgreSQL, `lastInsertId()` on MySQL. |
| `update($table, $data, $where)` | UPDATE matching rows, returns the affected row count. |
| `delete($table, $where)` | DELETE matching rows, returns the affected row count. |
| `identifier($name)` | The quoted identifier for this connection, `InvalidArgumentException` for anything not `[A-Za-z0-9_]`. |

`$where` is `column => value`, joined with AND: `null` becomes `IS NULL`, a list becomes `IN (...)` (an empty list
becomes `1 = 0`, matching nothing), anything else is `=`. An empty `$where` is refused, so a forgotten condition
cannot wipe a table:

```php
$db->delete('company', []);
// InvalidArgumentException: update()/delete() need a non-empty where (column => value)

$db->identifier('company; DROP');
// InvalidArgumentException: "company; DROP" is not a plain SQL identifier
```

Use `execute()` when you really mean a full-table update.

> **Note:** these helpers bypass the models: no validation, no `created_on` / `updated_on`, no audit log entry,
> no hooks. Use the model's `store()` for application data ([03-models.md](03-models.md)) and keep the helpers for
> tables that have no model or for deliberate bulk work.

## Transactions and savepoints

`$db->transaction(fn)` runs a callable inside a transaction: it commits when the callable returns, rolls back and
rethrows when it throws, and returns what the callable returned. `BaseObject::transaction(fn)` is the same thing on
the default connection.

The important property is nesting. When a transaction is already open (yours, a model's, or one the application
opened on the raw PDO handle) the inner call runs inside a `SAVEPOINT`. An inner failure that the outer code catches
undoes only the inner work, and the outer transaction stays usable on both engines:

```php
$result = $db->transaction(function (Db $db) {
    $db->update('company', ['phone' => '111'], ['id' => 8]);
    try {
        $db->transaction(function (Db $db) {
            $db->update('company', ['phone' => '222'], ['id' => 8]);
            throw new RuntimeException('inner failed');      // ROLLBACK TO SAVEPOINT laf_sp_1
        });
    } catch (RuntimeException $e) {
        echo 'caught: ', $e->getMessage(), "\n";
    }
    return 'outer done';
});

var_dump($result);                                                   // "outer done"
var_dump(Db::getOne('SELECT phone FROM company WHERE id = ?', [8])); // "111"
```

With models:

```php
use Laf\Database\BaseObject;
use MyApp\Company;
use MyApp\Employee;

$employeeId = BaseObject::transaction(function () {
    $company = new Company();
    $company->setNameVal('Initech');
    $company->store();

    $employee = new Employee();
    $employee->setCompanyIdVal($company->getIdVal());
    $employee->setFirstNameVal('Peter');
    $employee->setLastNameVal('Gibbons');
    $employee->setEmailVal('peter@initech.test');
    $employee->store();            // throws: the company row is rolled back too
    return $employee->getIdVal();
});
```

The manual methods `beginTransaction()`, `commit()`, `rollBack()` and `inTransaction()` are there when the
callback form does not fit (a transaction spanning several requests to a worker loop, for example). They do not
nest; `transaction()` does.

## Errors

Every statement failure is rethrown as the driver's own `PDOException`, after it has been recorded, logged and
(when configured) written to the SQL error log. Models wrap it in `Laf\Exception\DatabaseException` (see
[03-models.md](03-models.md)); `Db` does not, because existing applications catch `PDOException`.

```php
try {
    $db->insert('company', ['name' => 'ch13 Acme']);    // name is UNIQUE
} catch (PDOException $e) {
    echo $e->getCode();     // 23000
    echo $e->getMessage();  // SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'ch13 Acme' for key 'company_name_unique'
    var_dump($db->hasError());          // true
    echo $db->getErrorMessage();        // the same message
}
```

A connection that cannot be opened throws `Laf\Exception\DbConnectException`, a `PDOException` subclass whose
message names the DSN (never the password) and whose code is the driver's:

```text
Laf\Exception\DbConnectException (code 2002): Database connection failed
[mysql:dbname=laf_docs;host=127.0.0.1;port=3399;charset=utf8mb4]: SQLSTATE[HY000] [2002] ...
```

The error state of a connection: `hasError()` (alias `getHasError()`), `getErrorMessage()`, `getErrorTrace()`,
`getErrorTraceString()`, `clearError()`, and the setters `setHasError()`, `setErrorMessage()`, `setErrorTrace()`,
`setErrorTraceString()`.

> **Note:** `hasError()` describes the *last failure*, not the last statement: a successful statement does not
> reset it. Call `clearError()` before a block whose outcome you want to check this way, or simply catch the
> exception.

## Lost connections

Long-running workers and idle web connections meet "MySQL server has gone away" or a terminated PostgreSQL
backend. With `database.reconnect_on_loss` (default `true`) a statement that fails because the connection is gone
is retried once on a fresh connection, **only when no transaction is open** (inside a transaction the work is lost
anyway and the caller has to restart it). The reconnect is logged at warning level.

`Db::isLostConnection(PDOException $e)` is the rule: SQLSTATE `08xxx` / `57P01`-`57P03`, MySQL client codes
2002, 2006, 2013, 4031, or one of the driver messages in `Db::LOST_CONNECTION_MESSAGES`.

```php
Db::isLostConnection(new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away'));  // true
```

Related methods: `isConnected()`, `ping()` (runs `SELECT 1`, false on any failure), `disconnect()`, `reconnect()`,
`connect()`, `setReconnectOnLoss(bool)` / `isReconnectOnLoss()`.

## Schema helpers

These read `information_schema` for the connected database (MySQL) or the current schema (PostgreSQL). They run on
the raw handle, so they are neither counted nor logged.

```php
$db->tableExists('company');                  // true
$db->columnExists('employee', 'company_id');  // true
$db->getTableNames();                         // ['audit_log', 'company', 'employee', 'employee_project', 'project']
```

`getTableNames()` lists base tables only (no views), sorted by name. The generator uses the same helpers.

## SQL error log

Applications built on LAF often keep failed statements in a table (asm's `sql_error`). Give `Db` an object that
implements `Laf\Database\SqlErrorLoggerInterface`, usually a generated model with a few extra methods, and every
failed statement is written through it:

```php
use Laf\Database\Db;
use Laf\Database\SqlErrorLoggerInterface;

class SqlError extends Base\BaseSqlError implements SqlErrorLoggerInterface
{
    public function setSqlQuery(?string $value): void { $this->setSqlQueryVal($value); }
    public function setErrorMessage(?string $value): void { $this->setErrorMessageVal($value); }
    public function setFile(?string $value): void { $this->setFileVal($value); }
    public function setLineNumber(?string $value): void { $this->setLineNumberVal($value); }
    public function setTraceAsString(?string $value): void { $this->setTraceVal($value); }
    public function setCustomMessage(?string $value): void { $this->setCustomMessageVal($value); }
    public function getSqlQuery(): ?string { return $this->getSqlQueryVal(); }
    public function getErrorMessage(): ?string { return $this->getErrorMessageVal(); }
    public function getFile(): ?string { return $this->getFileVal(); }
    public function getLineNumber(): ?string { return $this->getLineNumberVal(); }
    public function getTraceAsString(): ?string { return $this->getTraceVal(); }
    public function getCustomMessage(): ?string { return $this->getCustomMessageVal(); }
    public function storeLogEntry(): bool { return (bool)$this->auditLogDisable()->insert(); }
    public function getTableName(): string { return 'sql_error'; }
}

Db::getInstance()->setSqlErrorLogger(new SqlError());
```

(The column names above are an example; `sql_error` is not part of the shared schema.)

The rules `Db` follows, so that logging an error never causes a second one:

- A **fresh logger object** is created for each entry (when the class has a constructor without required
  arguments), so a second failure is a new row, not an UPDATE of the first.
- **Inside a transaction** the entry is kept and written when the transaction ends (commit or rollback): written
  inside, it would vanish with the rollback (MySQL) or be refused by the aborted transaction (PostgreSQL). At most
  `Db::MAX_PENDING_ERROR_LOGS` (100) entries wait; `getPendingErrorLogCount()` shows how many.
- A missing log table (checked with `getTableName()`) switches logging off for that connection, reported once
  through the PSR-3 logger or `error_log()`.
- A failure while writing the log is swallowed (logged at warning level) and never replaces the original
  exception. Connection failures are not written.

`setSqlErrorLogger(null)` switches it off; `getSqlErrorLogger()` returns the current one.

## PSR-3 logger, query log and timing

`setLogger(LoggerInterface)` attaches any PSR-3 logger (Monolog is already installed as a LAF dependency).
Every statement is logged at debug level with its time, failures at error level, reconnects and logging problems at
warning level:

```php
use Laf\Database\Db;
use Psr\Log\AbstractLogger;

class EchoLogger extends AbstractLogger
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        echo strtoupper($level), ' ', $message, ' ', json_encode(array_intersect_key($context, ['sql' => 1, 'error' => 1])), "\n";
    }
}

Db::getInstance()->setLogger(new EchoLogger());
Db::getOne('SELECT COUNT(*) FROM project');
try { Db::getOne('SELECT nope FROM project'); } catch (PDOException $e) {}
```

```text
DEBUG Statement {"sql":"SELECT COUNT(*) FROM project"}
ERROR Statement failed {"sql":"SELECT nope FROM project","error":"SQLSTATE[42S22]: Column not found: 1054 Unknown column 'nope' in 'SELECT'"}
```

For debugging a page, keep every statement in memory instead:

```php
$db->enableQueryLog();
// ... work ...
foreach ($db->getQueryLog() as $entry) {
    echo $entry['sql'], ' | ', json_encode($entry['params']), ' | ', round($entry['time'] * 1000, 2), " ms\n";
}
// INSERT INTO `company` (`name`, `email`, `record_status_id`) VALUES (?, ?, ?) | ["ch13 Acme","info@ch13-acme.test",1] | ...
// SELECT id, name, email, vat_number FROM company WHERE id = :id | {"id":8} | ...
echo $db->getQueryCount(), ' statements, ', $db->getTotalExecTime(), " s\n";
$db->clearQueryLog();
```

Timing of the last statement: `getExecTime()` (seconds), `getSql()` (its SQL), `getTimeStart()`; plus the setters
`setSql()` and `setTimeStart()`.

> **Note:** the query log and the logger receive parameter values. Do not enable them in production for tables
> that hold secrets, or filter the context in your logger.

## Identifier quoting

`Laf\Database\Identifier` is the one quoting rule for SQL names in LAF: backticks on MySQL/MariaDB, double quotes
on PostgreSQL. Every name is first reduced to `[A-Za-z0-9_]`, so a quoted identifier cannot carry injected
characters whatever its source.

```php
use Laf\Database\Identifier;

Identifier::quote('order');                          // `order`
Identifier::quote('order', Identifier::POSTGRES);    // "order"
Identifier::quoteQualified('employee', 'company_id');// `employee`.`company_id`
Identifier::quote('na`me; --');                      // `name`   (characters stripped, not escaped)
Identifier::engine();                                // mysql
```

| Member | What it does |
|---|---|
| `Identifier::MYSQL`, `Identifier::POSTGRES` | The two normalised engine names (`'mysql'`, `'postgres'`). |
| `engine()` | The engine of the registered default connection (without connecting), else Settings `database.engine`, else `mysql`. |
| `isMysql(?$engine)`, `isPostgres(?$engine)` | Tests against `engine()` or the given name. |
| `sanitize($name)` | Removes every character that is not `[A-Za-z0-9_]`. |
| `quote($name, ?$engine)` | Sanitised and quoted. |
| `quoteQualified($table, $column, ?$engine)` | `table.column`, both parts quoted. |

> **Note:** `Identifier::quote()` silently strips characters, while `Db::identifier()` refuses a name that needs
> stripping. Use `Db::identifier()` (or validate against the model's columns, as `Query` does) when the name comes
> from a request: a stripped name may still be a *different* valid column.

## PostgreSQL specifics

LAF runs the same code on both engines; the differences you may notice are these:

- **Engine names.** `postgres`, `postgresql` and `pgsql` all normalise to `Identifier::POSTGRES`.
- **Quoting.** Identifiers are double-quoted. Write your own SQL with unquoted lower-case names or with
  `Identifier::quote()`.
- **New ids.** `insertGetId()` uses `INSERT ... RETURNING`, and so do models. `getInsertId()` without a
  sequence name means `lastval()`, which fails when no sequence was used in the session; pass the sequence name
  (`getInsertId('employee_id_seq')`) if you call it yourself.
- **Booleans** come back as PHP `true` / `false`; `getOne()` turns them into `'1'` / `'0'` so both engines give the
  same string.
- **Case-insensitive search.** Model queries (`whereContains()` and friends) and the grid use
  `CAST(col AS TEXT) ILIKE`; plain `LIKE` in your own SQL is case-sensitive on PostgreSQL.
- **Schemas.** `database.schema` sets the `search_path`; `tableExists()`, `getTableNames()` and the generator read
  `current_schema()`.
- **Failed statements inside a transaction** abort the transaction on PostgreSQL. Use nested `transaction()`
  calls (savepoints) around statements that may fail and that you intend to catch.
- **Audit log `changes`** is `JSONB` on PostgreSQL; see [14-audit-log.md](14-audit-log.md).

## Method reference

Instances and connection:

| Method | What it does | Notes |
|---|---|---|
| `Db::getInstance($name = 'default')` | The shared connection, connected on first use | Throws `DbConnectException`, `MissingConfigParamException` |
| `Db::setInstance($db, $name)` / `hasInstance($name)` / `resetInstance(?$name)` | Register, test, forget | |
| `Db::fromSettings($prefix = 'database')` | Build from Settings without connecting | |
| `Db::normalizeEngine(?$engine)` | `mysql` / `postgres` / the unknown name as given | |
| `new Db($host, $db, $user, $password, $port, $engine)` | Build by hand | Positional, all optional |
| `connect()` / `disconnect()` / `reconnect()` | Open (no-op when open), close, both | Return the `PDO` handle (`connect`, `reconnect`) |
| `isConnected()` / `ping()` | Handle present / server answers | |
| `getConnection()` | The PDO handle, opened on demand | On PHP 8.4+ a `Pdo\Mysql` / `Pdo\Pgsql` subclass |
| `getDsn()` / `getSessionCommands()` | Inspect what `connect()` will use | Credentials never in the DSN |
| `setAttribute($attr, $value)` / `getAttribute($attr)` | PDO attributes on the open handle | |
| `getEngine()` / `isMysql()` / `isPostgres()` / `getDriverName()` / `getServerVersion()` | Engine information | |
| `Db::isLostConnection($pdoException)` | The lost-connection rule | |
| `Db::binding($value)` | `[value, PDO::PARAM_*]` for a parameter | |

Statements and fetching: `query()`, `execute()`, `prepare()`, `statement()`, `getInsertId(?$sequence)`,
`quote()`, `fetchAssoc()`, `fetchAllAssoc()`, `fetchAllKeyed()`, `fetchNum()`, `fetchAllNum()`, `fetchOne()`,
`fetchColumn()`, `fetchPairs()`, `fetchObject()`; static `getRowAssoc()`, `getAllAssoc()`, `getAllKeyed()`,
`getRowNum()`, `getAllNum()`, `getRow()`, `getRowObject()`, `getOne()`, `getColumn()`, `getPairs()`, `run()`,
`escape()` (all described above).

Writes and transactions: `insert()`, `insertGetId()`, `update()`, `delete()`, `identifier()`, `transaction()`,
`beginTransaction()`, `commit()`, `rollBack()`, `inTransaction()`.

Settings getters and fluent setters (each setter returns the `Db`):

| Getter | Setter |
|---|---|
| `getHostName()` | `setHostName(?string)` |
| `getDatabase()` | `setDatabase(?string)` |
| `getUserName()` | `setUserName(?string)` |
| `getPassword()` | `setPassword(?string)` |
| `getPort()` (engine default when unset) | `setPort(int\|string\|null)` |
| `getEngine()` | `setEngine(string)` |
| `getCharset()` | `setCharset(?string)` |
| `getSocket()` | `setSocket(?string)` |
| `isPersistent()` | `setPersistent(bool)` |
| `getConnectTimeout()` | `setConnectTimeout(?int)` |
| `getOptions()` | `setOptions(array)` |
| (`getDsn()`) | `setDsn(?string)`, `setDsnExtra(?string)` |
| `getTimezone()` | `setTimezone(?string)` |
| `getSchema()` | `setSchema(?string)` |
| `getInitCommands()` | `setInitCommands(array)` |
| `isEmptyStringAsNull()` | `setEmptyStringAsNull(bool)` |
| `isReconnectOnLoss()` | `setReconnectOnLoss(bool)` |

Settings changes apply on the next `connect()`; call `reconnect()` to apply them to an open connection.

Diagnostics: `getSql()`, `setSql()`, `getTimeStart()`, `setTimeStart()`, `getExecTime()`, `getQueryCount()`,
`getTotalExecTime()`, `enableQueryLog(bool = true)`, `getQueryLog()`, `clearQueryLog()`, the error-state methods,
`getSqlErrorLogger()`, `setSqlErrorLogger()`, `getPendingErrorLogCount()`, `getLogger()`, `setLogger()`; schema:
`tableExists()`, `columnExists()`, `getTableNames()`.

Name helpers kept for compatibility (prefer `Laf\Util\Util`): `Db::convertClassNameToTableName('ServiceOrder')`
gives `service_order`, `Db::convertTableNameToClassName()`, `Db::encodeFieldNameToHash()` /
`getFieldNameFromHash()` (rot13).

Constants: `DEFAULT_INSTANCE`, `DEFAULT_PORTS`, `LOST_CONNECTION_SQLSTATES`, `LOST_CONNECTION_MYSQL_CODES`,
`LOST_CONNECTION_MESSAGES`, `MAX_PENDING_ERROR_LOGS`.

## See also

- [03-models.md](03-models.md): `BaseObject` writes, `DatabaseException`, model transactions
- [04-queries.md](04-queries.md): single-table `Query` builder; when to fall back to `Db`
- [14-audit-log.md](14-audit-log.md): what the models log, and what the `Db` write helpers do not
- [16-settings-locale-utilities.md](16-settings-locale-utilities.md): loading `database.*` from files and the environment
- [17-security.md](17-security.md): SQL injection rules
- [18-testing-and-tooling.md](18-testing-and-tooling.md): integration tests against MariaDB and PostgreSQL
