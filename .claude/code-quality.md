# LAF Code Quality Notes

## Known Bugs
- `FormElementTrait::setAutocomplete()` — sets attribute `'attribute'` instead of `'autocomplete'`
- `Field.php` calls `Table::setDisplayFieldName()` which doesn't exist (should be `setDisplayField()`)
- ~~`BaseObject::returnLeafClass()` — potential infinite recursion~~ fixed 2026-10-11 (returns `$this`)
- ~~`checkUniqueFieldsForDuplicateValues()` — marked TODO~~ implemented 2026-10-11 (single-column unique fields checked before the statement)
- ~~`QueryBuilder::with()` / `loadRelation()`~~ QueryBuilder deleted; replaced by the single-table `Laf\Database\Query` (2026-10-11)
- `Settings::saveToFile()` / `loadFromFile()` — not implemented (stubbed)

## PHP 8+ Adoption Gaps
- No typed properties (PHP 7.4+) on most classes
- No enums — `DrawMode` and `ContainerType` should be PHP 8.1 enums
- No `match` expressions, union types, or named arguments
- Only `mixed` return type used occasionally (Db.php)
- Good use of nullable types and return types throughout

## Query (replaced QueryBuilder, 2026-10-11)
- Single table only: no joins, GROUP BY, HAVING, subqueries or eager loading (by design; add when needed)
- Aggregates: `count()` only
- Transactions: `Db::transaction()` / `BaseObject::transaction()` (nest-aware); audit rows are still a separate statement
- Quoting through `Identifier` on both engines

## Db (reworked 2026-10-11)
- Fixed: half-built singleton cached after a failed connect, `?? 'mysql'` engine fallback that never applied, engine spellings other than mysql/postgres silently leaving no connection, `getLogger()` TypeError (never initialised), `getErrorMessage()/getErrorTrace*()` TypeErrors, `has_error` never set, trace stored as array in the string field, MySQL-only `SHOW TABLES` in the SQL error logger (which also replaced the original exception when its table was missing), execution errors never reaching the SQL error logger (only prepare was wrapped), `execute()` returning false under `: int`, `getRowObject()` returning an empty stdClass, `PhpGrid::isPostgres()` always false (`Db::getAttribute()` did not exist; the PostgreSQL branch then applied LOWER() to integer columns)
- Still outside: `SimpleTable::setSql()` raw SQL, `TableInspector` MySQL-only metadata queries (PostgresTableInspector handles its own)

## Documentation Gaps
- README.md is just `# LAF` — needs content
- No CONTRIBUTING.md, CHANGELOG, or installation guide
- phpstan.neon and rector.php config files missing
- No CI/CD pipeline

## Monolog Version
- Using `^1.24` — current is v3.x, should be updated
