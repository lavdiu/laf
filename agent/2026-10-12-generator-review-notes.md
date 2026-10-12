Review and rework of the code generators (ClassGenerator, PageGenerator, DatabaseGenerator and the table inspectors): unbound metadata queries, PostgreSQL comments/identity/composite-unique gaps, unescaped labels and comment values in generated code, unquoted identifiers, a delete action without CSRF, related tabs that ignored a second foreign key; plus a runtime FormLayout so models control their forms, column-comment form options on both engines, table selection and output control in DatabaseGenerator, and the first generator tests (unit with fake inspectors, integration on MariaDB and PostgreSQL).

# Generator review — 2026-10-12

Branch `improvements1`, working tree (not committed). Follows the Form review (`agent/2026-10-12-form-review-2-notes.md`)
and the Db review. Files: `src/Laf/Generator/{ClassGenerator,PageGenerator,DatabaseGenerator,TableInspector,
PostgresTableInspector,TableInspectorInterface}.php` (rewritten), new `AbstractTableInspector.php`,
`TableInspectorFactory.php`, `ColumnComment.php`, `src/Laf/UI/Form/FormLayout.php` (new), edits in `Form.php`,
`Database/BaseObject.php`, `Database/Field/Field.php`, `UI/Container/TabItem.php`, `Util/Util.php`,
`sample_files/class_generator.php`, `phpunit.xml`, `CLAUDE.md`; tests `FormLayoutTest` (19), `GeneratorUnitTest` (12),
`GeneratorTest` (4, Integration suite, both engines).

User decisions taken before the work: one related-records tab per (child table, FK column); delete links carry the
form's CSRF token; the form layout lives at runtime on the model (`FormLayout`), not in emitted page code.

## 1. How the generators are used (what had to keep working)

- asm and routerarena run `new DatabaseGenerator(__DIR__ . '/../lib/')->generateEverything($labels)` (asm passes Albanian
  labels, one with an apostrophe) from a CLI/web script, and a `generator.page` that drives `ClassGenerator` /
  `PageGenerator` per table with `setConfig([...])` and `setWriteOnLiveDirectory(true)`; routerarena also has
  `regenerate_single_base.php` (`saveBaseClassToFile()->saveClassToFile()`). Both autoload through Composer; the
  generated `autoload.php` is committed but unused (and did not parse, see C6).
- The apps' `Base/` classes and `.page` files come from an older generator and are only replaced when the user
  regenerates, so new output is opt-in. They call `getXObj()`, `getXFormElement()`, `getXFld()`, `::findOne()/::find()`,
  `recordExists()`, `canSoftDelete()`, `listAllArray()`; all of these are still emitted with the same signatures.
- The only JSON column comment in use is `{"fields":{"setDisplayField":"label_al"}}` on `country.label` (asm,
  routerarena); its value was ignored.

## 2. Defects found and fixed

### Inspectors

