Change notes for ForeignKey, PrimaryKey, the BaseObject integration tests and the Laf\UI\Table component (uncommitted on improvements1): bugs fixed, features added, compatibility, verification on MariaDB and PostgreSQL.

# ForeignKey / PrimaryKey / BaseObjectTest / UI\Table — notes

Branch `improvements1`, working tree only. Builds on the FieldType rewrite (`2026-10-11-fieldtype-rewrite-notes.md`). Plan: `C:\Users\lulzi\.claude\plans\now-check-src-laf-database-foreignkey-ph-staged-floyd.md`.

## 1. Bugs fixed

| Where | Bug | Fix |
|---|---|---|
| `ForeignKey::isValidValue()` | Backtick-quoted SQL (fails on PostgreSQL); `(int)` cast on the value (any varchar/UUID target such as ASM's `grupdetalet.grupi → grup.grupid varchar(50)` could never validate); one SELECT per `setXxxVal()` | Engine-aware quoting via the new `Identifier` helper; value bound through the referenced column's own type (`toDb()`), a value that cannot be of that type is simply invalid; positive answers memoised per request (`clearValidationCache()`, called by `hardDelete()`) |
| `ForeignKey::getTable()/getField()` | Non-nullable return types on nullable properties | `?Table`, `?Field` |
| `PrimaryKey::__construct()` | `$table` typed `?string` but returned as `Table` | Accepts a `Table` (a string is ignored instead of breaking); `getTable(): ?Table`, `getFirstField(): ?Field` |
| `BaseObject::update()/hardDelete()` with composite keys | `WHERE first_pk_column = …` only, so on ASM's four composite-key tables an update or delete could touch several rows | WHERE now covers every key column (`PrimaryKey::getWhereClause()/getBindings()`), bound to the values the row was loaded with; single-column tables produce the same SQL as before |
| `BaseObject::__clone()` | Shallow copy: the clone shared the original's `Field` objects, so clearing the clone's id also cleared the original and both ended up with the same id after `store()` | `Table::__clone()` deep-copies fields, PK, FKs, unique/display fields; `Field::__clone()` drops the cached form element; clone resets `recordSelected` |
| `Cell::addStyle()` | `return this;` (undefined constant, fatal) | `return $this;` |
| `Table::__construct(string $id = null)` | Implicitly nullable (PHP 8.4 deprecation), `setId(null)` TypeError | `?string`, empty id gets a generated `tbl_xxxxxxxx` |
| `Table::setCaption()` | Stored, never rendered | `<caption>` rendered (escaped) |
| `Tr`/`Cell` ids | Cells kept the row index they were created with; `addTr()` re-indexes the row afterwards, so ids were stale; thead/tfoot rows collided with body row 0 | Ids derived at draw time: `tbl_3_2` for body, `tbl_head_0_1`, `tbl_foot_0_0` |
| `Tr::draw()`/`Cell::draw()` without a table | Fatal on `getTable()->getId()` | id attribute omitted |
| UI table attributes | Classes, styles, `data-*` params and ids unescaped | `Util::e()` on every attribute value |
| `Radio::getSelectedLabel()` | Built the getter name with `convertTableNameToClassName` (wrong for field names) | `Util::tableFieldNameToMethodName()` |
| `Radio::getOptions()` | Unbound criteria values, hard-coded `id` column, backticks absent, so PostgreSQL + non-`id` keys broke | Bound params, referenced column from the FK, engine quoting |
| `Database\Table` docblocks | Fields declared `string[]`, hiding real type errors from PHPStan | Correct types |
| `tests/BaseObjectTest::orm_store_and_retreive_row_with_fk` | Copy of the unique-duplicate test; never touched an FK | Replaced by six FK tests |

## 2. Features added (all additive)

### ForeignKey
- Correctly named aliases: `getReferencedTable()/setReferencedTable()`, `getReferencedField()/setReferencedField()` (the `*Referencing*` names stay; the generator still emits them).
- `getReferencedClassName()` — single implementation of "package name + table name → class", always with a leading `\`. `Field::getReferencedValue()`, `Select`, `Radio` now use it instead of three private copies.
- `getReferencedObject($id = null)`, `getReferencedTableMeta()`, `getReferencedFieldType()`, `resolveReferencedFieldName()` (declared column, else the referenced table's first PK column, else `id`).
- `isNullable()`, `getOnDelete()/setOnDelete()`, `getOnUpdate()/setOnUpdate()`, `cascadesOnDelete()`, `__toString()` (`table.col -> ref.col`).
- Constructor accepts a 5th argument `$referencingField`.
- Both inspectors read `DELETE_RULE`/`UPDATE_RULE` from `information_schema.referential_constraints` (same view on both engines); `ClassGenerator` emits `->setOnDelete('CASCADE')` only when the rule is not `NO ACTION`/`RESTRICT`, so existing generated code is unchanged for the common case.
- `Select` options are keyed by the FK's referenced column, not blindly by the target's first PK column (ASM has FKs to non-key columns such as `filialet.arka_primare → arka.numri`).

### PrimaryKey
- `isComposite()`, `getFieldCount()`, `getFieldNames()`, `isAutoIncrement()`, `getValues()`, `getOldValues()`, `hasValue()`, `setValues(scalar|array)` (named or positional), `getWhereClause(prefix, engine)`, `getBindings(prefix, useOldValues)`.

### BaseObject composite-key support (single-column behaviour unchanged)
- `new Model(['a' => 1, 'b' => 2])` or `new Model([1, 2])` loads a composite-key row; `select()`, `setRecordId()` accept arrays; `getRecordId()` returns the array only for composite tables.
- `bOfind()/bOfindOne()` select every key column and construct composite objects correctly; generated `listAllObjects()` does the same (`ClassGenerator` template updated; old generated code keeps working for single-column keys).
- `Form::processForm()` decides insert vs update on the whole key (`PrimaryKey::hasValue()`); `AuditLog` stores `"v1,v2"` in `record_id` for composite keys.
- `recordExists()` checks every key column on composite tables.

### `Identifier` (new, `src/Laf/Database/Identifier.php`)
`engine()`, `isMysql()`, `isPostgres()`, `sanitize()`, `quote()`, `quoteQualified()` — one quoting rule for both engines, used by `BaseObject::select/update/hardDelete`, `PrimaryKey`, `ForeignKey`, `Radio`.

### `Laf\UI\Table`
- `Table` implements `ComponentInterface` (via `ComponentTrait`), so it can be `addComponent()`-ed to `Div`, `Card`, `TabItem`, `AdminPage`; the legacy `addClass/addStyle/addParam` API renders together with `addCssClass/addCssStyleItem/addAttribute`.
- `Table::fromArray($rows, $headers, $id)`, `addRow()`, `bootstrap()`, `setResponsive()` (`.table-responsive` wrapper), `addTheadRow()/setTheadRows()/getTheadRows()` for multi-row headers, `__toString()`.
- `Tr::addCells($values, $header)`, `Tr::getHtmlId()`, sections (`body/head/foot`).
- `Cell::setText()` (escaped; constructor/`setData()` stay raw HTML by design), `alignRight()/alignCenter()/alignLeft()`, `getHtmlId()`.
- `addTr()` is fluent; `setData/setColSpan/setRowSpan` return `Cell`.

### Tests
- `tests/BaseObjectTest.php` rewritten: schema created once per class (`BOOLEAN`/`JSONB` on PostgreSQL, `tinyint(1)`/`json_valid` on MySQL), three tables (`dummy_table`, `code_table` with a VARCHAR key, `link_table` with a composite key + int FK + varchar FK), 44 tests covering FK acceptance/rejection/null/navigation/varchar targets/cache, string PK insert/load/update/delete and UUID generation, composite load/update-one-row/delete-one-row/finders/listing/cascade, `find()`/`findAsArray()`/`listAll*`, clone, `touch()`, required-field enforcement, `update()` on an unselected object.
- New fixtures `LafShell\CodeTable`, `LafShell\LinkTable` (+ Base); `BaseDummyTable` gained `required_field`, `updated_on`, `TypeJson`, `getParentIdObj()`, `listAllArray()/listAllObjects()`.
- `tests/Fixtures/regenerate.php` regenerates the fixtures from the real `ClassGenerator` into the gitignored `tests/Fixtures/generated/<engine>/` for diffing.
- `tests/KeysAndTableTest.php` (13 unit tests): `Identifier`, `PrimaryKey` helpers, `ForeignKey` aliases/metadata/class resolution/short-circuits, UI table fixes and features.

## 3. Compatibility

- Every previously public method and signature is kept; ASM's 51 generated Base classes use only `setField/setKeyName/setReferencingTable/setReferencingField`, `PrimaryKey::addField` and `getPrimaryKey()->getFirstField()`, all unchanged.
- Single-column tables produce the same SQL as before (`WHERE pk = :primaryKeyField`), now quoted per engine.
- `TableComponentTest` (46 tests) passes unchanged: attribute quoting, class joining, pretty-print whitespace and the `<!-- ENDOF Table … -->` comment are byte-identical; only thead/tfoot row ids changed (`tbl_head_0` instead of `tbl_0`), which nothing used.
- No PHP 8.1+ syntax.

Behaviour changes to be aware of: on composite-key tables `getRecordId()` returns an array (it returned the first column before, and updates/deletes were wrong); `Select`/`Radio` option values follow the FK's referenced column; `AuditLog.record_id` holds `"v1,v2"` for composite keys.

## 4. Verification

| Check | Result |
|---|---|
| `phpunit --testsuite Unit` | 418 tests, 1,300 assertions, green |
| `phpunit --testsuite Integration` MariaDB 12.2 | 96 tests, 283 assertions, green |
| `phpunit --testsuite Integration` PostgreSQL 18 | 96 tests, 283 assertions, green |
| `tests/Fixtures/regenerate.php` on both engines | generated classes lint clean; emit `TypeDecimal(8, 2)`, `TypeJson`, `TypeBool`, `->setOnDelete('CASCADE')` only on the cascading FK, composite-aware `listAllObjects()` |
| PHPStan level 5 on touched files | clean apart from three "ternary always true" defensive checks |
| `php -l` | all changed files |
