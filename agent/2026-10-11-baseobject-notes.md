Review and rework of `src/Laf/Database/BaseObject.php` (the Active Record base): 25 bugs fixed, a single-table `Query` finder, lifecycle hooks, transactions, typed errors, MySQL/MariaDB + PostgreSQL verified, clean under E_ALL on PHP 8.5.

# BaseObject review and rework — 2026-10-11

Branch `improvements1`. Companion files: `src/Laf/Database/Query.php` (new), `src/Laf/Exception/DatabaseException.php` (new),
`src/Laf/Database/Db.php` (transactions, `getInsertId(?sequence)`), `src/Laf/Generator/ClassGenerator.php` (template),
`tests/BaseObjectTest.php` (+16 integration tests), `tests/QueryTest.php` (new, 19), `tests/BaseObjectStateTest.php` (new, 17),
fixtures `tests/Fixtures/LafShell/Base/*` (regenerated shape, `status` column).

## 1. Bugs found and fixed

| # | Where | Bug | Fix |
|---|---|---|---|
| 1 | `getQueryBuilder()` | referenced the deleted `QueryBuilder` class → fatal when called; docs still advertised it | returns the new `Query`; `query()` is the documented entry point |
| 2 | `returnLeafClass()` | `return static::returnLeafClass()` → infinite recursion for a Base class without a leaf (routerarena has two) | `return $this` (late static binding already gives the leaf) |
| 3 | `bOfind` / `bOfindOne` / `findAsArray` | three copies; identifiers unquoted; `null` criteria became `col = NULL` (never matches); arrays unsupported; untyped bindings (PostgreSQL rejects PHP `false` as `''` on boolean columns); 1 + N queries | one `Query`; null → `IS NULL`, array → `IN`; values bound through the field type; one SELECT, objects hydrated from the rows |
| 4 | `reset()` | replaced the table with an empty `new Table(name)` → object unusable | clears values, old values and caches; metadata kept |
| 5 | `select()` | on a miss the previous row's values and `recordSelected` stayed → a later `store()` updated the wrong row | a miss unloads a previously loaded row; a fresh object keeps its unsaved values |
| 6 | `insert()` | MySQL backticks via `isMySqlDriver()` (`mariadb`/`pgsql` spellings unquoted), PostgreSQL unquoted | `Identifier::quote()` everywhere |
| 7 | `insert()`/`update()` | `<package>\Person::getLoggedUserId()` fatal when no `Person` class; `setActiveUserId()` ignored for `created_by`/`updated_by` | `resolveActiveUserId()`: active user, else `Person` when it exists, else null (same rule as AuditLog) |
| 8 | `insert()` | PostgreSQL id via `lastInsertId()` = `lastval()`: fails when no sequence was used, or returns another table's value | `INSERT … RETURNING <key columns>` on PostgreSQL; `lastInsertId()` on MySQL |
| 9 | `insert()` | every column sent, NULL for unset fields → database DEFAULTs never applied | unset, not-required, non-key columns are omitted (user decision); zero columns → `DEFAULT VALUES` / `() VALUES ()` |
| 10 | `insert()`/`update()`/`hardDelete()` | PDO errors rethrown as bare `\Exception` (SQLSTATE lost), copy-pasted "INSERT SQL failed" messages, unreachable `=== false` branches, empty `$executeValues` in logs | one `execute()`; `DatabaseException` with SQL, bindings, SQLSTATE, previous |
| 11 | `update()` | `updated_on`/`updated_by` set before checking for changes → a no-op `store()` wrote the row and logged an UPDATE entry | nothing changed = nothing written; timestamps only when there is a change |
| 12 | `update()` | required/unique checks before the "not loaded" check; `isrecordSelected()` | order fixed |
| 13 | `hardDelete()` | object stayed "selected" after the row was gone | `recordSelected = false` once a row was deleted (values and id kept for messages) |
| 14 | `getAffectedRows(): int` | property default null → TypeError before the first write | typed `int`, default 0 |
| 15 | `setLogger()` | `setLogger(null)` fatal; seven copies of the `addLogger*` body | `?LoggerInterface`, one private `log()` |
| 16 | `getTable()` | built a debug context on every call (hot path) and fatalled when no table was bound | `LogicException` with a clear message; no per-getter logging |
| 17 | `__clone()` | `getTable()` unguarded | guarded |
| 18 | `getListAllSimpleTableObject()` | pretty view URL was `/mod/sub/view/id` (column name, no `{}`), table unquoted | `{<pk>}` placeholder in all three URLs, quoted table |
| 19 | `canSoftDelete()` | only `deleted`, while `softDelete()` also handles `record_status_id` → generated delete pages hard-deleted those tables | both columns |
| 20 | `softDelete()` | `reload()` result ignored: unsaved changes discarded; missing row → inserted a new row with `deleted = 1` | reload only when clean; missing row → false |
| 21 | `checkUniqueFieldsForDuplicateValues()` | TODO stub | implemented for single-column unique fields (`Table::getUniqueFields()` + `Field::isUnique()`), own row excluded; throws `UniqueFieldDuplicateValueException` |
| 22 | `setRecordId()` | dead `filter_var` branch, wrong docblocks | normalised ids (`int|string|array|null`) |
| 23 | `populateTableObjectList()` | `Settings::get() ?? 'mysql'` never fell back (throws), PostgreSQL listed every schema | `Identifier::engine()`, `current_schema()` |
| 24 | `getAllFieldsAsKVArrays()` | duplicate of `getFieldValuesAsArray()` | delegates |
| 25 | `loadRelation()` | dead stub | removed (no callers, no implementers) |

