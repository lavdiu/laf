# Models

A model is the PHP class that stands for one table: `MyApp\Employee` for `employee`, `MyApp\Company` for
`company`. Every model extends `Laf\Database\BaseObject`, which gives it the Active Record behaviour: load a row by
its key, read and change values through typed getters and setters, write the row back with `store()`, delete it
hard or soft, and run your own code at fixed points of that life cycle (hooks). This chapter covers what a model
object holds, how it reads and writes, which exceptions it throws, how to customise the leaf class, and the
metadata objects (`Table`, `Field`, `PrimaryKey`, `ForeignKey`, field types) that sit behind every generated class.

On this page:

- [The two classes behind every table](#the-two-classes-behind-every-table)
- [Loading a record](#loading-a-record)
- [Reading and writing values](#reading-and-writing-values)
- [Saving: store(), insert(), update()](#saving-store-insert-update)
- [Deleting: hard and soft](#deleting-hard-and-soft)
- [Object state](#object-state)
- [Metadata columns and the active user](#metadata-columns-and-the-active-user)
- [Foreign keys: navigating to the parent row](#foreign-keys-navigating-to-the-parent-row)
- [Transactions](#transactions)
- [Exceptions](#exceptions)
- [Customising the leaf class: hooks and helpers](#customising-the-leaf-class-hooks-and-helpers)
- [Metadata: Table, Field, PrimaryKey, ForeignKey](#metadata-table-field-primarykey-foreignkey)
- [Field types](#field-types)
- [BaseObject reference](#baseobject-reference)
- [See also](#see-also)

All examples use the shared schema (`company`, `employee`, `project`, `employee_project`, see
[examples/schema.mysql.sql](examples/schema.mysql.sql)) and the generated classes in
[examples/generated/](examples/generated/). The outputs shown were produced by running the snippets against MariaDB.

## The two classes behind every table

The generator writes two classes per table (see [02-code-generation.md](02-code-generation.md)):

```
Laf\Database\BaseObject                 the ORM: load, store, delete, find, hooks, audit
    ↓
MyApp\Base\BaseEmployee                 generated from the schema, rewritten on every run
    ↓
MyApp\Employee                          yours: created once, never overwritten
```

- [`Base/BaseEmployee.php`](examples/generated/MyApp/Base/BaseEmployee.php) builds the `Table` metadata in its
  constructor (one `Field` per column, the primary key, the foreign keys, the many-to-many relations) and declares the
  typed accessors: `setFirstNameVal()`, `getFirstNameVal()`, `getFirstNameFld()`, `getFirstNameFormElement()`, and
  for a foreign key `getCompanyIdObj()`. **Never edit it**: the next generator run replaces it.
- [`Employee.php`](examples/generated/MyApp/Employee.php) is the leaf class. It starts almost empty (`find()`,
  `findOne()` and a commented `getFormLayout()` example) and is where your business rules, hooks and helpers go.

Always use the leaf class (`new Employee()`, `Employee::find()`), never the Base class: the finders return
`static`, so `Employee::query()->get()` gives you `Employee` objects with your customisations.

## Loading a record

```php
use Laf\Exception\RecordNotFoundException;
use MyApp\Employee;

$employee = new Employee(12);           // SELECT * FROM `employee` WHERE `id` = :pk_id LIMIT 1
if (!$employee->isLoaded()) {
    // no such row: the object is empty, not null
}

$employee = Employee::load(12);         // Employee, or null when the row is missing
$employee = Employee::loadOrFail(12);   // Employee, or RecordNotFoundException
```

There are three ways because they answer different questions:

| Call | Missing row gives | Use it when |
|---|---|---|
| `new Employee($id)` | an empty, unloaded object (`isLoaded()` false) | you build a form that serves both "new" and "edit", or you always check `isLoaded()` |
| `Employee::load($id)` | `null` | the miss is a normal case (`if ($e = Employee::load($id))`) |
| `Employee::loadOrFail($id)` | `Laf\Exception\RecordNotFoundException` (code 404) | a page or API where a miss means "404" |

`load()` also returns `null` for `null`, `''` and `[]`. The exception carries the table and the key:

```php
try {
    Employee::loadOrFail(987654);
} catch (RecordNotFoundException $ex) {
    $ex->getMessage();    // 'No employee record with id 987654'
    $ex->getCode();       // 404
    $ex->getTableName();  // 'employee'
    $ex->getRecordId();   // 987654
}
```

Other ways to get loaded objects:

- `$employee->select($id)` loads (another) row into an existing object and returns `true`/`false`. On a miss a
  previously loaded row is unloaded (values cleared), so nothing stale can be stored by accident.
- `$employee->reload()` reads the current row again (database defaults, changes made by triggers or other requests).
- `Employee::hydrate($row)` builds a loaded object from an already fetched associative array, without a query. This
  is what `Query::get()` uses; use it for rows you fetched with raw SQL (see [04-queries.md](04-queries.md#raw-sql-through-db-joins-and-reports)).
- `$employee->loadRow($row)` does the same on an existing object.
- Finders: `find()`, `findOne()`, `query()` (see [04-queries.md](04-queries.md)).

Composite primary keys are addressed with an array: `new OrderLine(['order_id' => 5, 'line_no' => 2])` (or
positional values in key order).

## Reading and writing values

Every column gets four generated methods. For `employee.first_name`:

| Method | Returns | Notes |
|---|---|---|
| `setFirstNameVal($value)` | the object (`static`), so calls chain | normalised through the field type; may throw `InvalidValueException` / `InvalidForeignKeyValue` |
| `getFirstNameVal()` | the value in the type's PHP form | `int`, `bool`, decimal string, `'Y-m-d'`, ... |
| `getFirstNameFld()` | the `Laf\Database\Field\Field` object | labels, rules, old value (see [Field](#field)) |
| `getFirstNameFormElement()` | the form input bound to the field | see [07-form-inputs.md](07-form-inputs.md) |

```php
use MyApp\Employee;

$e = new Employee();
$e->setFirstNameVal('Ana')
  ->setLastNameVal('Hoxha')
  ->setEmailVal('ana@example.com')
  ->setBirthDateVal('1990-04-12')
  ->setSalaryVal(1234.5);

$e->getSalaryVal();      // '1234.50'  (decimal: an exact string, rounded to the column scale)
$e->getIsActiveVal();    // null       (not set yet; the database default applies on insert)
```

Setters normalise the value to the column type's canonical PHP form (see [Field types](#field-types)) and refuse
what cannot be converted:

```php
use Laf\Exception\InvalidValueException;

try {
    $e->setBirthDateVal('2024-02-30');
} catch (InvalidValueException $ex) {
    // Invalid Value of '2024-02-30' for type:Laf\Database\Field\TypeDate and field: employee:birth_date
    //   (Invalid value '2024-02-30' for type date (not a date))
}
```

Some conversions worth knowing (all verified):

| Call | Stored value |
|---|---|
| `setIsActiveVal('yes')` | `true` (`1/0`, `'true'/'false'`, `'on'/'off'`, `'y'/'n'`, `'t'/'f'` are understood) |
| `setIsActiveVal('')` | `false` (an unticked checkbox posts nothing) |
| `setBirthDateVal(new DateTime('2001-02-03 10:00'))` | `'2001-02-03'` |
| `setFirstNameVal('  <b>Ana</b>  ')` | `'<b>Ana</b>'` (trimmed, otherwise verbatim) |
| `setVatNumberVal('')` | `null` (an empty string is "no value" for every type except bool) |

> **Note:** values are stored verbatim. LAF never HTML-escapes at storage time; escaping happens when the value is
> drawn (`Laf\Util\Util::e()`, form inputs, tables). If you echo a value yourself, escape it. See
> [17-security.md](17-security.md).

Generic access by column name, useful in loops and imports:

```php
$company->setFieldValue('phone', '555-0100');        // false when the column does not exist
$company->setFieldValues(['name' => 'Acme', 'phone' => '555', 'unknown' => 'x']);  // unknown columns ignored
$company->getFieldValue('phone');                     // '555'
$company->getField('phone');                          // Field, or null
$company->toArray();                                  // column => value (same as getFieldValuesAsArray())
$company->toArray(['id', 'name']);                    // only these columns
json_encode($company);                                // the column => value map (JsonSerializable)
```

`setFieldValueRaw()` skips the type conversion and the foreign-key check; keep it for values you already know
are canonical (LAF uses it for timestamps and keys).

## Saving: store(), insert(), update()

`store()` is the one call you normally need: it runs `insert()` when the object is new and `update()` otherwise.

```php
use MyApp\Company;

$company = new Company();
$company->setNameVal('Acme Ltd')->setEmailVal('info@acme.example');
$company->isNew();        // true
$company->store();        // true
$company->getIdVal();     // 1   (the auto-increment key is read back)
echo $company->getLastSql();
```

```sql
INSERT INTO `company` (
	  `name`
	, `email`
	, `created_on`
	, `created_by`
)
VALUES (
	  :name
	, :email
	, :created_on
	, :created_by
)
```

What the INSERT contains, and why:

- **Unset optional columns are left out** so the database default applies. `record_status_id` (default 1) and
  `phone` are not in the statement above.
- **Database defaults are not read back.** Right after the insert `getRecordStatusIdVal()` is still `null`; call
  `$company->reload()` when you need the stored defaults (it then returns `1`).
- `created_on` / `created_by` are filled automatically (see [Metadata columns](#metadata-columns-and-the-active-user)).
- Values are bound through the field types (`PDO::PARAM_INT` for integers and booleans, strings otherwise).
- On PostgreSQL the key comes back through `INSERT ... RETURNING`; on MySQL through `lastInsertId()`.

An update only writes what changed:

```php
$company->setPhoneVal(' +355 4 123 456 ');
$company->getChangedValues();
// ['phone' => ['old' => null, 'new' => '+355 4 123 456']]
$company->store();          // true
echo $company->getLastSql();
$company->getAffectedRows(); // 1
$company->store();          // true, but no statement at all: nothing changed
```

```sql
UPDATE `company`
SET
	  `phone` = :phone
	, `updated_on` = :updated_on
	, `updated_by` = :updated_by
WHERE
	`id` = :pk_id
```

Rules of the write path:

| Situation | Result |
|---|---|
| `store()` on a new object | `insert()`; `true` |
| `store()` on a loaded object with changes | `update()` of the changed columns plus `updated_on/by`; `true` |
| `store()` on a loaded object without changes | `true`, no SQL, no audit entry, `afterUpdate()` not called |
| `update()` on an object that was never loaded | `false` |
| a required column is empty | `MissingFieldValueException` before any SQL |
| a single-column unique value already exists in another row | `UniqueFieldDuplicateValueException` before any SQL |
| validation on store is on and a rule fails | `ValidationException` before any SQL (see [05-validation.md](05-validation.md)) |
| the database refuses the statement | `DatabaseException` |

"New" is decided by `isNew()`: the key is empty, or a non-auto-increment key was assigned but never loaded. A model
whose key is a `TypeUuid` column gets a v4 UUID generated on insert.

After a successful write every field's old value equals its current value, so `isDirty()` is `false` and a second
`store()` is a no-op. Each write is recorded in the audit log when the `audit_log` table exists (see
[14-audit-log.md](14-audit-log.md)).

`touch()` sets `updated_on` to now and stores (only when the table has that column).

## Deleting: hard and soft

```php
$employee->hardDelete();    // DELETE FROM `employee` WHERE `id` = :pk_id   -> true
$employee->hardDelete();    // false: the object is no longer loaded
```

`hardDelete()` returns `false` instead of deleting when the object is not loaded, has an empty key, or when the
row was already gone (deleted by someone else since it was loaded; `afterDelete()` is then not called). It does not
open a transaction, so it can run inside yours. A foreign key that refuses the delete surfaces as a
`DatabaseException`:

```php
use Laf\Exception\DatabaseException;

try {
    $company->hardDelete();          // the company still has employees
} catch (DatabaseException $ex) {
    $ex->isForeignKeyViolation();    // true
    $ex->getSqlState();              // '23000'
    $ex->getDriverCode();            // 1451
    $ex->getSql();                   // "DELETE FROM `company`\nWHERE `id` = :pk_id"
    $ex->getBindings();              // [':pk_id' => 1]
}
```

`softDelete()` keeps the row and marks it deleted: `record_status_id = 0` when the table has that column, else
`deleted = 1`.

```php
$company->canSoftDelete();         // true: company has record_status_id
$company->softDelete();            // true
$company->getRecordStatusIdVal();  // 0
Company::load($company->getIdVal()) !== null;   // true: the row is still there
```

> **Note:** `record_status_id = 0` meaning "deleted" is the LAF convention, not an accident. Soft delete only flags
> the row. The finders do **not** hide flagged rows: filter them yourself where it matters, e.g.
> `Company::query()->where('record_status_id', '<>', 0)`, or with a helper in the leaf class.

Details of `softDelete()`: it returns `false` when the table has neither column or the row does not exist; a clean
object is reloaded first, while unsaved changes on a loaded object are stored together with the flag; the audit log
gets one DELETE entry carrying the status change. Hooks run as `beforeDelete('soft')`, then the update hooks,
then `afterDelete('soft')`.

To delete many rows at once without loading them, use `Query::delete()` (see [04-queries.md](04-queries.md#deleting-rows)).

## Object state

| Method | Meaning |
|---|---|
| `isLoaded()` / `isRecordSelected()` | a row was loaded from, or written to, the database |
| `isNew()` | `store()` would insert |
| `isDirty()` | at least one field differs from its persisted value |
| `getChangedFields()` | column => `Field` of the changed fields |
| `getChangedValues()` | column => `['old' => ..., 'new' => ...]` |
| `recordExists()` | the primary key holds a value (not a database check) |
| `getRecordId()` | the key the object was loaded with or got at insert (array for composite keys) |
| `getPrimaryKeyValue()` | the key the fields currently hold |
| `getAffectedRows()` | rows touched by the last insert/update/delete |
| `getLastSql()` | the last statement this object ran |
| `reset()` | clear every value and the loaded flag (the table metadata stays) |

```php
$company = new Company();
$company->isNew();                              // true
$company->isLoaded();                           // false
$company->setNameVal('Acme')->setEmailVal('info@acme.example');
$company->isDirty();                            // true
array_keys($company->getChangedFields());       // ['name', 'email']
$company->store();
$company->isNew();                              // false
$company->isDirty();                            // false
```

Change detection compares through the type, so setting `'1234.5'` on a salary loaded as `'1234.50'` is not a
change.

Cloning gives a new, unsaved row with the same values and an empty key; the clone has its own copy of the
metadata, so changing it never touches the original:

```php
$copy = clone $employee;
$copy->isNew();          // true
$copy->getIdVal();       // null
$copy->getFirstNameVal(); // 'Ana'
```

## Metadata columns and the active user

Columns with these names are maintained by `BaseObject` when the table has them:

| Column | Constant | Filled |
|---|---|---|
| `created_on` | `BaseObject::FIELD_CREATED_ON` | on insert, when empty: `date('Y-m-d H:i:s')` |
| `created_by` | `BaseObject::FIELD_CREATED_BY` | on insert, when empty: the active user id |
| `updated_on` | `BaseObject::FIELD_UPDATED_ON` | on every update that writes something |
| `updated_by` | `BaseObject::FIELD_UPDATED_BY` | on every update that writes something: the active user id |
| `record_status_id` | `BaseObject::FIELD_RECORD_STATUS_ID` | set to `0` by `softDelete()` |
| `deleted` | `BaseObject::FIELD_DELETED` | set to `1` by `softDelete()` when there is no `record_status_id` |

The active user comes from `BaseObject::setActiveUserId($id)`, set once per request after login:

```php
use Laf\Database\BaseObject;

BaseObject::setActiveUserId((int)$_SESSION['user_id']);
BaseObject::getActiveUserId();      // the id, or null
BaseObject::resolveActiveUserId();  // the id, else <package>\Person::getLoggedUserId() when that class exists, else null
```

With no user, `created_by` / `updated_by` are left untouched. The same user is written to the audit log.

## Foreign keys: navigating to the parent row

For each foreign key column the Base class has a `get...Obj()` getter that returns the referenced model:

```php
$employee = Employee::loadOrFail(1);
$company  = $employee->getCompanyIdObj();    // MyApp\Company
echo $company->getNameVal();                 // 'Acme Ltd'

$employee->getCompanyIdObj() === $employee->getCompanyIdObj();  // true: loaded once per key value
$employee->getCompanyIdObj(true);            // reload it
```

The object is cached per key value, so calling the getter in a loop runs one SELECT per employee, not one per call.
Change `company_id` and the next call loads the new company. An empty key gives a new, empty `Company`
(`isLoaded()` false), never `null`.

Setting a foreign key checks that the referenced row exists:

```php
use Laf\Exception\InvalidForeignKeyValue;

try {
    $employee->setCompanyIdVal(999999);
} catch (InvalidForeignKeyValue $ex) {
    $ex->getMessage();   // 'Foreign key not found company_id'
}
```

Positive checks are cached for the request; `null` and `''` are always accepted (a nullable foreign key).

To list the children of a row, query the child table: `Employee::find(['company_id' => $company->getIdVal()])`, or
`Employee::query()->where('company_id', $company)` (a model object as value means its key). Many-to-many links
(`$employee->manyToMany('employee_project')`) are covered in [08-relations.md](08-relations.md).

## Transactions

```php
use Laf\Database\BaseObject;
use MyApp\Company;
use MyApp\Employee;

$employeeId = BaseObject::transaction(function () {
    $company = (new Company())->setNameVal('Globex');
    $company->store();

    $employee = new Employee();
    $employee->setCompanyIdVal($company->getIdVal())
        ->setFirstNameVal('Dren')->setLastNameVal('Gashi')->setEmailVal('dren@globex.example');
    $employee->store();
    return $employee->getIdVal();       // the callback's return value is returned
});
```

The callback runs inside one database transaction: committed when it returns, rolled back and rethrown when it
throws (verified: a project stored inside a callback that then throws is not in the table afterwards). Nested
calls run in a savepoint, so an inner failure that the outer code catches undoes only the inner work. The callback
receives the `Db` instance as its argument. See [13-database.md](13-database.md) for `Db::transaction()`.

## Exceptions

| Exception | Thrown by | Carries |
|---|---|---|
| `Laf\Exception\RecordNotFoundException` (`RuntimeException`, code 404) | `loadOrFail()` | `getTableName()`, `getRecordId()` |
| `Laf\Exception\MissingFieldValueException` | `store()` / `insert()` / `update()` when a required column is empty | `getFieldNames()` |
| `Laf\Exception\UniqueFieldDuplicateValueException` | `store()` / `insert()` / `update()` when a single-column unique value exists in another row | `getFieldNames()` |
| `Laf\Exception\ValidationException` | `store()` with validation on store | `getErrors()`, `getMessages()`, `getFieldNames()`, `getTableName()` (see [05-validation.md](05-validation.md)) |
| `Laf\Exception\DatabaseException` | any statement the ORM runs (load, write, delete, `Query`) | `getSql()`, `getBindings()`, `getSqlState()`, `getDriverCode()`, `isUniqueViolation()`, `isForeignKeyViolation()`, `getPrevious()` = the `PDOException` |
| `Laf\Exception\InvalidValueException` | setters, `Field::setValue()` | message names the table, column and reason |
| `Laf\Exception\InvalidForeignKeyValue` | foreign-key setters | message names the column |

Messages from real runs:

```php
$e = new Employee();
$e->setFirstNameVal('NoEmail');
$e->store();
// MissingFieldValueException:
//   'Field last_name is required. Value provided is ; Field email is required. Value provided is '
//   getFieldNames() === ['last_name', 'email']

$dup = new Employee();
$dup->setFirstNameVal('Dup')->setLastNameVal('Models')->setEmailVal('ana.models@example.com');  // already used
$dup->store();
// UniqueFieldDuplicateValueException:
//   "Field email must be unique: value 'ana.models@example.com' already exists in employee"
//   getFieldNames() === ['email']
```

The required and unique checks run in PHP before the statement, which gives clear messages; the database
constraints stay the real guarantee. Two requests inserting the same e-mail at the same moment can both pass the
PHP check, and the second then fails with a `DatabaseException` whose `isUniqueViolation()` is `true`. Catch both
when it matters:

```php
use Laf\Exception\DatabaseException;
use Laf\Exception\UniqueFieldDuplicateValueException;

try {
    $employee->store();
} catch (UniqueFieldDuplicateValueException $ex) {
    $error = 'This e-mail is already in use';
} catch (DatabaseException $ex) {
    if (!$ex->isUniqueViolation()) {
        throw $ex;
    }
    $error = 'This e-mail is already in use';
}
```

`DatabaseException` extends `\Exception`, so older `catch (\Exception $e)` blocks keep working. Forms turn
missing/unique/validation failures into field errors on their own (see [06-forms.md](06-forms.md)).

## Customising the leaf class: hooks and helpers

The leaf class is plain PHP that survives regeneration. Typical additions: hooks, field rules, a display field,
query helpers, `validateRecord()` (see [05-validation.md](05-validation.md)) and `getFormLayout()` /
`configureForm()` (see [06-forms.md](06-forms.md)).

### Hooks

Override the protected hooks instead of `insert()` / `update()`. Throwing from a `before*` hook aborts the write.

| Hook | Runs |
|---|---|
| `beforeInsert(): void` | before the INSERT is built, before the required/unique checks (so it may fill required values) |
| `afterInsert(): void` | after the row exists; the key is known |
| `beforeUpdate(): void` | before the change set is computed; runs even when nothing changed |
| `afterUpdate(): void` | after the UPDATE ran; not called when nothing changed |
| `beforeDelete(string $mode): void` | before a hard or soft delete; `$mode` is `self::DELETE_HARD` (`'hard'`) or `self::DELETE_SOFT` (`'soft'`) |
| `afterDelete(string $mode): void` | after the delete |
| `afterLoad(): void` | after `select()`, `reload()`, `hydrate()`, `loadRow()` (also for every row a `Query` returns) |

```php
<?php

namespace MyApp;

class Company extends Base\BaseCompany
{
    public function __construct($id = null)
    {
        parent::__construct($id);
    }

    protected function beforeInsert(): void
    {
        // normalise the VAT number: 'al k 123 45' is stored as 'ALK12345'
        if ($this->getVatNumberVal() !== null) {
            $this->setVatNumberVal(strtoupper(str_replace(' ', '', $this->getVatNumberVal())));
        }
    }

    protected function beforeDelete(string $mode): void
    {
        if ($mode === self::DELETE_HARD && Employee::query()->where('company_id', $this->getIdVal())->exists()) {
            throw new \RuntimeException('The company still has employees');
        }
    }

    /** Rows that are not soft-deleted */
    public static function active(): \Laf\Database\Query
    {
        return static::query()->where('record_status_id', '<>', 0);
    }

    // the generated findOne() / find() stay below
}
```

A company stored, stored again unchanged, changed, soft-deleted and hard-deleted calls the hooks in this order
(traced in a test run): `beforeInsert`, `afterInsert`, `beforeUpdate` (nothing changed, no `afterUpdate`),
`beforeUpdate`, `afterUpdate`, `afterLoad` (soft delete reloads a clean object), `beforeDelete(soft)`,
`beforeUpdate`, `afterUpdate`, `afterDelete(soft)`, `beforeDelete(hard)`, `afterDelete(hard)`.

### Field settings that the generator does not know

Adjust fields in the leaf constructor, after `parent::__construct($id)`:

```php
class Employee extends Base\BaseEmployee
{
    public function __construct($id = null)
    {
        parent::__construct($id);
        $this->getTable()->setDisplayField($this->getLastNameFld());   // what a Select shows for an employee
        $this->getSalaryFld()->setMinValue('0.01');                     // a validation rule, see 05-validation.md
    }
}
```

> **Note:** the display field is the label other tables show for this row (Select options, autocomplete,
> `Field::getReferencedValue()`). Without an explicit one it is the `name`, `label` or `description` column, else
> the **second** column of the table. For `company` that is `name`. For `employee` it would be `company_id`, which
> is rarely what you want. The example schema avoids that with the column comment `{"fields":{"setDisplayField":true}}`
> on `employee.email`, so `BaseEmployee` already calls `setDisplayField()`. Setting it in the leaf class, as above,
> works as well.

Prefer column comments (see [02-code-generation.md](02-code-generation.md)) for labels, hints and required flags:
they land in the Base class and survive regeneration without code.

## Metadata: Table, Field, PrimaryKey, ForeignKey

Every model object owns a `Laf\Database\Table` built by the generated constructor. You rarely construct these
yourself, but reading them is how generic code (forms, grids, exports) works.

```php
$table = (new Employee())->getTable();
$table->getName();                                   // 'employee'
$table->getFieldCount();                             // 13
$table->getPrimaryKey()->getFieldNames();            // ['id']
array_keys($table->getForeignKeys());                // ['company_id']
array_map(fn ($f) => $f->getName(), $table->getUniqueFields());   // ['email']
array_keys($table->getManyToManyDefinitions());      // ['employee_project']

$fk = $table->getForeignKey('company_id');
(string)$fk;                                         // 'employee.company_id -> company.id'
$fk->getReferencedClassName();                       // '\MyApp\Company'

$salary = (new Employee())->getSalaryFld();
$salary->getLabel();                                 // 'Salary'
get_class($salary->getType());                       // 'Laf\Database\Field\TypeDecimal'
$salary->isRequired();                               // false
```

Each object is copied deeply when a model is cloned, so two model objects never share fields.

### Table

| Method | What it does |
|---|---|
| `__construct(?string $name = null, ?array $fields = [], ?PrimaryKey $primaryKey = null, ?array $foreignKey = [], ?Field $displayField = null)` | build a table (generated code passes the name only) |
| `getName()` / `setName()` | table name |
| `getNameAsClassname()` | `'employee_project'` → `'EmployeeProject'` |
| `getNameRot13()` | the plain table name (kept for compatibility; forms use it as id and marker) |
| `addField(Field)` / `setField(Field)` | attach a field (sets the field's table) |
| `getField($name)`, `hasField($name)`, `getFields()`, `getFieldCount()` | field access |
| `setPrimaryKey(PrimaryKey)`, `getPrimaryKey()`, `getPrimaryKeyCount()`, `isPrimaryKey($name)` | primary key |
| `addForeignKey(ForeignKey)`, `getForeignKey($column)`, `isForeignKey($column)`, `getForeignKeys()` | foreign keys, keyed by column |
| `addManyToMany($junction, $definition)`, `removeManyToMany()`, `hasManyToMany()`, `getManyToManyDefinition()`, `getManyToManyDefinitions()` | linker-table relations (see [08-relations.md](08-relations.md)) |
| `setDisplayField(?Field)`, `getDisplayField()` | the label column (fallback rules above) |
| `addUniqueField(Field)`, `setUniqueFields()`, `getUniqueFields()`, `hasUniqueFields()` | single-column unique constraints checked before writes |
| `__toString()` | the name |

### Field

`Laf\Database\Field\Field` is one column: its type, constraints, labels, current value, persisted ("old") value and
the form element bound to it. The model's setters call `setValue()`; loading calls `loadValueFromDb()`.

| Group | Methods |
|---|---|
| Identity | `getName()`, `setName()`, `getNameRot13()` (the form input name), `hasTable()`, `getTable()`, `setTable()`, `getTableName()`, `getClassName()` |
| Value | `getValue()`, `setValue()` (normalised, may throw `InvalidValueException`), `setValueRaw()`, `setValueHTML()`, `loadValueFromDb()` (sets value and old value, never throws), `getOldValue()`, `setOldValue()`, `hasChanged()`, `__toString()` (`''` for null, `'1'/'0'` for bools), `getValueNl2Br()` (escaped, with `<br />`), `getValueForDbInsert()`, `sanitize()`, `getLength()` |
| Timestamps | `getLoadedOnTs()`, `setLoadedOnTs()`, `getUpdatedOnTs()`, `setUpdatedOnTs()` |
| Type | `hasType()`, `getType()`, `setType(FieldType)` |
| Constraints | `isRequired()` / `setRequired()`, `isUnique()` / `setUnique()`, `isAutoIncrement()` / `setAutoIncrement()`, `getMaxLength()` / `setMaxLength()`, `getMinLength()` / `setMinLength()`, `getMinValue()` / `setMinValue()`, `getMaxValue()` / `setMaxValue()`, `getIncrementStep()` / `setIncrementStep()` |
| Validation | `validate()`, `isValidValue()`, `setPattern()`, `getPattern()`, `getPatternMessage()`, `addValidator()`, `clearValidators()`, `getValidators()`, `getInvalidValueErrorMessage()` / `setInvalidValueErrorMessage()` (see [05-validation.md](05-validation.md)) |
| Keys | `getPrimaryKey()`, `isPrimaryKey()`, `isForeignKey()`, `getReferencedValue()` (for a foreign key: the referenced row's display value), `isDocumentField()` (foreign key to `document`) |
| Labels | `getLabel()` / `setLabel()`, `getPlaceholder()` / `setPlaceholder()`, `getHint()` / `setHint()`, `getAttributes()`, `getAttribute()`, `setAttribute()` (HTML attributes copied onto the input) |
| Forms | `getFormElement()`, `hasFormElement()`, `clearFormElementCache()`, `setFormElementClass()` / `getFormElementClass()`, `setFormOptions()`, `getFormOptions()`, `getFormOption()`, `setTableDisplayField()` (make this the table's display field), `getDbSelectionCriteria()`, `setDbSelectionCriteria()`, `addDbSelectionCriteria()`, `hasDbSelectionCriteria()` (narrow the rows a foreign-key select offers) |
| Output | `allowHtml()` / `setAllowHtml(true)`: draw the value as raw HTML instead of escaping it (only for trusted content, see [17-security.md](17-security.md)) |

Getters of unset labels, hints, placeholders and attributes return `null`.

### PrimaryKey

| Method | What it does |
|---|---|
| `__construct(?array $fields = [], $table = null)` | |
| `addField(Field)`, `getFields()`, `getFirstField()`, `getFieldNames()`, `getFieldCount()`, `hasField($name)` | key columns |
| `isComposite()` / `hasMoreThanOneField()` | more than one column |
| `isAutoIncrement()` | the first column is auto-increment / serial |
| `getValues()`, `getOldValues()`, `hasValue()`, `setValues($values)` | key values (`setValues()` takes a scalar, a name => value array or positional values) |
| `getWhereClause($prefix = 'pk_', $engine = null)` | `` `id` = :pk_id `` with engine quoting |
| `getBindings($prefix = 'pk_', $useOldValues = true)` | the bindings for that clause (old values address the row as loaded) |
| `getTable()`, `setTable()` | |

### ForeignKey

| Method | What it does |
|---|---|
| `__construct(?string $keyName = null, ?Table $table = null, ?Field $field = null, ?string $referencingTable = null, ?string $referencingField = null)` | |
| `getKeyName()` / `setKeyName()`, `getTable()` / `setTable()`, `getField()` / `setField()` | the constraint and its column |
| `getReferencingTable()` / `setReferencingTable()`, `getReferencingField()` / `setReferencingField()` | the referenced table and column (historic names) |
| `getReferencedTable()` / `setReferencedTable()`, `getReferencedField()` / `setReferencedField()` | the same, clearer names |
| `getOnDelete()` / `setOnDelete()`, `getOnUpdate()` / `setOnUpdate()`, `cascadesOnDelete()` | referential actions |
| `isNullable()` | the column is not required |
| `getReferencedClassName()`, `referencedClassExists()` | `'\MyApp\Company'` from `project.package_name` |
| `getReferencedObject($id = null)` | a new referenced model loaded with `$id` (default: the column's value); `null` without a model class |
| `getReferencedTableMeta()`, `resolveReferencedFieldName()`, `getReferencedFieldType()` | metadata of the referenced side |
| `isValidValue($id)` | a row with this key exists (empty is valid); positive answers cached per request |
| `ForeignKey::clearValidationCache(?string $table = null)` | forget cached checks (deletes call it) |
| `__toString()` | `'employee.company_id -> company.id'` |

## Field types

Every field has a `Laf\Database\Field\FieldType` that converts values (`normalize()` for input, `fromDb()` for
loaded values, `toDb()` for bindings), compares them (`equals()`, used by change detection) and validates them. The
generator picks the type from the column (`FieldTypeFactory::resolve()`):

| Class | Columns | Canonical PHP value | Notes |
|---|---|---|---|
| `TypeInteger($size = 'int', $unsigned = false)` | int, smallint, mediumint, bigint, year, serial, tinyint(n>1) | `int` (digit string beyond `PHP_INT_MAX`) | `validate()` checks the range of the size; `setMinValue()` / `setMaxValue()` |
| `TypeDecimal($precision, $scale)` | decimal, numeric, money | exact string rounded to the scale: `'1234.50'` | digits before the point checked; min/max bounds compared exactly |
| `TypeFloat` | float, double, real | `float` | approximate, use decimal for money; min/max |
| `TypeBool` | bool, boolean, tinyint(1), bit(1) | `bool` | `''` is `false`; bound as 0/1 |
| `TypeVarchar` | varchar, character varying, citext, inet, ... | trimmed string, verbatim | max length checked |
| `TypeChar` | char, character | as varchar | |
| `TypeText` | text, tinytext, mediumtext, longtext | as varchar | drawn as a textarea |
| `TypeDate` | date | `'Y-m-d'` | accepts ISO, datetimes, `DateTimeInterface` and the locale format |
| `TypeDateTime($withTimezone = false)` | datetime, timestamp, timestamptz | `'Y-m-d H:i:s'` | |
| `TypeTime` | time, timetz | `'H:i:s'` | accepts `H:i`, AM/PM, locale format |
| `TypeJson` | json, jsonb | the JSON text | arrays/objects are encoded for you |
| `TypeBlob` | blob, binary, bytea | bytes | bound as `PARAM_LOB` |
| `TypeEnum($options, $multiple = false)` | enum, set, PostgreSQL enums | the declared label | case corrected; a set is the comma list |
| `TypeUuid` | uuid (or char(36) with `{"fields":{"setType":"uuid"}}`) | lower-case hyphenated | a key of this type gets a v4 UUID on insert |

For every type except `TypeBool`, `null` and an empty (or whitespace-only) string mean "no value" (`null`).
The type also chooses the default form input (see [07-form-inputs.md](07-form-inputs.md)).

## BaseObject reference

Every public method, grouped. Static methods are marked.

| Method | What it does | Notes |
|---|---|---|
| `__construct($id = null)` | generated constructors bind the table and load the row when `$id` is not empty | |
| `static load($id)` | the object, or `null` | |
| `static loadOrFail($id)` | the object, or `RecordNotFoundException` | |
| `static hydrate(array $row)` | loaded object from a fetched row, no query | |
| `select($id)` | load a row into this object; `bool` | a miss unloads |
| `reload()` | select the current record id again | clears cached form elements |
| `loadRow(array $row)` | take the values of a fetched row | calls `afterLoad()` |
| `reset()` | clear values and state | |
| `static query()` / `static getQueryBuilder()` | a `Query` on the table returning this class | [04-queries.md](04-queries.md) |
| `static bOfind($criteria, $orderBy, $limit, $offset)`, `static bOfindOne($criteria)`, `static findAsArray($criteria)` | criteria finders; the leaf's `find()` / `findOne()` call the first two | [04-queries.md](04-queries.md) |
| `store()` | insert or update | |
| `insert()`, `update()` | the two halves of `store()` | prefer `store()` and the hooks |
| `hardDelete()` | DELETE the loaded row; `bool` | |
| `softDelete()`, `canSoftDelete()` | flag the row deleted | |
| `touch()` | `updated_on` = now, store | |
| `static transaction(callable $fn)` | run `$fn` in a transaction | |
| `validate()` | every rule: column => messages | [05-validation.md](05-validation.md) |
| `recordErrors()` | `validateRecord()` normalised to column => messages | |
| `setValidateOnStore(?bool)`, `getValidateOnStore()`, `isValidateOnStore()` | validation inside `store()` | |
| `getTable()`, `hasTable()`, `setTable()` | metadata | |
| `getField($name)` | `Field` or `null` | |
| `setFieldValue($name, $value)`, `setFieldValues(array)`, `setFieldValueRaw()`, `setFieldValueHTML()` | set by column name | |
| `getFieldValue($name)`, `getFieldValuesAsArray($fields = [])`, `getAllFieldsAsKVArrays()`, `toArray($fields = [])`, `jsonSerialize()` | read by column name | |
| `isDirty()`, `getChangedFields()`, `getChangedValues()` | change tracking | |
| `isNew()`, `isLoaded()`, `isRecordSelected()`, `setRecordSelected()`, `recordExists()` | state | |
| `getRecordId()`, `setRecordId()`, `getPrimaryKeyValue()` | key | |
| `getAffectedRows()`, `getLastSql()` | last statement | |
| `manyToMany($junction)` | the `ManyToMany` relation through a linker table | [08-relations.md](08-relations.md) |
| `getForm($drawMode = null, $layout = null)`, `getFormLayout()`, `getAllFieldsAsFormElements()` | forms | [06-forms.md](06-forms.md) |
| `getListAllSimpleTableObject()` | legacy `SimpleTable` listing (deprecated grid) | use [09-lists-and-tables.md](09-lists-and-tables.md) or [10-grids.md](10-grids.md) |
| `static getIcon()` | icon class of the model | generated classes may override |
| `static setActiveUserId(?int)`, `static getActiveUserId()`, `static resolveActiveUserId()` | the user writes are attributed to | |
| `auditLogDisable()`, `auditLogEnable()`, `isAuditLoggingEnabled()` | per-object audit switch | [14-audit-log.md](14-audit-log.md) |
| `getLogger()`, `setLogger(?LoggerInterface)`, `addLoggerDebug()` ... `addLoggerEmergency()` | PSR-3 logging of the object's statements | |
| `static populateTableObjectList()` | create/fill the `object_list` table with every table name | utility for apps that use it |

Generated Base classes add per column `set...Val()`, `get...Val()`, `get...Fld()`, `get...FormElement()`, per foreign
key `get...Obj(bool $fresh = false)`, and `listAllArray()` / `listAllObjects()` (every row ordered by key). Generated
leaf classes add `find()` and `findOne()`.

Protected extension points: the hooks above, `validateRecord()`, `configureForm(Form $form)`,
`assertValidForStore(bool $inserting)`, `logAuditEntry()`, `relatedObject()`, `markFieldsAsPersisted()`,
`returnLeafClass()`.

## See also

- [02-code-generation.md](02-code-generation.md) — how the Base and leaf classes are generated, column comments
- [04-queries.md](04-queries.md) — finders, the `Query` builder, pagination
- [05-validation.md](05-validation.md) — field rules, `validateRecord()`, validation on store
- [06-forms.md](06-forms.md) — forms built from a model
- [08-relations.md](08-relations.md) — foreign keys in forms, many-to-many
- [13-database.md](13-database.md) — `Db`, raw SQL, transactions
- [14-audit-log.md](14-audit-log.md) — what every write records
- [17-security.md](17-security.md) — escaping and SQL safety
