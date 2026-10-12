Typing pass over the Database core on `improvements1` (2026-10-12): Field, Table, PrimaryKey, ForeignKey and AuditLog get typed properties and return types, Field loses its tabs, getAttribute()/getPrimaryKey()/isPrimaryKey() stop misbehaving on unset data, getLoadedOnTs() and a working isValidValue() are added; PHPStan level 5 is clean on src/Laf/Database, forms and values render byte-identical, Unit, MariaDB and PostgreSQL suites green.

# Scope

`src/Laf/Database/Field/Field.php` (1,040 lines, tab-indented, 23 untyped properties, 29 methods without return
types), `Table.php`, `PrimaryKey.php`, `ForeignKey.php`, `AuditLog.php`, one line each in
`Field/AbstractFieldType.php` and `Field/FieldTypeFactory.php`. `BaseObject`, `Db` and `Query` were reviewed earlier
and are untouched. No app (asm, routerarena) and no LAF class subclasses any of the five, so return types could be
added without an override breaking.

# Defects fixed

| # | Where | Defect | Fix |
|---|---|---|---|
| F1 | Field | `getAttribute()` documented `string`, returned null for an unset key | `getAttribute(string $key): mixed`, null when unset (the test `FieldTest::attributes` pins it) |
| F2 | Field | `getPrimaryKey(): PrimaryKey` and `isPrimaryKey()` threw a TypeError / "method on null" for a table without a primary key | `getPrimaryKey(): ?PrimaryKey`, `isPrimaryKey()` false |
| F3 | Field | `isValidValue()` was an empty `@TODO` returning null | `bool`, true when `validate()` has no message |
| F4 | Field | `$loadedOnTs` was written and never read (PHPStan) | `getLoadedOnTs(): ?int` |
| F5 | Field | `addDbSelectionCriteria()` returned nothing although every other setter is fluent | returns `static` |
| F6 | Field | `getFormElement()` declared a form element in the docblock but a plain component natively; a type handing back a non-element was stored and returned | `getFormElement(): FormElementInterface`, a non-element from the type or the configured class is a `LogicException` |
| T1 | Table | `getPrimaryKeyCount()` and `isPrimaryKey()` crashed without a primary key | 0 / false |
| T2 | Table | `getField(null)` relied on PHP turning the null key into `''` | explicit null check |
| P1 | PrimaryKey | three `$field->getType() ? … : …` checks that can never be false (`getType()` is typed) | `hasType()` |
| A1 | AuditLog | `recordIdToString()` handled booleans its docblock excluded (PHPStan: `is_bool()` always false) | docblock widened |
| A2 | AuditLog | `getChanges()` promised `field: string` while filling it with `?? null` | `field: string|null` on `getChanges()` and `getChangeFor()` |
| X1 | AbstractFieldType, FieldTypeFactory | an `instanceof` that is always true, a `??` fallback on a complete map | `!== null`, fallback removed |

# Modernisation

- Typed properties everywhere (`mixed` for the two values, nullable scalars, typed static caches in ForeignKey and
  AuditLog); `required`/`unique`/`autoIncrement` are plain `bool` (the constructor's null means false, as `isRequired()`
  always reported).
- Return types on every method; fluent setters return `static`; `mixed` parameters where any value is accepted
  (`setValue()`, `loadValueFromDb()`, `setValueRaw()`, `setValueHTML()`, `setOldValue()`, `sanitize()`, `setAttribute()`).
- Narrowed parameters: `setName(?string)`, `setLabel/setPlaceholder/setHint/setInvalidValueErrorMessage(?string)`,
  `setAttribute(string, mixed)`, `setLoadedOnTs(?int)`, `Table::setName(?string)`, `Table::isPrimaryKey(string)`,
  `ForeignKey::setKeyName/setReferencingField(?string)`; the generator and both apps pass strings.
- `Field.php` indented with spaces, LF endings on every touched file (`Field.php`, `Table.php`, `AuditLog.php` were CRLF),
  docblocks rewritten where they described the wrong method ("Set table ref" on the getter), `Settings` import dropped.
- PHPStan level 5 on `src/Laf/Database`: 12 errors before (Field 3, PrimaryKey 3, AuditLog 3, AbstractFieldType 1,
  FieldTypeFactory 1, plus the 3 the typed property surfaced downstream), 0 after; still 0 on Form, Generator, Page,
  Container, Component, Traits and UI/Table.

# Compatibility

- Every public method keeps its name and arguments; return types only state what was returned before (`?string`
  for a label that was never set, `?PrimaryKey` for a table without one). `getPrimaryKey()` widening from
  `PrimaryKey` to `?PrimaryKey` is the one declared type that got wider; it threw before in that case.
- `Table::getNameRot13()` still returns the **plain** table name. Forms use it as their id and marker input, page
  scripts and the request contract depend on it, so the misnomer stays and is documented and pinned by
  `DatabaseCoreReviewTest::table_name_rot13_is_the_plain_name_for_forms`.
- Before/after render (scratchpad `dbcore_render.php`, old classes from `git show HEAD:` through a prepended
  autoloader): a DummyTable form in VIEW, INSERT, UPDATE and UPDATE with errors plus a dump of every field's
  value, old value, labels, flags, validation messages, referenced value, table and key strings, PK bindings and a
  deep clone. Byte-identical except the per-process CSRF token and the signed field-list hash.

# Verification

- `php -l` on the seven files; PHPStan level 5: no errors.
- Unit suite: 645 tests, 2,492 assertions (8 new in `tests/DatabaseCoreReviewTest.php`).
- Integration: MariaDB 151 tests / 685 assertions, PostgreSQL 151 / 686.

# Open items

- `Field::getType()` and `getTable()` still throw a TypeError on a bare field (`hasType()` / `hasTable()` are the
  guards); making them nullable would push null checks into every caller for no gain.
- `BaseObject::returnLeafClass()` and the generated `get*Val()` getters stay untyped on purpose (generated overrides).
- Next in the sequence: `src/Laf/Util` (UrlParser, Util, Settings, the empty exceptions), then `Filesystem/Document`,
  `UI/Table`, scaffolding.
