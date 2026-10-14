# Audit log

Every INSERT, UPDATE and DELETE that goes through a model (`store()`, `insert()`, `update()`, `hardDelete()`,
`softDelete()`) writes one row to the `audit_log` table: who did it, which table and record, what kind of change,
and a JSON list of the columns that changed with their old and new values. You do not call anything per write;
you create the table once and tell LAF who the current user is. `Laf\Database\AuditLog` is also a regular model of
that table, so reading a record's history is one method call.

On this page:

- [Creating the table](#creating-the-table)
- [Who made the change](#who-made-the-change)
- [What is logged](#what-is-logged)
- [A real history: insert, update, deletes](#a-real-history-insert-update-deletes)
- [Reading the history](#reading-the-history)
- [Switching it off](#switching-it-off)
- [Failure policy and strict mode](#failure-policy-and-strict-mode)
- [Payload size and value normalisation](#payload-size-and-value-normalisation)
- [Writing entries yourself](#writing-entries-yourself)
- [What is not logged](#what-is-not-logged)
- [Reference](#reference)

## Creating the table

The migrations ship with LAF:

- MySQL / MariaDB: `migrations/create_audit_logs_table.sql`
- PostgreSQL: `migrations/create_audit_logs_table_postgres.sql`

```sql
-- MySQL / MariaDB
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `table_name` VARCHAR(100) NOT NULL,
    `record_id` VARCHAR(50) NOT NULL,
    `action` ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    `changes` TEXT NOT NULL,
    `created_on` DATETIME NOT NULL,
    INDEX `idx_audit_logs_table_record` (`table_name`, `record_id`),
    INDEX `idx_audit_logs_user_id` (`user_id`),
    INDEX `idx_audit_logs_created_on` (`created_on`),
    INDEX `idx_audit_logs_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

```sql
-- PostgreSQL
CREATE TABLE IF NOT EXISTS audit_log (
    id          SERIAL PRIMARY KEY,
    user_id     INTEGER NULL,
    table_name  VARCHAR(100) NOT NULL,
    record_id   VARCHAR(50) NOT NULL,
    action      VARCHAR(10) NOT NULL CHECK (action IN ('INSERT', 'UPDATE', 'DELETE')),
    changes     JSONB NOT NULL,
    created_on  TIMESTAMP NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_audit_logs_table_record ON audit_log (table_name, record_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_user_id ON audit_log (user_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created_on ON audit_log (created_on);
CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_log (action);
```

| Column | Content |
|---|---|
| `user_id` | The user the change is attributed to, `NULL` when unknown. |
| `table_name` | The table that changed. |
| `record_id` | The primary key as a string; a composite key is stored as `"v1,v2"`. |
| `action` | `INSERT`, `UPDATE` or `DELETE`. |
| `changes` | JSON list of `{"field", "old_value", "new_value"}`. |
| `created_on` | Application time (PHP `date()`), the same clock as the rows' `created_on` / `updated_on`. |

The column set is a contract with existing applications: do not rename columns. `changes` is `TEXT` (64 KB) on
MySQL; use `MEDIUMTEXT` or `AuditLog::setMaxValueLength()` when a table has very large text columns. Another table
name is possible with `AuditLog::setTableName('history')` (same columns).

Exclude `audit_log` from code generation; the generator's default `--exclude` list already does
([02-code-generation.md](02-code-generation.md)). Without the table, models still work: every write reports a failed
audit entry through `error_log()` (see [Failure policy](#failure-policy-and-strict-mode)).

## Who made the change

Set the active user once per request, as soon as you know who is logged in:

```php
use Laf\Database\BaseObject;

session_start();
BaseObject::setActiveUserId($_SESSION['user_id'] ?? null);
```

The same id fills the `created_by` / `updated_by` columns of your tables. When no active user is set, LAF looks for
`<package>\Person::getLoggedUserId()` (for `project.package_name = MyApp`: `MyApp\Person`), a convention from the
applications built on LAF. If neither gives a numeric id, `user_id` is `NULL`. `AuditLog::resolveUserId()` and
`BaseObject::resolveActiveUserId()` return what will be used; `BaseObject::getActiveUserId()` returns only the
explicitly set id.

## What is logged

| Write | Action | `changes` |
|---|---|---|
| `insert()` (or `store()` on a new object) | `INSERT` | every non-key column that received a non-empty value, old value `null` |
| `update()` (or `store()` on a loaded object) | `UPDATE` | only the columns whose value differs from the loaded one; **nothing** is written when nothing changed |
| `softDelete()` | `DELETE` | the status change only (`record_status_id` 1 to 0, or `deleted` 0 to 1), plus any unsaved changes stored with it |
| `hardDelete()` | `DELETE` | a snapshot of every column, new value `null` |

`created_on`, `created_by`, `updated_on` and `updated_by` are left out of INSERT and UPDATE payloads: the audit row
carries its own user and time, and these columns change on every write. A hard-delete snapshot keeps them, so the
deleted row can be reconstructed completely.

The entry is written right after the statement succeeded, through the same connection, so inside a
`BaseObject::transaction()` the audit rows commit or roll back together with the data.

## A real history: insert, update, deletes

This script ran against the example database (company and employee from the shared schema):

```php
use Laf\Database\AuditLog;
use Laf\Database\BaseObject;
use MyApp\Company;
use MyApp\Employee;

BaseObject::setActiveUserId(7);

$company = new Company();
$company->setNameVal('ch14 Initech');
$company->store();

$employee = new Employee();
$employee->setCompanyIdVal($company->getIdVal());
$employee->setFirstNameVal('Ada');
$employee->setLastNameVal('Lovelace');
$employee->setEmailVal('ada@ch14.test');
$employee->setSalaryVal('4200.00');
$employee->store();                         // INSERT entry

$employee->setSalaryVal('4500.00');
$employee->setNotesVal('Promoted');
$employee->store();                         // UPDATE entry: salary and notes only
$employee->store();                         // returns true, writes nothing, logs nothing

foreach (AuditLog::findForObject($employee) as $entry) {      // newest first
    printf("#%d %s %s#%s by user %s on %s\n",
        $entry->getIdVal(), $entry->getActionVal(), $entry->getTableNameVal(),
        $entry->getRecordIdVal(), $entry->getUserIdVal() ?? '-', $entry->getCreatedOnVal());
    foreach ($entry->getChanges() as $change) {
        printf("  %-11s %s -> %s\n", $change['field'],
            json_encode($change['old_value']), json_encode($change['new_value']));
    }
}
```

Output:

```text
#71 UPDATE employee#19 by user 7 on 2026-10-14 18:56:31
  salary      "4200.00" -> "4500.00"
  notes       null -> "Promoted"
#70 INSERT employee#19 by user 7 on 2026-10-14 18:56:31
  company_id  null -> 14
  first_name  null -> "Ada"
  last_name   null -> "Lovelace"
  email       null -> "ada@ch14.test"
  salary      null -> "4200.00"
```

The rows as stored in `audit_log`:

```json
{"id":70,"user_id":7,"table_name":"employee","record_id":"19","action":"INSERT","changes":"[{\"field\":\"company_id\",\"old_value\":null,\"new_value\":14},{\"field\":\"first_name\",\"old_value\":null,\"new_value\":\"Ada\"},{\"field\":\"last_name\",\"old_value\":null,\"new_value\":\"Lovelace\"},{\"field\":\"email\",\"old_value\":null,\"new_value\":\"ada@ch14.test\"},{\"field\":\"salary\",\"old_value\":null,\"new_value\":\"4200.00\"}]"}
{"id":71,"user_id":7,"table_name":"employee","record_id":"19","action":"UPDATE","changes":"[{\"field\":\"salary\",\"old_value\":\"4200.00\",\"new_value\":\"4500.00\"},{\"field\":\"notes\",\"old_value\":null,\"new_value\":\"Promoted\"}]"}
```

Note what is missing from the INSERT: `is_active` and `birth_date` were never set, so they were left out of the
INSERT statement (the database default applied) and out of the payload. Decimal values stay strings (`"4200.00"`),
so no precision is lost.

Deleting: the company has a `record_status_id` column, so `softDelete()` is the normal delete for it; the employee
table has none, so it is hard-deleted:

```php
(new Employee($employeeId))->hardDelete();
(new Company($companyId))->softDelete();
```

```text
company  DELETE [{"field":"record_status_id","old_value":1,"new_value":0}]
employee DELETE [{"field":"id","old_value":19,"new_value":null},{"field":"company_id","old_value":14,"new_value":null},
                 {"field":"first_name","old_value":"Ada","new_value":null},{"field":"last_name","old_value":"Lovelace","new_value":null},
                 {"field":"email","old_value":"ada@ch14.test","new_value":null},{"field":"birth_date","old_value":null,"new_value":null},
                 {"field":"salary","old_value":"4500.00","new_value":null},{"field":"is_active","old_value":true,"new_value":null},
                 {"field":"notes","old_value":"also silent","new_value":null},{"field":"created_on","old_value":"2026-10-14 18:56:31","new_value":null},
                 {"field":"created_by","old_value":7,"new_value":null},{"field":"updated_on","old_value":"2026-10-14 18:56:31","new_value":null},
                 {"field":"updated_by","old_value":7,"new_value":null}]
```

A soft delete is one `DELETE` entry carrying the status change, not an `UPDATE` plus a `DELETE`. Soft delete
(`record_status_id = 0`) is LAF's convention for "deleted"; see [03-models.md](03-models.md). (The `notes` value
`"also silent"` comes from the un-audited writes in [Switching it off](#switching-it-off).)

## Reading the history

```php
use Laf\Database\AuditLog;

$history = AuditLog::findForObject($employee);             // the loaded object's history, newest first
$history = AuditLog::findForRecord('employee', 19);        // same, by table and key
$page2   = AuditLog::findForRecord('employee', 19, 20, 20);// limit 20, offset 20 (limit 0 = no limit)
$mine    = AuditLog::findForUser(7, 50);                   // everything user 7 did, newest first
$count   = AuditLog::countForRecord('employee', 19);       // 2
$updates = AuditLog::findBy(['table_name' => 'employee', 'action' => 'UPDATE'], 100);
$entry   = new AuditLog(71);                               // one row by id

$last = AuditLog::findForRecord('employee', 19, 1)[0];
$last->getChangeFor('salary');      // ['field' => 'salary', 'old_value' => '4200.00', 'new_value' => '4500.00']
$last->getChangeFor('email');       // null: not touched by this entry
$last->getChangedFieldNames();      // ['salary', 'notes']
$last->isUpdate();                  // true (also isInsert(), isDelete())
```

`findBy()` accepts only real `audit_log` columns (`InvalidArgumentException` otherwise); a `null` value means
`IS NULL`. Results are ordered `id DESC`. For composite keys pass the key array; it is converted to the same
`"v1,v2"` string that was stored.

`getChanges()` always returns the keys in the order `field`, `old_value`, `new_value` (PostgreSQL `JSONB` stores
object keys in its own order) and an empty list for an unreadable payload.

Showing a history on a record's view page is a small table; escape the values, they are user data:

```php
use Laf\Database\AuditLog;
use Laf\UI\Table\Table;
use Laf\Util\Locale;

$rows = [];
foreach (AuditLog::findForObject($employee, 20) as $entry) {
    foreach ($entry->getChanges() as $change) {
        $rows[] = [
            Locale::formatDateTime($entry->getCreatedOnVal()),
            $entry->getActionVal(),
            $change['field'],
            is_scalar($change['old_value']) ? (string)$change['old_value'] : json_encode($change['old_value']),
            is_scalar($change['new_value']) ? (string)$change['new_value'] : json_encode($change['new_value']),
        ];
    }
}
$table = Table::fromArray($rows, ['When', 'Action', 'Field', 'Old', 'New']);   // values are escaped
```

([09-lists-and-tables.md](09-lists-and-tables.md) covers `Table`.)

## Switching it off

Per object, for writes you do not want in the history (a counter, a cache column):

```php
$employee = new Employee(19);
$employee->auditLogDisable();          // fluent; auditLogEnable() switches it back on
$employee->setNotesVal('silent edit');
$employee->store();                    // stored, not logged
$employee->isAuditLoggingEnabled();    // false
```

For the whole process (bulk imports, migrations, test setup):

```php
use Laf\Database\AuditLog;

AuditLog::disable();                   // or AuditLog::setEnabled(false)
try {
    // import thousands of rows
} finally {
    AuditLog::enable();
}
AuditLog::isEnabled();
```

`isAuditLoggingEnabled()` on a model is true only when both switches are on.

## Failure policy and strict mode

A failed audit write never breaks the business write: the data is already stored when the audit row is attempted.
The failure is reported through the default connection's PSR-3 logger (or `error_log()` when there is none) and
kept in `AuditLog::getLastError()`; `logChange()` returns `false`.

```php
AuditLog::setTableName('audit_log_missing');
AuditLog::logChange('UPDATE', 'employee', 1, []);   // false
AuditLog::getLastError();
// Failed to write audit log for employee#1 (UPDATE): SQLSTATE[42S02]: Base table or view not found:
// 1146 Table 'laf_docs.audit_log_missing' doesn't exist
```

When a lost audit row must stop the request (regulated data), switch strict mode on. A failed write then throws
`RuntimeException` (the original exception as `getPrevious()`); inside a `BaseObject::transaction()` that rolls
back the data write as well:

```php
AuditLog::setStrict(true);
AuditLog::isStrict();   // true
```

`getLastError()` is reset to `null` by the next successful write.

## Payload size and value normalisation

Values are normalised before they are encoded (`AuditLog::normalizeValue()`):

| Value | Stored as |
|---|---|
| `null`, bool, int, float | as is (JSON `null`, `true`, `1`, `1.5`) |
| numeric strings (DECIMAL columns) | strings, so `"4200.00"` keeps its precision |
| `DateTimeInterface` | `Y-m-d H:i:s` string |
| a BLOB column's value | `"[binary, N bytes]"` (or `"[binary stream]"`) |
| arrays | normalised element by element |
| `JsonSerializable` | its JSON; other objects: `__toString()` or `"[object Class]"` |
| long strings | cut to the maximum length, marked with the number of characters removed |

Long text columns make large payloads. Limit each value (0 = unlimited, the default; also Settings
`audit.max_value_length`):

```php
AuditLog::setMaxValueLength(10);
AuditLog::normalizeValue(str_repeat('x', 25));   // "xxxxxxxxxx…[+15 chars]"
AuditLog::getMaxValueLength();                   // 10
```

`encodeChanges()` never fails: invalid UTF-8 is substituted and unencodable values are replaced, so one odd value
cannot lose the whole entry.

## Writing entries yourself

Writes that bypass the models (the `Db::insert()` / `update()` / `delete()` helpers, raw SQL, stored procedures)
are not audited. Log them explicitly when they matter:

```php
use Laf\Database\AuditLog;
use Laf\Database\Db;

$affected = Db::getInstance()->update('employee', ['is_active' => false], ['company_id' => 14]);
AuditLog::logChange(AuditLog::ACTION_UPDATE, 'employee', 'company:14', [
    ['field' => 'is_active', 'old_value' => true, 'new_value' => false],
]);
```

`logChange($action, $tableName, $recordId, $changes = [], $userId = null)` accepts the three action constants
(anything else is a failure: `Unknown audit action "RENAME" for table employee`), a scalar or array record id, and
an explicit user id (default: the resolved active user). `logInsert($object)`, `logUpdate($object)` and
`logDelete($object, $userId, $changes)` build the payload from a model the way the models do;
`collectChanges()`, `collectInsertedValues()` and `collectSnapshot()` return those payloads without writing.

To enrich or redirect the entries of one model (add a tenant id, write to a message queue), override the protected
`logAuditEntry(string $action, ?array $changes = null)` hook in the leaf class.

## What is not logged

- Writes through `Db` helpers or raw SQL (see above).
- Changes made by database triggers, cascades (`ON DELETE CASCADE` on `employee_project`) or other applications.
- `store()` calls that change nothing.
- Writes of objects with `auditLogDisable()`, or while `AuditLog::disable()` is in effect.
- The `audit_log` rows themselves (`AuditLog` disables auditing on its own instances).

## Reference

| Method | What it does | Notes |
|---|---|---|
| `AuditLog::enable()` / `disable()` / `setEnabled(bool)` / `isEnabled()` | Process-wide switch | Default on |
| `AuditLog::setStrict(bool)` / `isStrict()` | Throw on a failed write | Default off |
| `AuditLog::setMaxValueLength(?int)` / `getMaxValueLength()` | Cut long values | Settings `audit.max_value_length`, 0 = unlimited |
| `AuditLog::setTableName($name)` / `getTableName()` | Audit table | Default `audit_log` |
| `AuditLog::getLastError()` | Message of the last failed write | `null` after a success |
| `AuditLog::logChange($action, $table, $recordId, $changes, $userId)` | Write one row | `bool` |
| `AuditLog::logInsert($obj, $userId)` / `logUpdate()` / `logDelete($obj, $userId, $changes)` | Write from a model | `logUpdate()` writes nothing (true) when nothing changed |
| `AuditLog::collectChanges($obj)` / `collectInsertedValues($obj)` / `collectSnapshot($obj)` | Build payloads | |
| `AuditLog::encodeChanges($changes)` / `normalizeValue($value, $field)` | JSON and value rules | |
| `AuditLog::recordIdToString($id)` | `[3, 'b']` gives `"3,b"` | |
| `AuditLog::resolveUserId()` | Active user or `Person::getLoggedUserId()` | |
| `AuditLog::findForRecord($table, $id, $limit = 100, $offset = 0)` | History of a record | newest first |
| `AuditLog::findForObject($obj, $limit, $offset)` | History of a model | |
| `AuditLog::findForUser($userId, $limit, $offset)` | A user's changes | |
| `AuditLog::findBy($criteria, $limit, $offset)` | Any column filter | |
| `AuditLog::countForRecord($table, $id)` | Number of entries | |
| `AuditLog::fromRow($row)` | Entry from a fetched row | no second SELECT |
| `$entry->getChanges()` / `getChangeFor($field)` / `getChangedFieldNames()` | Decoded payload | |
| `$entry->getIdVal()`, `getUserIdVal()`, `getTableNameVal()`, `getRecordIdVal()`, `getActionVal()`, `getChangesVal()`, `getCreatedOnVal()` | Column values | |
| `$entry->isInsert()` / `isUpdate()` / `isDelete()` | Action tests | |
| `BaseObject::setActiveUserId(?int)` / `getActiveUserId()` / `resolveActiveUserId()` | The user | |
| `$model->auditLogDisable()` / `auditLogEnable()` / `isAuditLoggingEnabled()` | Per-object switch | |

Constants: `ACTION_INSERT`, `ACTION_UPDATE`, `ACTION_DELETE`, `ACTIONS`, `SKIPPED_FIELDS`
(`created_on`, `created_by`, `updated_on`, `updated_by`).

`examples/audit_logging_example.php` in the LAF repository walks through the same API as a commented script.

## See also

- [03-models.md](03-models.md): writes, soft delete, hooks, transactions
- [13-database.md](13-database.md): the `Db` helpers that are not audited
- [01-getting-started.md](01-getting-started.md): running the migration in a new project
- [17-security.md](17-security.md): sessions and the active user
