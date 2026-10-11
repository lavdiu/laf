Review and rework of Laf\Database\AuditLog and the audit hooks in BaseObject: bugs found, fixes, new reading API, verification on MariaDB and PostgreSQL (uncommitted on improvements1).

# AuditLog review and rework — notes

Branch `improvements1`, working tree. Follows the FieldType / keys work in `2026-10-11-fk-pk-table-notes.md`.

## 1. How it is used

- `BaseObject::insert()/update()/hardDelete()/softDelete()` call the private hook `logAuditEntry()`, which calls `AuditLog::logInsert/logUpdate/logDelete`. `logChange()` writes one row to `audit_log` (`user_id, table_name, record_id, action, changes, created_on`).
- `BaseObject::setActiveUserId()` supplies `user_id` (ASM sets it in `App.php`); `auditLogDisable()` switches one object off (ASM: `Device`, `Person`, `PersonLoginLog`).
- ASM has its own generated `Asm\AuditLog` model of the same table (same seven columns) plus `app/util/audit_log_archive.php`, which moves rows older than two days into `<db>_archive`. The column set is therefore a contract and was kept unchanged.

## 2. Bugs found and fixed

| # | Where | Bug | Fix |
|---|---|---|---|
| 1 | `AuditLog::__construct` | Built `new Table('audit_log')` and threw it away, so the class had no fields: `new AuditLog($id)`, `AuditLog::bOfind()` (as shown in the example) were fatal | Proper model of the table (7 typed fields, PK `id`); `new AuditLog($id)` loads a row |
| 2 | `BaseObject::update()` / `insert()` | Old values were never aligned after a successful write, so `hasChanged()` stayed true: a second `store()` re-sent the same SET clause and wrote a duplicate UPDATE audit entry with identical old/new values (`touch()`, "save twice" flows) | `markFieldsAsPersisted()` after both writes; `hasChanged()` is false until the object is modified again |
| 3 | `BaseObject::softDelete()` | With a `deleted` column: `store()` logged an UPDATE and then a full DELETE snapshot (two rows, the second claiming every column went to null although the row still exists). With `record_status_id`: only an UPDATE, no DELETE | One DELETE entry carrying the status change (`deleted: null→1` or `record_status_id: x→0`) on both paths; returns `store()`'s result |
| 4 | `BaseObject::hardDelete()` | Logged DELETE *before* running the statement; an FK violation (or any failure) left a DELETE entry for a row that still exists | Logged after the statement, only when a row was affected; the object still holds every value for the snapshot |
| 5 | `AuditLog::logChange()` | Every failure swallowed into `error_log()` and `return true`; integration tests ran for months without an `audit_log` table and nobody noticed | Returns false, remembers `getLastError()`, logs through the Db logger (falls back to `error_log`); `setStrict(true)` turns a lost audit row into a `\RuntimeException` |
| 6 | `BaseObject::logAuditEntry()` | Caught `\Exception` only, so a `TypeError`/`Error` inside the audit code aborted the business operation *after* the data was written | Catches `\Throwable` (re-thrown only in strict mode) |
| 7 | `json_encode($changes)` | Returned `false` for invalid UTF-8 (binary columns, Latin-1 leftovers) or unencodable values: the row was written with an empty `changes` (or rejected by `NOT NULL` in strict SQL mode) | `encodeChanges()` with `JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE`; BLOB columns are recorded as `[binary, N bytes]`; streams, DateTime and objects are normalised |
| 8 | `created_on` | Used SQL `NOW()` while the rows' own `created_on/updated_on` use PHP `date()`: with different app/DB time zones the audit entry and the row disagreed | Bound PHP `date('Y-m-d H:i:s')`, same clock as the rows |
| 9 | `logChange()` | Accepted any `$action`; MySQL's `ENUM` then rejected (strict mode) or stored `''` (non-strict) | Validated against `ACTIONS`; unknown action → `fail()` |
| 10 | `user_id` | Only `setActiveUserId()`; apps that never call it (but do have `Person::getLoggedUserId()`, which `created_by` already uses) logged `NULL` | `resolveUserId()`: active user, else `<package>\Person::getLoggedUserId()` when the class and method exist |
| 11 | `logInsert()` | Recorded every nullable column as `null → null` | Columns left null/empty are skipped on INSERT (the key is already in `record_id`) |
| 12 | `migrations/` | MySQL-only DDL (backticks, `ENUM`, `AUTO_INCREMENT`, `ENGINE=InnoDB`); nothing for PostgreSQL | `create_audit_logs_table_postgres.sql` (`SERIAL`, `CHECK (action IN …)`, `JSONB`, `TIMESTAMP`) |
| 13 | `examples/audit_logging_example.php` | Called `setAuditLoggingEnabled(false)` (does not exist), read `created_at` (column is `created_on`), used the broken `bOfind` reader | Rewritten against the real API |

