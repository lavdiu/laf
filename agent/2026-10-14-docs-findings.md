LAF bugs and oddities found while writing and running the docs/ examples (2026-10-14, improvements1); nothing fixed, the docs describe current behaviour.

Docs: `docs/README.md` + 18 chapters (~11,900 lines), example schema `docs/examples/schema.{mysql,postgres}.sql`, real
generator output `docs/examples/generated/`. Examples were run against a scratch MariaDB database `laf_docs`
(left in place; PostgreSQL `laf_docs` holds the schema only).

## Bugs (wrong behaviour)

| # | Where | Finding |
|---|---|---|
| B1 | `src/Laf/Generator/PageGenerator.php:596` (`sqlLiteral()`) vs `idExpression()` :549 | The generated related-tab SQL escapes the concatenation quotes: `WHERE jt.employee_id = \' . ((int)UrlParser::getId()) . \'` is a literal string, so **every "Related information" tab on generated view pages is empty**. `tests/GeneratorUnitTest.php:454` pins the escaped form, `tests/GeneratorTest.php:307` patches it out. Fix: a `:id` placeholder + `setParamsList()` (workaround shown in chapter 12). |
| B2 | generated pages + `UrlParser.php:226` | Generated grid links are hard-coded `?module=…&action=…` (ignored in pretty mode), but `sample_files/settings.php` defaults `use_pretty_url => true`; pretty links are root-absolute and break under a sub-path. |
| B3 | `Link` (attributes array) | `class` passed in the attributes array is dropped; the generated view page passes its Update/Delete button classes that way, so they never render. |
| B4 | `Alert::draw()`, `TabItem::draw()` | Ignore classes added with `addCssClass()`. |
| B5 | generated save handler + `CsrfTokenException` | `CsrfTokenException` is not a `FormValidationException`; the generated save path lets a bad token surface as an uncaught exception. |
| B6 | `src/Laf/Generator/ColumnComment.php:149` | `"element":"TransferList"` in a column comment is dropped with a warning (`FormLayout::resolveElementClass()` lacks the alias `ManyToMany` accepts). CLAUDE.md advertises it. |
| B7 | `PhpGrid.php:639` | `setRowsPerPage(0)` becomes 10 when the request has no `limit`, contrary to its docblock (0 = all rows). |
| B8 | `PhpGrid.php:441`, `GridRequest.php:55` | A client can always request `limit=0` (all rows) despite `setMaxRowsPerPage()` / `setAllowExport(false)`. |
| B9 | `autocomplete.js:745-771` | `LafAutocomplete.set/clear/add/remove` do not handle a dual list (`clear` throws); only `values()` does. `autocomplete.js:589` "At most N" is hard-coded English, not in `setTexts()`. |
| B10 | `Autocomplete.php:522-533` | The visible search box inherits the integer key's attributes (`inputmode="numeric"`, `step`, `min`, `max`): phones show a numeric keypad for a name search. |
| B11 | `BaseObject.php:1249` | Objects from a partial `Query::select()` cannot be stored: unselected required columns count as missing. |

## Design questions / sharp edges

- Generator: NOT NULL columns with a database default are generated as required (`ClassGenerator.php:190`); the docs
  schema uses `{"fields":{"setRequired":false}}`. Consider treating `COLUMN_DEFAULT` as "not required".
- Display field rules disagree: generator `AbstractTableInspector.php:150` (comment, label, name, 2nd column) vs runtime
  `Table.php:299` (name, label, description, 2nd column). Falling back to the 2nd column gives FK ids as labels.
- `FieldTypeFactory.php:91` unknown `setType` silently becomes TypeText; `:98` bare `tinyint` (MySQL 8.0.19+ reports no
  width) maps to TypeBool.
- `--exclude` replaces the default exclusions (so `--exclude=x` brings `audit_log` back); `getGeneratedFiles()` / the
  "N class files" summary count kept model classes too.
- `FormLayout::auto()->tab('X', [...])` does not move fields `auto()` already placed, so the tab stays empty.
- `form.restrict_to_rendered_fields` off by default: any column posted under its rot13 name is applied (a smuggled
  `created_by` was stored). `setReadonly()` still posts and applies; only `FormLayout::readOnly()`/`setDisabled()` protect.
- Default submit button is wrapped in `<div id='_container' class='mb-3 row'>` (empty, repeated id); Modal
  `addFooterButton()` needs `setWrapInDiv(false)`.
- `Column` always adds `col-sm`; `TabItem` ids come from the title only (clash for equal titles); `UrlParser::getModule()`
  default is `''` vs `'home'` by URL mode; `Flash::drain()` needs a started session.
- `setDebug(true)` on a grid sends stack trace + bindings to the client; `grid.js:1140` puts row values into hrefs
  unencoded; client-side exports key rows by label (equal labels collide).
- `Db::hasError()` stays true after later successes; `Identifier::quote()` strips invalid characters silently while
  `Db::identifier()` refuses them; `Settings::saveToFile()` writes the password; `Document::storeFile()` skips the MIME
  allow-list (by design); `sample_files/settings.php` puts documents under `public/`.
- `MissingFieldValueException` message ends in "Value provided is " (empty); `find()` with an unknown column returns
  `[]` while `Query::where()` throws; `Query::delete()` skips hooks and the audit log.
- CLAUDE.md says PostgreSQL tests use postgres/postgres; `docker-compose.yml` uses root/root on 5433.
- Default date format `F d, Y` (English month names) unless `locale.*` is set.