Found in the apps, not changed here: routerarena `ArticleHandler.php:80` and `ArticleFromUrlHandler.php:86` use `insert()`'s `true` as the
new id (`getRecordId()` is the id); asm `Grup::__clone()` does not call `parent::__clone()` so the clone shares its Field objects with the
original. `PostgresTableInspector` does not flag identity columns as auto-increment (generator, separate task).

## 2. Design

The class stays the single public base with the two-tier inheritance; it is now organised as state → metadata → values → loading →
finders → writing → validation → hooks → audit → logging → UI helpers → schema utility.

- **`Laf\Database\Query`** (final, `strict_types`): single-table criteria builder bound to a `Table` and a model class.
  `where()` (2-arg `=`/`IS NULL`/`IN`, 3-arg operator from a whitelist), `orWhere()`, `whereIn/NotIn/Null/NotNull/Between()`,
  `whereRaw()` (trusted SQL), `orderBy()`, `limit()`, `offset()`, `select()`; terminals `get()`, `first()`, `arrays()`, `column()`,
  `count()`, `exists()`, `delete()` (refuses to run without a condition), `getSql()`, `getBindings()`. Column names validated with
  `Table::hasField()`, quoted by `Identifier`, values bound through `FieldType::toDb()` (fallback: string, as the legacy finders did).
  Joins and eager loading were left out on purpose; the class is the place to add them.
- **Hydration**: `loadRow(array $row)` and static `hydrate(array $row)` build a loaded object from a fetched row (no second SELECT);
  `Query::get()`, the finders and the generated `listAllObjects()` use it.
- **Write path**: `insert()`, `update()`, `hardDelete()` share one `execute()`; `DatabaseException` carries SQL, bindings, SQLSTATE,
  driver code, `isUniqueViolation()`, `isForeignKeyViolation()`. `store()` is `isNew() ? insert() : update()` with the old rule.
- **Hooks**: protected no-ops `beforeInsert()`, `afterInsert()`, `beforeUpdate()`, `afterUpdate()`, `beforeDelete($mode)`,
  `afterDelete($mode)`, `afterLoad()`; a hook aborts by throwing. They are the intended replacement for overriding `insert()`/`update()`.
- **Transactions**: `Db::beginTransaction/commit/rollBack/inTransaction()` and `Db::transaction(callable)` (nest-aware), surfaced as
  `BaseObject::transaction()`. `hardDelete()` still opens no transaction (routerarena wraps it in its own).
- **State API**: `isNew()`, `isDirty()`, `isLoaded()`, `getChangedFields()`, `getChangedValues()`, `setFieldValues()`, `toArray()`,
  `JsonSerializable`, `getPrimaryKeyValue()`, `getLastSql()`, `validate()` (required + type rules + uniqueness, no write),
  `resolveActiveUserId()`, column-name constants `FIELD_CREATED_ON` … `FIELD_RECORD_STATUS_ID` (overridable per subclass).
- **Logging**: PSR-3 through one `log()`; only loads, writes and errors log.

## 3. Compatibility

Kept untyped because the apps override them without types: `insert()`, `update()`, `hardDelete()`, `returnLeafClass()`, `__clone()`;
`getIcon(): ?string` unchanged; `select($id): bool` matches the generated override; the constructor still accepts `null`, `''`,
numeric strings, varchar keys and arrays. No `find()`, `findOne()`, `findBy()`, `listAll*()` were added to the base class (the leaf and
Base classes own those names, `AuditLog` owns `findBy()`/`fromRow()`/`getChanges()`). `tests/BaseObjectStateTest.php` loads a class with
the exact override shapes used by asm and routerarena, so an incompatible parent signature fails the unit suite.

Behaviour changes to know about:
- A no-op `store()` no longer writes `updated_on` or an audit entry.
- Unset nullable columns are left out of the INSERT, so database defaults apply (a nullable column with a non-null default cannot be
  forced to NULL on insert any more; set it after the insert if needed). `reload()` reads the defaults back.
- `canSoftDelete()` is true for `record_status_id` tables → generated delete pages soft-delete them.
- `select()` on a missing id unloads a previously loaded row; `hardDelete()` leaves the object unloaded.
- Statement failures are `DatabaseException` (still an `\Exception`, same message); `insert()`/`update()` no longer lose the SQLSTATE,
  `hardDelete()` no longer rethrows the raw `PDOException`.
- `getRecordId()` after a load is the typed key value (int for integer keys).
- `insert()` on a non-auto-increment key without a generator throws `MissingFieldValueException` (was a bare `\Exception`).
- Generated `listAllObjects()`/`listAllArray()` (template + fixtures) run one SELECT through `query()`; the constructor's dead
  `in_array(FieldType object, …)` branch is gone. Existing generated classes keep working unchanged.

## 4. Verification

- Unit: 510 tests / 1,664 assertions, `-d error_reporting=E_ALL -d display_errors=1`, PHP 8.5.4.
- Integration MariaDB 12.2: 124 tests / 443 assertions. PostgreSQL 18.3: 124 / 443 (same suite; boolean comparisons, `RETURNING`,
  sequence isolation, transactions covered on both).
- PHPStan level 5 clean on `BaseObject.php`, `Query.php`, `DatabaseException.php` (remaining findings are pre-existing in `Db.php`
  and `ClassGenerator.php`).
- `tests/Fixtures/regenerate.php` diff against the fixtures: docblocks/formatting only.

## 5. Not done / next

- `Db.php` itself (next task): typed properties, `execute()` without a connection, `getLogger()` uninitialised, MySQL-only
  `handleSqlErrorLogging`.
- Composite unique indexes are still the database's job (`DatabaseException::isUniqueViolation()` tells the caller).
- Audit rows are still a separate statement; wrap `store()` in `transaction()` when atomicity matters.
- `getListAllSimpleTableObject()` keeps UI classes inside the ORM base; moving it to a UI factory would need every app page checked.