## 3. Optimisations

- The audit `INSERT` is prepared once per connection and reused (`PDOStatement` cached by connection object id + table name, dropped on failure).
- `logUpdate()` returns before touching the database when nothing changed; `update()` itself already returns early in that case.
- `findBy()` builds `AuditLog` objects straight from the fetched rows (`fromRow()`), one query for the whole history instead of one `SELECT` per entry as `bOfind()` does.
- Optional `setMaxValueLength()` / Settings `audit.max_value_length` cuts long text values in the payload (`…[+N chars]`) so a large `TEXT` column cannot overflow the 64 KB `changes` column and lose the entry. Default 0 = unlimited (unchanged behaviour).

## 4. New API (all additive)

AuditLog, static: `setEnabled()/isEnabled()/enable()/disable()` (process-wide switch), `setStrict()/isStrict()`, `setMaxValueLength()/getMaxValueLength()`, `setTableName()/getTableName()`, `getLastError()`, `collectChanges()`, `collectInsertedValues()`, `collectSnapshot()`, `encodeChanges()`, `normalizeValue()`, `recordIdToString()`, `resolveUserId()`, `findForRecord(table, id, limit, offset)`, `findForObject(object)`, `findForUser(userId)`, `findBy(criteria)` (newest first), `countForRecord()`, `fromRow()`. `logDelete()` takes an optional explicit change list.

AuditLog, instance: `getChanges()` (decoded, keys always `field, old_value, new_value` — JSONB reorders keys), `getChangeFor(field)`, `getChangedFieldNames()`, `getIdVal/getUserIdVal/getTableNameVal/getRecordIdVal/getActionVal/getChangesVal/getCreatedOnVal`, `isInsert/isUpdate/isDelete`.

BaseObject: `isAuditLoggingEnabled()`, `logAuditEntry()` is now `protected` with an optional `$changes` argument (overridable per model), `markFieldsAsPersisted()` (protected). `Field::hasType()`.

## 5. Compatibility

- Table columns, action values and the payload shape (`[{field, old_value, new_value}]`) are unchanged; ASM's `Asm\AuditLog` model and `audit_log_archive.php` keep working. `logChange()/logInsert()/logUpdate()/logDelete()` keep their signatures (`logDelete` gained an optional third parameter).
- `logChange()` now returns false when the write fails or auditing is disabled; nothing in LAF or ASM reads the return value.
- Behaviour changes to know about: INSERT entries no longer list null columns; a soft delete produces one DELETE entry (was UPDATE + DELETE); `hasChanged()` is false after a successful `store()` (no caller in LAF/ASM relied on the old value); `created_on` comes from the PHP clock.
- `setActiveUserId(?int)` and the `INT user_id` column stay integer; UUID user keys would need a schema change.
- No PHP 8.1+ syntax; `private logAuditEntry` became `protected` (no subclass in ASM defines that name).

## 6. Not done (worth a follow-up)

- The audit row is not atomic with the data change: without a transaction a crash between the two leaves one without the other. `Db` has no transaction API yet (ERP audit item 5.3).
- `record_id VARCHAR(50)`: a composite key of long varchar parts could exceed it; the entry would then fail (reported via `getLastError()`), not be truncated.
- The MySQL `changes` column is `TEXT` (64 KB); the migration header suggests `MEDIUMTEXT` or `setMaxValueLength()` for very large text columns.

## 7. Verification

| Check | Result |
|---|---|
| `phpunit --testsuite Unit` | 432 tests, 1,359 assertions (14 new in `tests/AuditLogTest.php`) |
| `phpunit --testsuite Integration` MariaDB 12.2 | 106 tests, 334 assertions (10 new audit tests) |
| `phpunit --testsuite Integration` PostgreSQL 18.3 | 106 tests, 334 assertions |
| PHPStan level 5 on touched files | only pre-existing findings plus "new static" and always-true defensive checks |
| ASM `app/lib` grep | no `logAuditEntry`/`markFieldsAsPersisted`/`isAuditLoggingEnabled`/`hasType` definitions to collide with |

The integration schema now creates `audit_log` from the real migration file for each engine, so the migrations themselves are exercised on every run.
