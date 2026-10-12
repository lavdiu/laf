Second review of `src/Laf/Database/Db.php` after the 8f50144 rework: 7 remaining defects fixed (error log lost inside transactions, flat nesting, dead cached connection after a server drop, port not following `setEngine()`, schema lists, engine source, lost SQLSTATE), savepoint nesting, reconnect-on-loss, plain-table write helpers, keyed fetch and schema lookups, verified on MariaDB and PostgreSQL under E_ALL on PHP 8.5.

# Db review 2 — 2026-10-12

Branch `improvements1`, on top of the first Db rework (8f50144, notes `agent/2026-10-11-db-notes.md`). Companion edits:
`src/Laf/Database/Identifier.php` (`engine()`), `src/Laf/Exception/DbConnectException.php` (code), `src/Laf/UI/Grid/SimpleTable.php`
(dead branch), `src/Laf/UI/Grid/PhpGrid/PhpGrid.php` (one E_ALL deprecation), `tests/DbUnitTest.php` (+7), `tests/DbTest.php` (+5),
`tests/KeysAndTableTest.php` (detaches the default instance), `CLAUDE.md`, `sample_files/database.php`.

## 1. Defects found and fixed

| # | Where | Defect | Fix |
|---|---|---|---|
| 1 | SQL error logger inside a transaction | the log row was written inside the application's transaction: MySQL discarded it with the rollback, PostgreSQL refused both the `tableExists()` check and the INSERT in the aborted transaction (failure swallowed). Errors raised inside transactions never reached asm's `sql_error` table | entries raised while `inTransaction()` (or on a dead connection) are queued (`MAX_PENDING_ERROR_LOGS` = 100) and written after `commit()`, `rollBack()`, `transaction()`, from the next statement when the application rolled back on the raw PDO, and in `__destruct()` |
| 2 | `transaction()` nesting | an inner call just joined the outer transaction: an inner failure that the outer code caught left partial writes (MySQL) or an aborted transaction (PostgreSQL) | inner calls run in `SAVEPOINT laf_sp_<n>` / `RELEASE` / `ROLLBACK TO`, also when the application opened the transaction on `getConnection()`; the outermost call still does BEGIN/COMMIT/ROLLBACK |
| 3 | lost connections | MySQL 2006/2013/4031 and PostgreSQL 08xxx/57P01 were ordinary `PDOException`s; the cached instance stayed dead for the rest of a worker's life (routerarena `article_queue_worker.php`, asm archive crons) | `isLostConnection()` (SQLSTATE list, MySQL client codes, driver message fragments); `timed()` reopens the connection and runs the statement once more when `reconnect_on_loss` is on (default) and no transaction was open before the statement; inside a transaction the driver exception is rethrown untouched |
| 4 | `setEngine()` after construction | the port had been defaulted for the constructor's engine (`new Db(h,d,u,p)` then `setEngine('postgres')` → 3306) | `$port` stays null when not given; `getPort()` resolves the engine default on demand |
| 5 | `Identifier::engine()` | read `Settings` only: a named connection on the other engine, or `setEngine()`, quoted with the wrong character while `PhpGrid::isPostgres()` asked the driver | the registered default instance's engine wins (never connects), Settings remain the fallback |
| 6 | PostgreSQL `schema` | `Identifier::quote('app, public')` → `"apppublic"` | the list is split, each name quoted; `getSessionCommands()` exposes the session SQL without connecting |
| 7 | `DbConnectException::fromPdo()` | the SQLSTATE code was dropped (`new self($message, 0, $ex)`) | code copied (PDO codes are strings; `PDOException` allows that) |
| 8 | `SimpleTable::toHTML()` | checked `query() === false` and threw `new \Exception($db->getErrorMessage())` (null now): dead branch since `query()` throws | removed |
| 9 | `PhpGrid::isValid()` | `strlen(null)` deprecation under E_ALL when no SQL is set | cast |

## 2. New functionality

