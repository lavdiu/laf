# Queries

LAF reads rows in three ways. The criteria finders `find()`, `findOne()` and `findAsArray()` cover "rows where
these columns equal these values". The `Laf\Database\Query` builder, returned by `Model::query()`, covers
everything else on a single table: operators, `OR` groups, text search, aggregates, pagination, streaming and
batch processing. For joins, `GROUP BY` and reports you write SQL and run it through `Laf\Database\Db`, then turn
rows back into models with `hydrate()` when you need them. In every case column names are checked against the
model and values are bound as parameters, so a criterion may come straight from user input.

On this page:

- [Criteria finders: find(), findOne(), findAsArray()](#criteria-finders-find-findone-findasarray)
- [The Query builder](#the-query-builder)
- [Conditions](#conditions)
- [AND, OR and groups](#and-or-and-groups)
- [Conditional pieces: when()](#conditional-pieces-when)
- [Text search: whereContains(), whereStartsWith(), whereEndsWith()](#text-search-wherecontains-wherestartswith-whereendswith)
- [Columns, order, limit](#columns-order-limit)
- [Getting results](#getting-results)
- [Aggregates and pairs](#aggregates-and-pairs)
- [Pagination](#pagination)
- [Large result sets: cursor() and chunk()](#large-result-sets-cursor-and-chunk)
- [Deleting rows](#deleting-rows)
- [Inspecting the SQL](#inspecting-the-sql)
- [Raw SQL through Db: joins and reports](#raw-sql-through-db-joins-and-reports)
- [Query reference](#query-reference)
- [See also](#see-also)

The examples run against the shared schema (see [03-models.md](03-models.md)) with five employees in two
companies: Ana Hoxha and Besa Krasniqi at Acme, Dren Gashi and Elira Berisha at Globex, and Fatos Morina without a
company. Outputs are from real runs on MariaDB.

## Criteria finders: find(), findOne(), findAsArray()

The generated leaf class has two static finders (see [Employee.php](examples/generated/MyApp/Employee.php)):

```php
public static function find(array $keyValuePairs = [], array $orderBy = [], ?int $limit = null, int $offset = 0): array
public static function findOne(array $keyValuePairs): ?Employee
```

They call `BaseObject::bOfind()` / `bOfindOne()`. Criteria are column => value, joined with `AND`:

```php
use MyApp\Employee;

$acmeStaff = Employee::find(['company_id' => $acmeId], ['last_name' => 'ASC']);
// [Employee Ana Hoxha, Employee Besa Krasniqi]

$dren = Employee::findOne(['email' => 'dren@globex.example']);    // Employee, or null
$noCompany = Employee::find(['company_id' => null]);               // company_id IS NULL
$some = Employee::find(['id' => [3, 5, 8]]);                       // id IN (...)
$page = Employee::find([], ['first_name'], 2, 1);                  // ORDER BY first_name LIMIT 2 OFFSET 1
$everyone = Employee::find();                                      // every row
```

| Value | SQL |
|---|---|
| scalar | `column = :v` |
| `null` | `column IS NULL` |
| array | `column IN (:v0, :v1, ...)` (an empty array matches nothing) |
| a model object | `column = ` its primary key |

`$orderBy` takes column => `'ASC'|'DESC'` or a plain list of columns (ascending).

`findAsArray($criteria)` returns plain associative arrays instead of objects:

```php
Employee::findAsArray(['email' => 'besa@acme.example']);
// [['id' => 11, 'company_id' => 6, 'first_name' => 'Besa', 'last_name' => 'Krasniqi',
//   'email' => 'besa@acme.example', 'birth_date' => '1985-11-02', 'salary' => '1850.50',
//   'is_active' => 1, 'notes' => null, 'created_on' => '2026-10-14 18:55:20', 'created_by' => 1, ...]]
```

> **Note:** arrays carry the driver's values (`'is_active' => 1`), objects carry the type's values
> (`getIsActiveVal()` is `true`). Use objects when you need the converted values or the behaviour of the model.

> **Note:** a criteria key that is not a column of the table makes `find()` return `[]` and `findOne()` return
> `null`; it is never put into the SQL. The `Query` builder throws instead, which is easier to debug.

## The Query builder

`Model::query()` returns a `Laf\Database\Query` bound to the model's table and class. Builder methods return the
query, so they chain; a terminal method (`get()`, `first()`, `count()`, ...) runs one statement.

```php
use MyApp\Employee;

$employees = Employee::query()
    ->where('company_id', $acmeId)
    ->where('salary', '>', 2000)
    ->orderBy('last_name')
    ->limit(10)
    ->get();                 // [Employee Ana Hoxha]
```

```sql
SELECT * FROM `employee` WHERE `company_id` = :v0 AND `salary` > :v1 ORDER BY `last_name` ASC LIMIT 10
```

Safety rules that hold for every method:

- Column names must exist in the model's table, else `InvalidArgumentException('Table employee has no column "nope"')`.
  They are quoted for the engine (backticks on MySQL, double quotes on PostgreSQL).
- Values are always bound (`:v0`, `:v1`, ...), typed through the column's field type when it accepts the value
  (`where('is_active', 'yes')` binds `1` as an integer).
- Operators come from a fixed list (`Query::OPERATORS`), else `InvalidArgumentException('Unsupported operator "REGEXP"')`.
- `orderBy()` accepts only `ASC` / `DESC` (any case).
- A query is single-table. There are no joins; see [Raw SQL through Db](#raw-sql-through-db-joins-and-reports).

A query object is mutable: each builder call changes it. To reuse a base query for several results, `clone` it:

```php
$base = Employee::query()->where('is_active', true);
$total   = (clone $base)->count();
$highest = (clone $base)->orderBy('salary', 'DESC')->first();
```

You can also build a query on any `Table` without a model class: `new Query($table)` gives a query whose
`arrays()`, `column()`, `count()` and aggregates work, while `get()` / `first()` throw `LogicException` ("This query is
not bound to a model class; use arrays() or column()"). The third constructor argument forces an engine
(`Identifier::MYSQL` / `Identifier::POSTGRES`); the default follows the connection.

## Conditions

```php
->where('last_name', 'Hoxha')               // `last_name` = :v
->where('company_id', null)                 // `company_id` IS NULL
->where('id', [1, 2, 3])                    // `id` IN (:v0, :v1, :v2)
->where('salary', '>', 2000)                // `salary` > :v
->where('salary', '<>', null)               // `salary` IS NOT NULL
->where('birth_date', 'BETWEEN', ['1980-01-01', '1995-12-31'])
->where('email', 'LIKE', '%@acme.example')  // raw pattern, you write the wildcards
->where('company_id', $acme)                // a model object: its primary key
->whereIn('first_name', ['Ana', 'Dren'])
->whereNotIn('first_name', ['Ana', 'Dren'])
->whereNull('salary')
->whereNotNull('salary')
->whereBetween('birth_date', '1980-01-01', '1995-12-31')
```

`where()` with two arguments means `=` (or `IS NULL` / `IN` for `null` / arrays); with three, the middle one is the
operator. Supported operators: `=`, `<>`, `!=`, `<`, `<=`, `>`, `>=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`,
`IS NULL`, `IS NOT NULL`, `BETWEEN`, `NOT BETWEEN`. `= null` becomes `IS NULL` and `<> null` becomes `IS NOT NULL`
(SQL's `= NULL` never matches). `whereIn()` with an empty array adds `1 = 0` (nothing matches), `whereNotIn()` with
an empty array adds `1 = 1`.

When the builder cannot express a condition, `whereRaw()` adds SQL you write yourself, with named bindings:

```php
Employee::query()->whereRaw('YEAR(birth_date) < :y', [':y' => 1990])->column('first_name');
// ['Besa', 'Dren']    SQL: ... WHERE (YEAR(birth_date) < :y)
```

> **Note:** `whereRaw()` is for SQL written by the developer. Never concatenate user input into its first argument;
> pass values through the bindings array (`':name' => value`, or `':name' => [value, PDO::PARAM_*]`). Raw SQL is
> engine-specific (`YEAR()` does not exist on PostgreSQL).

## AND, OR and groups

Conditions are joined in the order you add them, flat, with no implicit parentheses. `orWhere()` outside a group
therefore applies to everything before it:

```php
Employee::query()
    ->whereStartsWith('email', 'q04.')
    ->where('company_id', $acmeId)
    ->orWhere('salary', '>', 3000);
```

```sql
SELECT * FROM `employee` WHERE `email` LIKE :v0 AND `company_id` = :v1 OR `salary` > :v2
```

SQL reads that as `(email LIKE ... AND company_id = ...) OR salary > 3000`, so it also returned well-paid employees
whose e-mail does not match, rows the first condition was meant to exclude. Put the alternatives in a group:

```php
use Laf\Database\Query;

Employee::query()
    ->whereStartsWith('email', 'q04.')
    ->whereGroup(fn (Query $q) => $q->where('company_id', $acmeId)->orWhere('salary', '>', 3000))
    ->column('first_name');
// ['Ana', 'Besa', 'Dren']
```

```sql
SELECT * FROM `employee` WHERE `email` LIKE :v0 AND (`company_id` = :v1 OR `salary` > :v2)
```

`orWhereGroup($callback)` adds the group with `OR`. Groups nest. An empty group adds nothing. The text-search
methods and `whereRaw()` take a last `$boolean` argument (`'AND'` or `'OR'`).

## Conditional pieces: when()

`when($condition, $then, $otherwise = null)` applies `$then` when the condition is truthy, else `$otherwise`, which
keeps search screens free of `if` blocks:

```php
use Laf\Database\Query;

$search = trim($_GET['q'] ?? '');            // e.g. 'ri'
$onlyActive = isset($_GET['active']);

$employees = Employee::query()
    ->when($search !== '', fn (Query $q) => $q->whereContains('last_name', $search))
    ->when($onlyActive, fn (Query $q) => $q->where('is_active', true), fn (Query $q) => $q->orderBy('last_name'))
    ->get();
```

| `$search` | `$onlyActive` | SQL (after the common prefix) | Result |
|---|---|---|---|
| `'ri'` | true | `` AND `last_name` LIKE :v1 AND `is_active` = :v2 `` | Berisha |
| `'ri'` | false | `` AND `last_name` LIKE :v1 ORDER BY `last_name` ASC `` | Berisha, Morina |
| `''` | true | `` AND `is_active` = :v1 `` | Krasniqi, Berisha |
| `''` | false | `` ORDER BY `last_name` ASC `` | Berisha, Krasniqi, Morina |

The callbacks also receive the condition's value as second argument.

## Text search: whereContains(), whereStartsWith(), whereEndsWith()

For user-typed search text, use these instead of `where(..., 'LIKE', ...)`:

```php
Employee::query()->whereContains('email', '100%')->column('email');
// ['fatos_100%@example.com']
// SQL:      SELECT * FROM `employee` WHERE `email` LIKE :v0
// binding:  ':v0' => '%100\%%'
```

- The text's `%`, `_` and `\` are escaped (`Query::escapeLike('50%_off\\')` gives `'50\%\_off\\\\'`), so they match
  literally. With a raw `LIKE`, a user typing `%` would match everything.
- The match is case-insensitive on both engines. On PostgreSQL the column is cast to text and compared with
  `ILIKE`, so numbers and dates are searchable too:

```sql
-- the same builder on PostgreSQL
SELECT * FROM "employee" WHERE CAST("salary" AS TEXT) ILIKE :v0 AND "company_id" = :v1 ORDER BY "last_name" ASC LIMIT 5
```

`where($field, 'LIKE', $pattern)` stays the raw, engine-native form for patterns you build yourself.

## Columns, order, limit

```php
Employee::query()
    ->select(['id', 'first_name'])   // default: every column
    ->orderBy('last_name')           // ASC by default; call again for a second sort column
    ->orderBy('first_name', 'desc')
    ->limit(20)                      // null removes the limit
    ->offset(40);                    // must be >= 0
```

> **Note:** objects built from a partial `select()` only carry the selected columns; the getters of the other
> columns return `null`. Such objects are for reading: `store()` on one fails as soon as a required column was not
> selected (verified: `MissingFieldValueException` for `last_name` and `email` after `select(['id', 'first_name'])`).
> Load full rows when you intend to write.

## Getting results

| Terminal | Returns |
|---|---|
| `get()` | `Employee[]` (one SELECT, every row hydrated; `afterLoad()` runs per row) |
| `first()` | the first `Employee` (adds `LIMIT 1`), or `null` |
| `arrays()` | rows as associative arrays (driver values) |
| `column('email')` | one column of every row: `['ana@...', 'besa@...']` |
| `count()` | number of matching rows (`ORDER BY` / `LIMIT` ignored) |
| `exists()` | `true` when at least one row matches (`SELECT 1 ... LIMIT 1`) |

```php
$base = Employee::query()->whereStartsWith('email', 'q04.');
(clone $base)->count();                                      // 5
(clone $base)->where('is_active', false)->exists();          // true
(clone $base)->orderBy('salary', 'desc')->first()->getFirstNameVal();   // 'Dren'
(clone $base)->select(['id', 'first_name'])->orderBy('id')->limit(2)->arrays();
// [['id' => 10, 'first_name' => 'Ana'], ['id' => 11, 'first_name' => 'Besa']]
```

A failing statement throws `Laf\Exception\DatabaseException` with the SQL and bindings (see
[03-models.md](03-models.md#exceptions)).

## Aggregates and pairs

```php
$base = Employee::query()->whereStartsWith('email', 'q04.');
(clone $base)->sum('salary');          // '8100.50'
(clone $base)->avg('salary');          // '2025.125000'
(clone $base)->max('salary');          // '3200.00'
(clone $base)->min('birth_date');      // '1979-01-30'
(clone $base)->where('salary', '>', 999999)->sum('salary');   // null
(clone $base)->getAggregateSql('sum', 'salary');
// 'SELECT SUM(`salary`) FROM `employee` WHERE `email` LIKE :v0'
```

Aggregates return what the driver returns: a numeric string for `DECIMAL` columns (keep it a string for money, or
format it with `Laf\Util\Locale::formatNumber()`), an int or float for others, `null` when no row matches. `NULL`
values are skipped by SQL (Elira has no salary, so the average is over four rows). `getAggregateSql()` accepts
`SUM`, `MIN`, `MAX`, `AVG` and `COUNT`.

`pairs($keyColumn, $valueColumn)` gives key => value, the shape a select box wants:

```php
use MyApp\Company;

Company::query()->where('record_status_id', '<>', 0)->orderBy('name')->pairs('id', 'name');
// [6 => 'Acme', 7 => 'Globex']
```

## Pagination

`paginate($page = 1, $perPage = 25, $asArrays = false)` runs a `COUNT(*)` plus one page of rows and returns a
`Laf\Database\Pagination`:

```php
$page = Employee::query()->orderBy('last_name')->paginate($_GET['page'] ?? 1, 2);

foreach ($page as $employee) {          // iterable over the items
    echo $employee->getLastNameVal();   // page 2: Hoxha, Krasniqi
}
```

| Property / method | Page 2 of 5 rows, 2 per page |
|---|---|
| `$page->items` | `[Employee Hoxha, Employee Krasniqi]` |
| `$page->total` | `5` (all pages) |
| `$page->page` | `2` |
| `$page->perPage` | `2` |
| `$page->lastPage` | `3` (`1` when there are no rows) |
| `$page->from` / `$page->to` | `3` / `4` (1-based positions; `0` on an empty page) |
| `$page->hasMore()` | `true` |
| `$page->hasPrevious()` | `true` |
| `count($page)` | `2` (items on this page) |
| `$page->isEmpty()` | `false` |
| `$page->toArray()` / `json_encode($page)` | the counts plus the items |

Rules:

- The page number may be a string from the request: anything below 1 or non-numeric (`'abc'`) means page 1.
- A page past the end is empty (it is **not** clamped to the last page): page 9 gives `isEmpty()` true, `from` and
  `to` `0`, `lastPage` `3`. Redirect or show "no more rows" yourself.
- The query's own `limit()` / `offset()` are ignored.
- Give an `orderBy()` on a unique column (or ending with one), else the database may return rows in a different
  order on each page.
- `$asArrays = true` pages over plain arrays (no model class needed), handy for JSON APIs:

```php
echo json_encode(
    Employee::query()->select(['id', 'first_name'])->orderBy('id')->paginate('1', 2, true)
);
// {"items":[{"id":10,"first_name":"Ana"},{"id":11,"first_name":"Besa"}],"total":5,"page":1,"perPage":2,"lastPage":3,"from":1,"to":2}
```

Drawing the pager links is covered in [09-lists-and-tables.md](09-lists-and-tables.md).

## Large result sets: cursor() and chunk()

`get()` builds every object at once. For exports and batch jobs over many rows use one of these:

`cursor($asArrays = false)` is a generator that yields one object (or array) at a time:

```php
foreach (Employee::query()->orderBy('id')->cursor() as $i => $employee) {
    echo "$i: {$employee->getEmailVal()}\n";
}
// 0: ana@acme.example
// 1: besa@acme.example
// ...
```

One statement runs; PHP holds one object at a time. On MySQL the driver still buffers the whole result unless the
connection disables buffered queries (`database.options` with `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false`, see
[13-database.md](13-database.md)); PostgreSQL streams.

`chunk($size, $callback, $asArrays = false)` hands out batches, each fetched with its own `LIMIT/OFFSET` query, and
returns the number of rows handed out. The callback receives the batch and its 1-based number, and returns `false`
to stop:

```php
$handed = Employee::query()->orderBy('id')->chunk(2, function (array $batch, int $n) {
    echo "batch $n: " . implode(', ', array_map(fn ($e) => $e->getFirstNameVal(), $batch)) . "\n";
});
// batch 1: Ana, Besa
// batch 2: Dren, Elira
// batch 3: Fatos
// $handed === 5

$handed = Employee::query()->orderBy('id')->chunk(2, fn (array $batch) => false);   // $handed === 2
```

> **Note:** because each batch is a new `OFFSET` query, order by a unique column and do not insert, delete or change
> the sort column of matching rows inside the callback, or rows are skipped or repeated. Collect ids and delete
> afterwards.

Use `cursor()` when one pass over the rows is enough; `chunk()` when you want to work (or commit) per batch.

## Deleting rows

`delete()` deletes every matching row in one statement and returns the count. It refuses to run without a
condition, so an empty query cannot wipe a table:

```php
Employee::query()->where('company_id', $globexId)->where('is_active', false)->delete();   // rows deleted, e.g. 1
Employee::query()->delete();   // LogicException: 'Query::delete() needs at least one condition'
```

This bypasses the models: no hooks, no audit log entries, no soft delete. Load the objects and call
`hardDelete()` / `softDelete()` when those matter (see [03-models.md](03-models.md#deleting-hard-and-soft)).

## Inspecting the SQL

```php
$q = Employee::query()->where('company_id', 6)->where('salary', '>', 2000)->orderBy('last_name')->limit(10);
$q->getSql();            // 'SELECT * FROM `employee` WHERE `company_id` = :v0 AND `salary` > :v1 ORDER BY `last_name` ASC LIMIT 10'
$q->getBindingValues();  // [':v0' => 6, ':v1' => 2000]
$q->getBindings();       // [':v0' => [6, PDO::PARAM_INT], ':v1' => [2000, PDO::PARAM_STR]]
```

To see every statement a request runs, enable the `Db` query log or a PSR-3 logger ([13-database.md](13-database.md)).

## Raw SQL through Db: joins and reports

`Query` is deliberately single-table. When you need a join, `GROUP BY`, a subquery or a database function, write
the SQL and run it through `Laf\Database\Db` with bound parameters:

```php
use Laf\Database\Db;

$rows = Db::getAllAssoc(
    'SELECT c.name AS company, COUNT(e.id) AS employees, SUM(e.salary) AS payroll
       FROM company c
       LEFT JOIN employee e ON e.company_id = c.id
      WHERE c.record_status_id <> 0
   GROUP BY c.id, c.name
   ORDER BY c.name',
    []
);
// [['company' => 'Acme', 'employees' => 2, 'payroll' => '3950.50'],
//  ['company' => 'Globex', 'employees' => 2, 'payroll' => '3200.00']]
```

When the join only filters and you want models back, select the model's columns and hydrate them, no extra query
per row:

```php
use Laf\Database\Db;
use MyApp\Employee;

$employees = array_map(
    fn (array $row) => Employee::hydrate($row),
    Db::getAllAssoc(
        'SELECT e.* FROM employee e JOIN company c ON c.id = e.company_id WHERE c.name = :name ORDER BY e.id',
        [':name' => 'Globex']
    )
);
// each one isLoaded() === true, isDirty() === false
```

Other helpers with the same `($sql, $params)` signature: `Db::getRowAssoc()`, `Db::getOne()`, `Db::getColumn()`,
`Db::getPairs()`, `Db::getAllKeyed()`. Parameters are bound by PHP type (`int` as integer, `bool` as 0/1, `null`
as NULL). Table and column names in raw SQL are yours to quote (`Laf\Database\Identifier::quote()`); never build them
from user input. Full reference: [13-database.md](13-database.md).

## Query reference

| Method | What it does | Notes |
|---|---|---|
| `__construct(Table $table, ?string $objectClass = null, ?string $engine = null)` | a query on a table | `Model::query()` fills the class |
| `getTable()` | the bound `Table` | |
| `select(array $columns)` | columns to fetch | default `*` |
| `where($field, $operatorOrValue, $value)` | `AND` condition | 2 args: `=` / `IS NULL` / `IN` |
| `orWhere($field, $operatorOrValue, $value)` | `OR` condition | joins flat, see groups |
| `whereIn($field, array)`, `whereNotIn($field, array)` | `IN` / `NOT IN` | empty array: `1 = 0` / `1 = 1` |
| `whereNull($field)`, `whereNotNull($field)` | `IS NULL` / `IS NOT NULL` | |
| `whereBetween($field, $low, $high)` | `BETWEEN` | |
| `whereContains($field, $text, $boolean = 'AND')` | escaped, case-insensitive contains | `ILIKE` on PostgreSQL |
| `whereStartsWith(...)`, `whereEndsWith(...)` | same, anchored | |
| `static escapeLike(string $text)` | escape `\`, `%`, `_` for a LIKE pattern | |
| `whereRaw($sql, array $bindings = [], $boolean = 'AND')` | developer-written condition in parentheses | never user input in `$sql` |
| `whereGroup(callable, $boolean = 'AND')`, `orWhereGroup(callable)` | parenthesised group | |
| `when($condition, callable $then, ?callable $otherwise = null)` | conditional piece | |
| `orderBy($field, $direction = 'ASC')` | sort | `ASC`/`DESC` only |
| `limit(?int)`, `offset(int)` | | negative values throw |
| `get()` | model objects | needs the model class |
| `first()` | first object or `null` | needs the model class |
| `arrays()` | associative arrays | |
| `column($field)` | one column | |
| `count()`, `exists()` | | ignore order/limit |
| `sum($f)`, `min($f)`, `max($f)`, `avg($f)` | aggregates | driver value, `null` when no row |
| `getAggregateSql($function, $field)` | the aggregate's SQL | |
| `pairs($keyField, $valueField)` | key => value | |
| `paginate($page = 1, $perPage = 25, $asArrays = false)` | `Pagination` | page past the end is empty |
| `cursor($asArrays = false)` | generator | |
| `chunk($size, callable $callback, $asArrays = false)` | batches; returns rows handed out | callback returns `false` to stop |
| `delete()` | delete matching rows; returns count | refuses without a condition; no hooks |
| `getSql()`, `getBindings()`, `getBindingValues()` | inspection | |

`Laf\Database\Pagination`: public properties `items`, `total`, `page`, `perPage`, `lastPage`, `from`, `to`; methods
`hasMore()`, `hasPrevious()`, `isEmpty()`, `count()`, `getIterator()`, `toArray()`, `jsonSerialize()`; constructor
`new Pagination(array $items, int $total, int $page, int $perPage)` if you page something yourself.

## See also

- [03-models.md](03-models.md) — loading single records, `hydrate()`, exceptions
- [09-lists-and-tables.md](09-lists-and-tables.md) — drawing query results as tables with pager links
- [10-grids.md](10-grids.md) — interactive grids with server-side search, sort and export
- [13-database.md](13-database.md) — `Db` helpers, transactions, query log
- [17-security.md](17-security.md) — why bindings and validated column names matter
