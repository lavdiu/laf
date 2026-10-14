Unique values in forms (2026-10-14, improvements1): a duplicate never ends in an error page; unique keys over several columns are generated and checked; database unique violations become field messages.

# What the user asked

"can we check and validate enforcing unique value fields, so it doesnt error out but instead shows a message?"

# What was found (before)

| Case | Before |
|---|---|
| single unique column, duplicate on insert/update (also case/trailing space on MariaDB) | message "E-mail: this value is already in use", but only after every other field was valid (second round trip) |
| unique index over several columns (UNIQUE (first_name, last_name), linker employee_project) | uncaught `DatabaseException` 1062/23505 → error page |
| unique index the model does not know (race, expression index, Base class generated earlier) | uncaught `DatabaseException` |

# Changes

- `Table::addUniqueKey($name, $columns)` / `removeUniqueKey()` / `hasUniqueKey()` / `getUniqueKeys()`.
- Inspectors: `AbstractTableInspector::getUniqueKeys()` / `setUniqueKeys()` / `storeUniqueKeyRows()`, `populateUniqueKeys()`
  (default no-op so third-party subclasses keep working; NOT added to `TableInspectorInterface` for BC). MySQL reads
  `information_schema.STATISTICS` (prefix indexes dropped), PostgreSQL `pg_index` (partial and expression indexes
  dropped, key columns only).
- `ClassGenerator::generateUniqueKeys()`: one `addUniqueKey()` per index with ≥ 2 columns; nothing emitted otherwise,
  so output is byte-identical for tables without such an index (only `BaseEmployeeProject` and the
  `BaseM2mPersonGroup` fixture changed).
- `BaseObject`: unique checks generalised to keys (`uniqueKeyColumns()`, `isDuplicateKey()`); public
  `duplicateUniqueKeys(?array $fieldNames = null, bool $changedOnly = true)` and
  `uniqueViolationFieldNames(DatabaseException)` (PG `Key (…)=` detail incl. expression indexes, else index name via
  Table keys / PRIMARY / `{table}_pkey` / MySQL information_schema). `store()` throws `UniqueFieldDuplicateValueException`
  with every column of a combination. `validate()` reports combinations.
- `Form::validate()` checks taken unique keys of the editable, error-free fields together with the field rules;
  `processForm()` converts `DatabaseException::isUniqueViolation()` into the same messages (form-level when the columns
  are unknown); other database errors still propagate. BC: `getPrevious()` of the `FormValidationException` is still a
  `UniqueFieldDuplicateValueException` when a duplicate was found (an existing test pins this).
- Messages: "{Label}: this value is already in use"; "{A} + {B}: this combination is already in use" on the first
  column of the key the form edits.

# Verified

- Scenario script (scratchpad `uniq/repro2.php`) on MariaDB and PostgreSQL: 11 cases incl. combination + bad date in
  one round trip, database-only combination, model without the key, linker duplicate, edit of own row unchanged.
- Unit 784 (no database), Integration 197 on MariaDB and on PostgreSQL, PHPStan 0. New tests: `tests/UniqueKeyTest.php`,
  `GeneratorUnitTest::unique_indexes_over_several_columns_become_add_unique_key`, 5 integration tests in
  `BaseObjectTest`; `m2m_person_group` got `UNIQUE (person_id, group_id)`; fixture regenerated (identical on both engines).

# Not done / notes

- `store()` outside forms still throws `DatabaseException` for a database-only violation (changing the exception type
  would break apps that catch `DatabaseException::isUniqueViolation()`).
- asm/routerarena get `addUniqueKey()` lines only when they regenerate; until then the database fallback covers them.