- **Write helpers** (instance methods, any named connection): `insert($table, $data): int`, `insertGetId($table, $data, $idColumn = 'id'): string`
  (`RETURNING` on PostgreSQL, `lastInsertId()` on MySQL), `update($table, $data, $where): int`, `delete($table, $where): int`.
  `$where` is column => value: `null` → `IS NULL`, a list → `IN (...)` (empty list matches nothing), else `=`, several keys ANDed;
  an empty `$where` or an empty row throws `InvalidArgumentException` before any connection attempt. Identifiers go through
  `identifier()` (whitelisted, quoted for the engine; anything else refused rather than silently rewritten); values bind like
  `statement()` parameters (typed pairs allowed in `$data`; in `$where` a list is always `IN`, typed pairs only inside it).
- **Fetch**: `fetchAllKeyed($sql, $params, $keyColumn = null)` / `getAllKeyed()` (first column by default, the key column stays in the row).
- **Schema**: `columnExists($table, $column)`, `getTableNames()` next to `tableExists()`, all on `information_schema` with
  `DATABASE()` / `current_schema()`, run on the raw handle so they are neither counted nor logged.
- **Connection**: `setReconnectOnLoss()/isReconnectOnLoss()`, `isLostConnection()` (public, for app retry loops),
  `getPendingErrorLogCount()`, `getSessionCommands()`; `fromSettings()` reads `reconnect_on_loss`.
- **PHP 8.4+**: `connect()` uses `\PDO::connect()` when it exists, so `getConnection()` hands out `Pdo\Mysql` / `Pdo\Pgsql`
  (driver methods such as `Pdo\Pgsql::escapeIdentifier()`); identical behaviour on 8.0–8.3. No 8.1+ syntax anywhere.

## 3. Compatibility

Kept: every public method and signature of 8f50144, the positional constructor with string/false ports, raw `PDOException` from
statements, `getConnection()` for raw PDO use (routerarena transactions keep working and now nest with `transaction()`), the static
helpers, `setSqlErrorLogger()`.

Behaviour changes (additive or opt-out):
- A lost connection outside a transaction is retried once (off with `database.reconnect_on_loss = false` / `setReconnectOnLoss(false)`).
- Nested `transaction()` calls have savepoint semantics; the outermost call behaves as before.
- SQL error-log rows raised inside a transaction appear after the transaction ends instead of being lost; connection failures are
  not written to the SQL error table (as before).
- `setEngine()` re-defaults a port that was not given explicitly.
- `Identifier::engine()` follows the registered default instance; `KeysAndTableTest` detaches it while it drives quoting from Settings
  (the Unit suite leaves a connected default instance behind when the local MariaDB is up).

## 4. Verification

- `php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Unit`: 533 tests / 1,789 assertions (PHP 8.5.4), including the new
  `DbUnitTest` cases (port re-default, lost-connection detection, session commands and schema lists, `Identifier::engine()` from the
  instance, refused empty where / unsafe identifiers, connect exception code, `reconnect_on_loss` setting).
- Integration MariaDB 12.2 and PostgreSQL 18.3: 146 tests each (601 / 602 assertions), including `DbTest`: error-log entries inside
  `beginTransaction()`, a raw-PDO transaction and a nested `transaction()`; savepoint nesting with a PHP exception, with a failed
  statement (PostgreSQL transaction stays usable), under a raw-PDO outer transaction, and the outermost rollback; a connection killed from
  a second connection (`KILL` / `pg_terminate_backend`) with retry on, retry off, and inside a transaction; the write helpers; keyed fetch;
  `columnExists()` / `getTableNames()`; the `Pdo\*` subclass on 8.4+.
- PHPStan level 5: clean on `Db.php`, `Identifier.php`, `DbConnectException.php` (the five `SimpleTable.php` findings are unrelated lines, pre-existing).
- CI runs both engines as `root` (superuser), so the kill test has the rights it needs there too.

## 5. Not done / next

- `Query` always runs on the default instance; a `Query::setDb()` for named connections is the next step if the ERP needs a second database in models.
- `ping()` returns false on PostgreSQL while a transaction is aborted even though the connection is alive (documented, not changed).
- The lost-connection retry re-runs the statement once; a statement that reached the server before the drop (rare mid-execution loss) could in
  theory run twice. Switch the retry off for non-idempotent batch scripts if that matters.
- routerarena's memory note `reference_laf_db_no_execute.md` claims LAF Db has no `execute()`/`update()`; it is stale (not this repo).