| # | Defect | Fix |
|---|---|---|
| I1 | table and schema names interpolated into every metadata query | bound parameters; schema = `DATABASE()` / `current_schema()` (honours `database.schema`) |
| I2 | PostgreSQL `COLUMN_COMMENT` was a literal `null`: JSON comments only worked on MySQL | `pg_catalog.col_description()` |
| I3 | `GENERATED AS IDENTITY` columns not auto-increment on PostgreSQL | `EXTRA = 'auto_increment'` for `is_identity = 'YES'` or `nextval(` defaults |
| I4 | PostgreSQL `is_unique` scalar subquery failed with two UNIQUE constraints on a column and flagged every column of a composite UNIQUE as `UNI`, so `BaseObject` refused valid rows with its single-column unique check | `EXISTS` over `pg_index` with `indnkeyatts = 1` (same meaning as MySQL's `UNI`) |
| I5 | referencing rows keyed `TABLE_NAME` on MySQL and `table_name` on PostgreSQL, one row per table, no FK column | one row per FK column: `table_name`, `column_name`, `constraint_name` (+ the upper-case keys for older callers) |
| I6 | `getDisplayColumnName()` TypeError on a one-column table; comment `setDisplayField` ignored | comment first (value = column of the table, else the commented column), then `label`, `name`, second, first; a table without columns throws a `RuntimeException` naming it |
| I7 | composite primary key: the last column won | `getPrimaryColumnName()` = first, new `getPrimaryColumnNames()` |
| I8 | PostgreSQL composite foreign keys produced a kcu×ccu cross product | `pg_constraint` with `unnest(conkey, confkey) WITH ORDINALITY` |
| I9 | interface declared only `inspect()`/`getColumns()`; three literal `== 'postgres'` comparisons (`pgsql`/`postgresql` fell through to the MySQL inspector) | interface with every method the generators call; shared `AbstractTableInspector`; `TableInspectorFactory::create()` via `Db::normalizeEngine()` |

### ClassGenerator

| # | Defect | Fix |
|---|---|---|
| C1 | comment values emitted as `"{$value}"`: a quote or `$` broke or injected code, `false` became `"1"`/`""`, one argument only | `ColumnComment::fieldSetters()` exports every value with `var_export()` cast to the setter's parameter type (reflection); a list spreads into arguments; unknown methods are warnings |
| C2 | `setDisplayField` value ignored | honoured when it names a column (`$this->getTable()->setDisplayField($this->getTable()->getField("label_al"))`) |
| C3 | `setMaxLength(255)` for every column without a length, `65535`/`4294967295` for text/json (became the `maxlength` attribute of textareas) and a duplicated line | emitted once, for character columns with a length |
| C4 | `getXObj()` loaded only `is_numeric` keys: varchar/uuid foreign keys always returned an empty object | `$value !== null && $value !== ''` |
| C5 | `getTableInspector()` returned null before `processBaseClass()` (TypeError); write result ignored; no directory creation | lazy inspector (constructor-injectable), `writeFile()` creates the directory and throws on failure |
| C6 | `DatabaseGenerator::generateAutoLoader()` emitted `str_replace('\', ...)`: every shipped `autoload.php` is a parse error | nowdoc template; test runs `php -l` on it |
| C7 | untyped properties, `@return null` docblocks, `@param int $id` on constructors taking strings/arrays, "Instructors constructor.", `returnLeafClass()` in every leaf class although `BaseObject` implements it, `setPlaceHolder` spelling | typed, documented; the leaf template keeps `find()/findOne()` and gains a commented `getFormLayout()` example |

### PageGenerator

| # | Defect | Fix |
|---|---|---|
| P1 | labels interpolated into single-quoted PHP and into `confirm('...')`: an apostrophe broke the page | `var_export()` for every label; the confirm message through `json_encode` with hex escapes |
| P2 | unquoted identifiers in the grid SQL: a table or column named `order`, `user`, `group` failed on both engines | `Identifier::quote()` for every table, alias and column |
| P3 | the id column and action buttons hardcoded `id` | the inspector's primary column |
| P4 | junction detection and ClassGenerator disagreed on "is a foreign key"; many-to-many grid assumed `{table}_id`; a child with two FKs to the same parent was a "self junction" | one `columnIsForeignKey()`; the other table's primary key; two FKs to the current table = a plain child table |
| P5 | one related tab per child table, filtered through the join alias of the first FK (wrong rows for a second FK to the same parent) | one tab per (table, column), filtered on the child's FK column; label `Child (Fk Column)` and grid `child_fkcolumn_list` when a table appears twice |
| P6 | delete ran on a plain GET link: any crafted link or image deleted a record | links through `$form->withCsrfToken()`, the delete case checks `isValidCsrfToken($_GET[...])` and otherwise flashes `record-not-deleted` |
| P7 | `#[Pure]` / `#[ArrayShape]` from an uninstalled package, untyped properties, inspector null before `generatePageFile()` | removed, typed, lazy per-table inspector cache (`addTableInspector()` for tests) |
| P8 | `Factory::GeneralPage()` hardcoded | config `page_factory` / `page_factory_method` |
| P9 | layout emitted into every page | `$obj->getForm($drawMode)` + `FormLayout` (§3); the commented block shows the hand-layout alternative |
| P10 | non-integer primary keys compared with `(int)UrlParser::getId()` in related grids | `(int)` for integer keys, `Db::getInstance()->quote()` otherwise |

### DatabaseGenerator

| # | Defect | Fix |
|---|---|---|
| D1 | `setLogger(null)` fatal | guarded |
| D2 | `getTables()` listed views on MySQL (no `table_type`), interpolated the database name, and every non-system schema on PostgreSQL | `Db::getTableNames()` (base tables of the current database/schema) |
| D3 | `ob_flush()` without a buffer → notice under E_ALL on the web | guarded by `ob_get_level()` |
| D4 | `mkdir 0777`; a bad config was reported by `echo` and silently skipped | 0755; `generateEverything()` throws `MissingConfigParamException` |
| D5 | no table selection, labels only through `generateEverything()`, progress only via `echo`, no list of written files | `setTables()`, `setExcludedTables()` (patterns with `*`), `setLabelTranslations()`, `setOutput(callable)`, `getGeneratedFiles()`, `getWarnings()` |
| D6 | autoloader always generated (Composer apps do not need it) | `setGenerateAutoloader(false)` |

Also: `Util::tableFieldNameToLabel()` stripped every `_id` substring (`user_identity` → "Userentity"); now only a trailing
`_id`. `sample_files/class_generator.php` passed an array where the constructor takes a path.

## 3. New functionality

- **`Laf\UI\Form\FormLayout`** (programmatic form control): an ordered list of sections (`fields()`, `fieldset()`,
  `tab()`, each with a column count and the draw modes it applies to) plus per-field options (`exclude()`, `readOnly()`,
  `hidden()`, `element()`), `toArray()/fromArray()`, `apply(Form, $mode)`. Read-only = the element drawn in VIEW mode
  inside the edit form (posts nothing, not in the signed field list, untouched on processing; the apps' own idiom);
  hidden = wrapper `d-none` (still posts). Columns are `Div(['row'])`/`Div(['col-lg'])` (never `Row`/`Column`, which
  overwrite child draw modes); tabs become one `TabContainer("{table}_form_tabs")` with a leading "General" tab.
  `FormLayout::auto($table)` reproduces the generator's heuristic and reads the fields' form options.
- **Model hooks**: `BaseObject::getForm(?string $drawMode = null, ?FormLayout $layout = null)` (no arguments = the old
  flat form), `getFormLayout(): ?FormLayout` (override in the model class), `configureForm(Form)` (runs at the end of
  every `getForm()`), `Form::applyLayout($mode, $layout = null)`.
- **Field form options**: `Field::setFormOptions()/getFormOptions()/getFormOption()`, `setFormElementClass()` (short
  name or class; a foreign key must stay a `Select`; replaces a cached element of another class), honoured by
  `getFormElement()`. `ClassGenerator` emits `$field->setFormOptions([...])` from the comment's `form` object.
- **Column comment** (`Laf\Generator\ColumnComment`, both engines): `fields` (any Field setter, typed arguments,
  `setType`, `setDisplayField`) and `form` (`hidden`/`readonly`/`exclude` as `true`, one mode or a list; `group`,
  `tab`, `order`, `element`); unknown keys, modes and classes become generator warnings.
- `Form::withCsrfToken($url)` / `isValidCsrfToken($token)`; `TabItem::setIdPrefix()` / `getContentId()` so a form
  "Details" tab and a related-records tab with the same title do not share DOM ids (titles are escaped now).
- Inspectors: `hasColumn()`, `getColumn()`, `isForeignKeyColumn()`, `getForeignKey()`, `getPrimaryColumnNames()`,
  `isInspected()`; generators accept an injected inspector (unit tests run without a database).

## 4. Compatibility

Kept: every public method and signature of the six classes (new optional parameters only), the config keys, the
generated method names and signatures, `getForm()` without arguments, the request contract of the forms,
`getReferencingTables()` rows still carrying `TABLE_NAME`. Behaviour changes, all additive or opt-in by regeneration:

- PostgreSQL inspector reads comments, identity columns and single-column unique indexes; composite unique columns are
  no longer `setUnique(true)`.
- `setDisplayField` honours its value: regenerating asm's `country` makes `label_al` the display field.
- Regenerated Base classes: `setMaxLength()` only for character columns, `getXObj()` loads string keys, `setPlaceholder`
  spelling, `if ($id !== null && $id !== '')` in the constructor; regenerated leaf classes have no `returnLeafClass()`.
- Regenerated pages: `getForm($drawMode)` + model layout, per-FK tabs, quoted SQL, delete token (the page refuses a
  delete without it: pretty-URL rewrites must pass the query string on), `exit` after the delete redirect.
- `DatabaseGenerator::getTables()` no longer returns views; `generateEverything()` throws on a bad config; the
  constructor takes a `string` path (an array was already broken).
- `TabItem::getId()` is typed `string`; `TabItem` titles are HTML-escaped.
- `Util::tableFieldNameToLabel()` keeps inner `_id` fragments.

## 5. Verification

| Check | Result |
|---|---|
| `php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Unit` (PHP 8.5.4) | 575 tests, 2,066 assertions (was 544): FormLayoutTest 19, GeneratorUnitTest 12 |
| Integration MariaDB 12.2 | 151 tests, 685 assertions (GeneratorTest: inspector keys/comments/identity/references, generated classes loaded and used for reads and writes, every `setSqlQuery` of the generated page executed on the engine, DatabaseGenerator selection and output) |
| Integration PostgreSQL 18.3 | 151 tests, 686 assertions |
| PHPStan level 5 | `Generator/*`, `FormLayout`, `Form`, `TabItem`, `BaseObject`: clean; `Field.php` and `Util.php` keep their pre-existing findings |
| `tests/Fixtures/regenerate.php` on both engines | MySQL and PostgreSQL output identical; every file parses |
| Smoke run | classes and pages for `dummy_table`/`code_table`/`link_table` generated on both engines into the scratchpad, all `php -l` clean |

## 6. Not done / next

- The hand-maintained `tests/Fixtures/LafShell/Base/*` differ from generator output (quotes, `setPlaceholder`,
  `TypeInteger('int')`, no `setMaxLength(255)`); regenerate and copy them over when the fixtures are next touched.
- App-side issues seen but outside this repo: both `generator.page` files pass the whole table list to
  `new Table($databaseTables)` in `generate_base_class_all`; `code_generator.page` references classes that do not exist;
  the committed `autoload.php` files do not parse (regenerating fixes them, Composer makes them unnecessary).
- PostgreSQL `getDisplayColumnName()` and the related-grid display column still pick `label`/`name` by name; a table
  comment could name it later.
- `FormLayout` has no per-field column width (the col-lg grid cannot express it) and no conditional visibility beyond
  `Form::setFieldVisibilityCondition()`.
