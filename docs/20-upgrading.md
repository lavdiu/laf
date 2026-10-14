# Upgrading from master to improvements1

LAF's `master` branch is the framework as tagged `v1.2.1` (2026-02-13) plus four README/composer commits. The
`improvements1` branch starts at that tag and carries the whole modernisation: a rewritten field-type layer, values
stored verbatim and escaped on output, a new `Query` finder instead of the old `QueryBuilder`, a reworked `Db`,
CSRF-protected and validating forms, `FormLayout`, a generator CLI that reads JSON column comments and turns marked
linker tables into many-to-many pickers, a rewritten grid and document store, and typed code throughout. Almost
every public method kept its name and arguments, so an application keeps running after `composer update`; this
chapter lists the places where it will not, the behaviour that changed under unchanged calls, and the order in which
to upgrade. Each section says what changed, what you must check or change (with before/after code where a call
changes) and what stays compatible. The full list of changes is in [`../CHANGELOG.md`](../CHANGELOG.md).

On this page:

- [Where you start from](#where-you-start-from)
- [The changes that need action, at a glance](#the-changes-that-need-action-at-a-glance)
- [Models: BaseObject](#models-baseobject)
- [Queries: QueryBuilder is gone, Query replaces it](#queries-querybuilder-is-gone-query-replaces-it)
- [Database access: Db](#database-access-db)
- [Field types and the values you get back](#field-types-and-the-values-you-get-back)
- [Escaping: stored verbatim, escaped on output](#escaping-stored-verbatim-escaped-on-output)
- [Forms](#forms)
- [Unique values](#unique-values)
- [FormLayout and generated pages](#formlayout-and-generated-pages)
- [Code generator](#code-generator)
- [UI: pages, containers and components](#ui-pages-containers-and-components)
- [Grid](#grid)
- [Documents](#documents)
- [Audit log](#audit-log)
- [Settings](#settings)
- [UrlParser](#urlparser)
- [Upgrade checklist](#upgrade-checklist)
- [asm and routerarena](#asm-and-routerarena)
- [Known open issues](#known-open-issues)
- [See also](#see-also)

## Where you start from

| You are on | Locked at | What the upgrade brings |
|---|---|---|
| `lavdiu/laf: dev-master` (routerarena) | `8f369d6` (master) | everything in this chapter |
| `lavdiu/laf: dev-improvements1` (asm) | `3b83f6f` (2026-10-12) | the last commits only: opt-in validation on store and the foreign-key Autocomplete (`962cfda`), "create new" and many-to-many pickers (`5517ccb`), the docs (`cec40b8`) and the unique keys over several columns (see [Unique values](#unique-values)) |

Check your own lock with `grep -A12 '"name": "lavdiu/laf"' composer.lock`. `git log --oneline <your ref>..improvements1`
in a LAF checkout lists what you are about to receive.

What does not change:

- The PHP floor stays `>=8.0` (no enums, readonly properties or other 8.1+ syntax in `src/`); the code is tested on
  PHP 8.1 to 8.5 under `error_reporting=E_ALL` and on MariaDB and PostgreSQL.
- The two-tier classes (`Base/BaseX.php` regenerated, `X.php` yours), the generated method names
  (`setXVal()`, `getXVal()`, `getXFld()`, `getXFormElement()`, `getXObj()`), `find()` / `findOne()`, `bOfind()` /
  `bOfindOne()`, `listAllArray()` / `listAllObjects()`, `recordExists()`, `canSoftDelete()`.
- The form request contract: hidden `form_submit` = table name, a marker input named after the table,
  `{form}_draw_mode`, every field posted under its rot13 name. Your JavaScript that reads `#gvgyr` or
  `[name="…"]` keeps working.
- Existing generated `Base/` classes and `.page` files keep working without regeneration. Regenerating is how you
  opt into most of the new output (see [Code generator](#code-generator)).
- `record_status_id = 0` is still what `softDelete()` writes; that is the convention, not a bug (the application
  carries a `record_status` row 0 when the column has a foreign key).

## The changes that need action, at a glance

| Area | Change | What to do |
|---|---|---|
| Queries | `Laf\Database\QueryBuilder` deleted | port to `Model::query()` ([details](#queries-querybuilder-is-gone-query-replaces-it)) |
| Escaping | values are no longer HTML-encoded when stored | escape your own `echo $obj->getXVal()` with `Util::e()` ([details](#escaping-stored-verbatim-escaped-on-output)) |
| UI | `TabItem` titles, `Dropdown` texts, `Html::setPageTitle()`, form labels/hints are escaped | pass icons through the icon argument instead of markup in the title ([details](#ui-pages-containers-and-components)) |
| Forms | every POST `processForm()` checks a CSRF token | hand-written HTML forms that post to `processForm()` must carry the token ([details](#csrf)) |
| Forms | `processForm()` validates and throws `FormValidationException` | catch it and redraw the form, or `setValidateOnSubmit(false)` ([details](#validation-and-error-display)) |
| Forms | a POST form ignores GET submissions | no LAF form may be submitted with GET unless you call `setMethod('GET')` |
| Pages | regenerated delete links carry a token in the query string | rewrite rules must keep the query string (`[QSA]`) |
| Generator | linker tables give many-to-many pickers only when marked with the table comment `{"linker": true}` (or declared with `belongsToMany()`); unmarked ones are reported as `Note:` lines | mark the linker tables you want pickers for before regenerating ([details](#linker-tables-opt-in-pickers)) |
| Grid | `grid.js` rewritten | copy `sample_files/grid.js` over your bundled copy; `{column}` placeholders are no longer substituted into `on*` attributes ([details](#grid)) |
| Documents | only raster images, PDF and plain text are served inline; storage failures throw | check `Document::upload()` callers ([details](#documents)) |
| Settings | `settings.use_pretty_url = "false"` now means false | check string values in ini/env settings ([details](#settings)) |
| Custom code | `FieldType` interface grew; `Form`, `Field`, `Table` and UI classes are typed | only matters for classes that implement or extend LAF classes |

## Models: BaseObject

Compatible: the constructor (`new Customer(5)`, `new Customer('code')`, `new Customer(['a' => 1, 'b' => 2])` for a
composite key), `store()`, `insert()`, `update()`, `hardDelete()`, `softDelete()`, `select()`, `reload()`,
`getRecordId()`, the static finders and the logger methods (`addLoggerDebug()` … `addLoggerEmergency()`).
`insert()`, `update()`, `hardDelete()`, `returnLeafClass()` and `__clone()` stay untyped on purpose so the overrides in
your models do not clash.

Behaviour that changed under the same calls (see [03-models.md](03-models.md) for the full API):

| Before (master) | Now |
|---|---|
| a `store()` with nothing changed wrote the row, `updated_on` and an UPDATE audit entry | nothing changed = nothing written, no audit entry |
| every column was sent on INSERT, `NULL` for unset ones, so database defaults never applied | unset optional columns are left out of the INSERT; `reload()` reads the defaults back |
| PostgreSQL ids came from `lastInsertId()` (`lastval()`, sometimes another table's value) | `INSERT … RETURNING` on PostgreSQL |
| `update()` / `hardDelete()` on a composite key used only the first key column (could touch several rows) | every key column is in the `WHERE` |
| statement failures were a bare `\Exception` (insert/update) or the raw `PDOException` (hardDelete) | `Laf\Exception\DatabaseException` (`getSql()`, `getSqlState()`, `isUniqueViolation()`, `isForeignKeyViolation()`, previous = the `PDOException`) |
| `canSoftDelete()` only for a `deleted` column | also for `record_status_id`: generated delete actions soft-delete those tables |
| a soft delete logged UPDATE + DELETE (or UPDATE only) | one DELETE entry carrying the status change |
| `hardDelete()` returned true even when the row was already gone | false, `afterDelete()` not called |
| `select()` on a missing id kept the previous row loaded | it unloads the object |
| `getXObj()` loaded only numeric keys and ran a query per call | loads string/UUID keys too; memoised per key value (`getXObj(true)` reloads); regenerate to get it |
| unique fields were a TODO | checked before every write (`UniqueFieldDuplicateValueException`), see [Unique values](#unique-values) |
| `created_by` / `updated_by` called `<package>\Person::getLoggedUserId()` and fataled without that class | `BaseObject::setActiveUserId()` first, then `Person::getLoggedUserId()` when the class exists, else null |
| `clone $obj` shared the `Field` objects with the original | deep copy (if your model overrides `__clone()`, call `parent::__clone()`) |
| getters returned what PDO returned | typed values after load: `int` for integer columns, `bool` for `tinyint(1)` / `boolean`, a numeric string such as `"1234.50"` for `decimal` once regenerated |

Strict comparisons are where the last row bites: `if ($emp->getIsActiveVal() === '1')` is false now; compare with
`true` or loosely.

New and opt-in: hooks (`beforeInsert()` … `afterLoad()`), `BaseObject::transaction()`, `load()` / `loadOrFail()`,
`isNew()` / `isDirty()` / `getChangedFields()`, `validate()`, `validateRecord()` and validation inside `store()`
(`orm.validate_on_store`, default false). Prefer the hooks over overriding `insert()` / `update()`.

## Queries: QueryBuilder is gone, Query replaces it

`src/Laf/Database/QueryBuilder.php` was deleted (`f62d6aa`). It quoted with backticks only, hydrated "objects" by
calling `setFieldValue()` on new instances (dirty, unloaded objects), and its eager loading called a stub.
`BaseObject::getQueryBuilder()` still exists and now returns the new `Laf\Database\Query`, so code that only used
`where()` / `orderBy()` / `get()` / `first()` keeps compiling; `join()`, `leftJoin()`, `with()`, `asObject()`,
`setCustomSql()`, `setDebug()`, `buildQuery()` and `getCachedQuery()` do not exist on `Query`.

Before (master):

```php
$rows = Customer::getQueryBuilder()
    ->where('city', '=', 'Boston')
    ->orderBy('name')
    ->limit(10, 20)
    ->get();                         // Customer objects filled through setFieldValue(), not loaded
$debug = $qb->getCachedQuery();      // ['sql' => ..., 'bindings' => ...]
```

After:

```php
$rows = Customer::query()            // getQueryBuilder() is an alias of query()
    ->where('city', 'Boston')        // the 3-argument form where('city', '=', 'Boston') works too
    ->orderBy('name')
    ->limit(10)
    ->offset(20)                     // limit() takes one argument now
    ->get();                         // loaded Customer objects (isLoaded() true), one SELECT
$sql = $query->getSql();             // and getBindings()
```

For joins, use SQL through `Db` (`Db::getAllAssoc($sql, $params)`) or a grid; `Query` is single-table by design. The
conditions are joined flat in the order given (`a AND b OR c`); group alternatives with `whereGroup()` /
`orWhereGroup()`. Column names are validated against the model, so a misspelt column throws instead of producing
SQL. Everything else (`whereContains()`, aggregates, `pairs()`, `paginate()`, `cursor()`, `chunk()`) is in
[04-queries.md](04-queries.md).

## Database access: Db

Compatible: `Db::getInstance()`, the positional constructor `new Db($host, $db, $user, $pass, $port)` with string
ports, `query()`, `prepare()`, `execute()`, the static helpers (`getOne()`, `getAllAssoc()`, `getRowAssoc()` …),
`getConnection()` for raw PDO work, `setSqlErrorLogger()` and the `SqlErrorLoggerInterface` methods,
`ATTR_EMULATE_PREPARES = false`, `''` fetched as null (now switchable with `database.empty_string_as_null`), and the
driver's `PDOException` for failed statements.

Changed:

- `database.port` and `database.engine` are optional (3306/5432, `mysql`); the engine accepts `mysql`, `mariadb`,
  `postgres`, `postgresql`, `pgsql`. An unknown engine throws before connecting.
- A failed `getInstance()` throws every time; master cached a dead instance after the first failure. Connection
  failures are `DbConnectException`, which now extends `\PDOException` (it was a plain `\Exception`), so
  `catch (\PDOException)` catches them too.
- `query()` / `prepare()` / `execute()` connect on demand and never return null.
- `getRowObject()` returns null instead of an empty `stdClass` when nothing matches.
- `hasError()` is true after a failure (it never was); the error getters are nullable.
- A missing SQL error log table no longer throws over the original error; the logger switches off with one
  warning. Each logged error gets a fresh logger object (master overwrote the first entry of a request), and
  entries raised inside a transaction are written after it ends.
- Nested `transaction()` calls run in savepoints; a lost connection outside a transaction is reopened and the
  statement retried once (`database.reconnect_on_loss`, default true; switch it off for non-idempotent batch scripts).

New: named connections (`database.<name>.*`, `Db::getInstance('<name>')`), typed bindings, `insert()` /
`insertGetId()` / `update()` / `delete()` for plain tables, schema helpers, a PSR-3 logger and a query log. See
[13-database.md](13-database.md).

## Field types and the values you get back

The `FieldType` layer was rewritten (`cec9e06`). Every type now converts in one place: `normalize()` (any PHP
value), `fromInput()` (form text, locale formats), `fromDb()`, `toDb()` (value + PDO type, used by insert and update),
`equals()` (change tracking) and `validate()`. New types: `TypeDecimal(precision, scale)`, `TypeEnum`, `TypeUuid`.

What you notice:

- `false` is stored as 0, never NULL (master wrote NULL for false on some paths).
- An integer field rejects `"1.7"` (master truncated it to 1); `$emp->setSalaryVal('abc')` throws
  `InvalidValueException`.
- Decimal columns become `TypeDecimal` after regeneration: exact rounding to the scale (`'1,234.5'` becomes
  `"1234.50"`), with `ext-bcmath` (suggested in composer.json) for exact arithmetic. Old `new TypeFloat()` literals in
  Base classes keep working until you regenerate.
- `timestamp` columns become `TypeDateTime` (they were textareas); `tinyint(1)` / `bit(1)` / `boolean` become
  `TypeBool`, other widths `TypeInteger` with their range; `enum` / `set` get a select / checkbox list.
- Date and time inputs show the locale formats (`locale.date.format`, default `'F d, Y'`); seconds are kept on
  round trips; the default time format is `g:i A`.

If you wrote your own type: the `FieldType` interface has the new methods above. Extend `AbstractFieldType` and
implement `convert()`, `getPdoType()` and `createFormElement()`; the five legacy methods (`isValid()`,
`getValueDbSanitized()`, `formatForDb()`, `getPdoType()`, `getFormElement()`) still exist and delegate. Neither asm
nor routerarena implements a type.

## Escaping: stored verbatim, escaped on output

On master, `TypeVarchar` / `TypeText` stored `htmlspecialchars(strip_tags($value))`: `Smith & <Sons>` was saved as
`Smith &amp; ` and the tags were lost. Now the value is stored exactly as typed (trimmed) and every LAF output sink
escapes it: inputs, selects, radios, textareas, view-mode values, table cells set with `setText()`, grid cells
(`innerText`). `Field::setAllowHtml(true)` is still the explicit opt-in for a column that holds HTML.

What this means for your code: anything you print yourself is now raw user input.

Before (safe by accident on master, because the stored value was already encoded):

```php
echo "<td>" . $company->getNameVal() . "</td>";
echo "<input value='" . $company->getNameVal() . "'>";
```

After:

```php
use Laf\Util\Util;

echo "<td>" . Util::e($company->getNameVal()) . "</td>";            // text and single-quoted attributes
echo '<input value="' . Util::attr($company->getNameVal()) . '">';   // double-quoted attributes
```

Rows written by master still contain `&amp;`-style text. `Util::e()` does not double-encode, so they display as
before; decoding them once with `html_entity_decode()` per text column is optional and an application decision.
Also check exports, e-mails and JSON APIs that relied on the encoded text: they now get the real characters.

Which LAF APIs take raw HTML and which escape is listed under
[UI: pages, containers and components](#ui-pages-containers-and-components) and in
[17-security.md](17-security.md#apis-that-take-raw-html).

## Forms

The request contract and every public `Form` method are kept. Underneath, five things changed that a page can feel.

### CSRF

master had no CSRF protection. Now every POST `processForm()` requires the token `_csrf_token_{form}` (one per
session and form name, reused across tabs and redraws) and throws `Laf\Exception\CsrfTokenException` when it is
missing or wrong. A form drawn by `Form::draw()` (`$page->addComponent($form)`) writes the token itself, so the usual
pages need nothing. Two cases need work:

- Hand-written HTML that posts to `processForm()`: add the hidden input.

  ```php
  // the page that draws the hand-written form
  $form = $person->getForm();
  ?>
  <form method="post">
      <input type="hidden" name="form_submit" value="person">
      <input type="hidden" name="person" value="1">
      <input type="hidden" name="person_draw_mode" value="update">
      <input type="hidden" name="<?= Util::e($form->getCsrfSessionKey()) ?>" value="<?= Util::e($form->getCsrfToken()) ?>">
      ...
  </form>
  ```

- A `Modal` that renders its own `<form>`: `$modal->addHiddenInput($form->getCsrfSessionKey(), $form->getCsrfToken())`.

The token lives in `$_SESSION`; `Form` starts the session when none is active and no output was sent yet. Start the
session in your bootstrap before any output, or the token cannot be stored. `CsrfTokenException` is not a
`FormValidationException` (see [Known open issues](#known-open-issues)); catch it separately if you want a friendly
message. `setCsrfProtection(false)` exists for API handlers that authenticate differently.

### Request reading

A form reads only the request of its own method (POST by default). master chose the array by where the marker was
found, so `?person=1&gvgyr=…` in a link could drive a POST form past any check. If one of your LAF forms really
submits with GET, call `$form->setMethod('GET')` on it. `processForm()` also stopped using `filter_input()`, so
`setRequest($data, $files)` and `buildRequest()` work for tests and imports.

### Signed field list and the mass-assignment guard

A drawn form posts `{form}_fields`, an HMAC-signed list of its editable elements. Booleans whose checkbox was drawn
disabled, read-only or not at all are now left alone when absent (master set them to false whenever the element was
among the form's components). `validate()` skips read-only and disabled elements.

`setRestrictToRenderedFields(true)` / Settings `form.restrict_to_rendered_fields` additionally ignores posted fields
outside that list. It is **off by default** so existing pages keep working; with it off, any column posted under its
rot13 name is applied (including `created_by`). Turn it on where you can; hand-written forms then need
`$form->signedFieldList([...rot13 names])` in the `{form}_fields` input.

### Validation and error display

`processForm()` now converts and validates the posted values before writing (required, length, range, precision,
`setMinLength()`, `setPattern()`, validators, the model's `validateRecord()`) and throws
`Laf\Exception\FormValidationException` with every message; the redrawn form shows `is-invalid`, the message under
the field and an error summary above the fields. Write refusals (missing required value, duplicate unique value)
become the same exception with the message on the field (`getPrevious()` is the original exception). On master the
invalid value was stored or the write failed with an uncaught exception.

Before (master pages):

```php
if ($form->isSubmitted()) {
    $id = $form->processForm();
    UrlParser::redirectToViewPage($id);
}
```

After:

```php
use Laf\Exception\FormValidationException;
use Laf\UI\Component\Alert;

if ($form->isSubmitted()) {
    try {
        $id = $form->processForm();
        UrlParser::redirectToViewPage($id);
    } catch (FormValidationException $ex) {
        // fall through: the form below is drawn again with the submitted values and the messages
        $page->addComponent(new Alert('', 'The record was not saved, please correct the fields marked below', Alert::Type_Danger));
    }
}
```

`$form->setValidateOnSubmit(false)` restores "let the database complain" for a page you cannot change yet.
Regenerated pages already contain the try/catch. Details: [06-forms.md](06-forms.md#validation-errors),
[05-validation.md](05-validation.md).

### Markup changes

Bootstrap 5 throughout: checkboxes are `form-check-input` (were `form-control`), radios `form-check` (were the
Bootstrap 4 `custom-control`), wrappers `mb-3`. `required` is no longer drawn on checkboxes and checkbox lists (the
hidden `0` satisfies NOT NULL). The hidden `form_submit` and marker inputs have the ids `{form}_form_submit` and
`{form}_marker` (were `form_submit` and `{table}`; the names are unchanged). Password values are never drawn back.
A `File` input in edit mode shows the stored file (`setShowCurrentFile(false)` hides it) and keeps the stored document
when no new file is uploaded (master wiped it on every update). Field labels, hints and placeholders are escaped.

### Foreign keys stay single, many values only through a linker table

A foreign key always holds one value: `Select::setMultiple()` is ignored on a foreign key and `CheckboxCollection`
draws radios on one. Several values come only from a linker table (`ManyToMany`, the pickers in
[08-relations.md](08-relations.md)). Neither asm nor routerarena called `setMultiple()` on a foreign key.

For foreign keys to big tables, `form.select_autocomplete_threshold` (default 0 = off) or the column comment
`"element":"Autocomplete"` switches to the `Autocomplete` input; a page with such a form must call
`$form->handleLookupRequest()` before any output (regenerated pages do).

## Unique values

Uncommitted on `improvements1` at the time of writing (the unique-key work in the working tree), so it reaches you
with the next commit:

- Single-column unique fields (`setUnique(true)`) are checked before every write; a taken value throws
  `UniqueFieldDuplicateValueException` with `getFieldNames()`.
- Unique indexes over several columns become `$this->getTable()->addUniqueKey('employee_project_unique',
  ['employee_id', 'project_id'])` in regenerated Base classes; `store()` checks the combination before the INSERT
  (only keys with a changed column on an update, so a row that already holds an old duplicate stays editable).
- Forms report taken values together with the other errors, on the first column of the key:
  `E-mail: this value is already in use`, `Team members + Projects: this combination is already in use`.
- A unique violation the model did not know about (an index the Base class has no `addUniqueKey()` for, or a
  concurrent request) is turned into a field message by `processForm()` instead of an uncaught `DatabaseException`
  (`BaseObject::uniqueViolationFieldNames()` reads the key from the driver message).

What to check: code that catches `DatabaseException` and tests `isUniqueViolation()` around `store()` on a table with a
composite unique index. After regeneration the model refuses the duplicate first, with
`UniqueFieldDuplicateValueException` (not a `DatabaseException`), so catch both:

```php
use Laf\Exception\DatabaseException;
use Laf\Exception\UniqueFieldDuplicateValueException;

try {
    $link->store();
} catch (UniqueFieldDuplicateValueException $ex) {
    // known key, refused before the statement: $ex->getFieldNames() === ['employee_id', 'project_id']
} catch (DatabaseException $ex) {
    if (!$ex->isUniqueViolation()) {
        throw $ex;
    }
    // an index the model does not know: $link->uniqueViolationFieldNames($ex)
}
```

See [02-code-generation.md](02-code-generation.md#unique-keys-over-several-columns) and
[05-validation.md](05-validation.md).

## FormLayout and generated pages

`$obj->getForm()` without arguments still returns the flat form with every field, so pages that lay out their forms
themselves are unaffected. `$obj->getForm($drawMode)` is new: it lays the form out through `getFormLayout()` on the
model (null = `FormLayout::auto()`), and `configureForm(Form)` runs at the end of every `getForm()`. Many-to-many
pickers are only added by a layout, so they appear on `getForm($drawMode)` forms, never on the flat one.

Regenerated pages (`generator_cache/pages/*.page`; live pages in `pages/` are never overwritten) differ from the
master template:

- the form comes from `$obj->getForm($drawMode)`; the hand-layout block is a comment;
- `processForm()` is wrapped in a try/catch for `FormValidationException` with the `record-not-saved` label;
- delete links carry the form's CSRF token in the query string and the delete action checks it (`record-not-deleted`
  flash otherwise): pretty-URL rewrite rules must pass the query string on (`RewriteRule ^(.*)$
  index.php?uriRewrite=$1 [QSA,L]`);
- one related-records tab per foreign key column that points at the table, linking to the child table's own page
  (config `module_for_table`), quoted identifiers in the grid SQL;
- flash messages through `Laf\UI\Flash` (the legacy `$_SESSION['flash_message']` / `['flash_type']` keys are still
  read by `Flash::drain()`);
- `$form->handleLookupRequest()` after `getForm()` (Autocomplete lookups);
- the page factory is configurable (`page_factory`, `page_factory_method`; default `{namespace}\Factory::GeneralPage()`).

Compare a regenerated page with your live one before copying anything over: [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md)
walks through one.

## Code generator

### Running it

`vendor/bin/laf-generate` is new (`bf1b714`). Your existing script that calls
`(new DatabaseGenerator($libPath))->generateEverything($labels)` keeps working; the CLI does the same with table
selection, a dry run and exit codes.

Before:

```bash
php app/util/generate_classes.php
```

After:

```bash
vendor/bin/laf-generate --bootstrap=data/config.php --lib=app/lib --list            # what would be written
vendor/bin/laf-generate --bootstrap=data/config.php --lib=app/lib --install-pages    # classes + pages
vendor/bin/laf-generate --bootstrap=data/config.php --lib=app/lib --tables=router --classes
```

`--exclude` replaces the default exclusions (`audit_log, sql_error, object_list, phinx_*`) instead of adding to
them; repeat them when you add your own. `TableInspector` was renamed `MysqlTableInspector` (the old name is a
deprecated alias); use `TableInspectorFactory::create($table)` to get the right inspector for the engine.
`DatabaseGenerator::getTables()` no longer lists views, and `generateEverything()` throws
`MissingConfigParamException` on a bad configuration instead of echoing and skipping. Full reference:
[02-code-generation.md](02-code-generation.md).

### What changes in a regenerated Base class

Model classes (`X.php`) are created once and never touched unless you pass `--force`. Base classes are rewritten;
compared with a Base class from the master generator, expect:

| master output | regenerated |
|---|---|
| constructor: `setRecordId($id)` + `is_numeric()` / type checks | `if ($id !== null && $id !== '') { self::select($id); }` |
| `->setMaxLength(255)` on every column (also integers and texts) | only on character columns with a length |
| `new TypeInteger()`, `new TypeFloat()` for decimals | `new TypeInteger('int')`, `TypeDecimal(12, 2)`, `TypeEnum([...])`, `TypeDateTime(true)`, `TypeBool` for `tinyint(1)` |
| `->setPlaceHolder(...)` | `->setPlaceholder(...)` (PHP method names are case-insensitive, both work) |
| `getXObj()` with `is_numeric()` and `new X(...)` per call | `getXObj(bool $fresh = false)` through `relatedObject()` |
| `listAllArray()` / `listAllObjects()` with hand-built unquoted SQL | one `query()` each |
| FK definitions only | `->setOnDelete('CASCADE')` when the rule is not NO ACTION/RESTRICT |
| JSON column comments ignored (except a broken `setDisplayField`) | `fields` setters and `form` options emitted (`$field->setLabel('E-mail')`, `setFormOptions([...])`, `setDisplayField(...)`) |
| nothing for composite unique indexes | `addUniqueKey(...)` |
| nothing for linker tables | `belongsToMany(...)` on both sides, for linker tables marked `{"linker": true}` |

On PostgreSQL the inspector now reads column comments, identity columns and single-column unique indexes; columns
of a composite unique index are no longer marked `setUnique(true)` each. A NOT NULL column with a database default is
still generated as required; add `{"fields":{"setRequired":false}}` to its comment when the default should apply.

### Linker tables: opt-in pickers

Many-to-many pickers come only from a linker table **that you mark**. A regeneration alone adds no picker: the
generator turns a linker table into a relation only when the table's own comment says `{"linker": true}`:

```sql
-- MySQL / MariaDB
ALTER TABLE person_group COMMENT = '{"linker": true}';
-- PostgreSQL
COMMENT ON TABLE person_group IS '{"linker": true}';
```

The marked table must also follow the linker rule (`JunctionDetector`): exactly two foreign keys to two different
tables and at most two data columns besides the key and the row metadata (`id`, `created_*`, `updated_*`,
`is_deleted`, `deleted`, `record_status_id`; metadata columns never count, also when they are foreign keys). A
marked table that does not follow it gets a generator warning and no picker. After regeneration both sides' Base
classes of a marked linker get

```php
// BasePerson
$this->belongsToMany("group")->through("person_group")->keys("person_id", "group_id")->label("Groups");
```

and every form built with `getForm($drawMode)` shows a multi-value picker (checkboxes; chips above
`form.select_autocomplete_threshold`); `processForm()` stores the row and the links in one transaction. A linker
with a NOT NULL data column without a default gets no picker and a generator warning.

The rule is structural, so it also matches tables that are not "tags": a stock table `(article_id, branch_id,
quantity)` or a login log `(person_id, device_id, ip_address, user_agent)` qualifies when its data columns are
nullable or have defaults. That is why the marker decides. Every unmarked table that follows the rule is reported
once per run as a note, which is no warning (`--strict` stays green):

```
Note: person_group looks like a linker table (group ⇄ person): mark it with the table comment {"linker": true} for many-to-many pickers
```

Read the notes of the first run: mark the tables you want pickers for, and leave the rest unmarked (or comment
them `{"linker": false}` to silence the note). `laf-generate --many-to-many=auto` (or Settings
`generator.many_to_many = auto`, `DatabaseGenerator::setManyToManyMode('auto')`) turns every table the rule finds
into pickers, as the generator did before the marker existed, except tables commented `{"linker": false}`;
`--many-to-many=off` writes no relation at all. Details: [02-code-generation.md](02-code-generation.md#linker-tables-and-many-to-many-relations).

A relation can also be declared by hand in the model class, without the marker or the generator
([08-relations.md](08-relations.md#many-to-many-declaring-a-relation)):

```php
// MyApp\Person, after parent::__construct($id)
$this->belongsToMany('group')->through('person_group')->keys('person_id', 'group_id')->label('Groups')->chips();
```

To switch one side of a marked linker off, or configure it, pick one:

- In the schema (permanent, survives regeneration): the comment of the linker column that points at the **picked**
  table. For "no Groups picker on the person form", comment `person_group.group_id`; for "no Persons picker on the
  group form", comment `person_group.person_id`.

  ```sql
  -- MySQL: MODIFY must repeat the column's existing definition
  ALTER TABLE person_group MODIFY group_id INT NOT NULL COMMENT '{"form":{"multi":false}}';
  -- PostgreSQL
  COMMENT ON COLUMN person_group.group_id IS '{"form":{"multi":false}}';
  ```

  The same comment configures a picker you keep: `{"fields":{"setLabel":"Grupet"},"form":{"element":"Autocomplete"}}`.
- In the model, for every form: `$this->getTable()->removeManyToMany('person_group');` in the leaf constructor
  after `parent::__construct($id)`, or refine the generated relation there with
  `$this->belongsToMany('group')->label('Grupet')->chips();`.
- In one layout: `FormLayout::auto($this->getTable())->exclude(['person_group'])`.

## UI: pages, containers and components

Rendered output is Bootstrap 5 (with the Bootstrap 4 `data-*` attributes kept next to the `data-bs-*` ones), every
attribute is escaped, and the typed setters return `static`. The rule for text arguments:

| API | master | now |
|---|---|---|
| `GenericPage` / `AdminPage::setTitle()`, `setSubtitle()`, `setHeader()`, `setFooter()` | raw HTML | raw HTML (unchanged; pages pass anchors) |
| `Link` caption, `Alert` title and message, `Modal` title, `HtmlContainer`, `Cell` constructor / `setData()` | raw HTML | raw HTML (unchanged) |
| `TabItem` title | raw HTML | **escaped**; the icon is the second argument |
| `Dropdown` text | raw | **escaped**; the icon is an argument |
| `Html::setPageTitle()` (`<title>`) | raw | **escaped** |
| form labels, hints, placeholders, option labels | raw | **escaped** |
| `Cell::setText()`, `Table::addRow()`, `Table::setCaption()` | (did not exist / not drawn) | escaped |

The `TabItem` change is the one that shows: a title with markup now displays the markup as text.

Before:

```php
$tab = new TabItem("<i class='fas fa-user-friends'>&nbsp;</i> Grupet");
```

After:

```php
$tab = new TabItem('Grupet', 'fas fa-user-friends');
```

The tab's DOM ids derive from the title's letters and digits, so they change too (`Grupet-tab` instead of
`iclassfasfauserfriendsnbspiGrupet-tab`); check CSS or JavaScript that targets them.

Other changes:

- `Html::addComponent()` and `GenericPage::addLink()` require a `ComponentInterface` (they were untyped).
- `GenericPage::enable()` / `disable()` work (they threw a `TypeError`); a page drawn twice no longer repeats its body;
  the component's attributes are drawn on the wrapper; `Page` (banner) renders instead of fataling.
- `Row` and `Column` no longer force their draw mode onto children that have their own; the explicitly active tab of a
  `TabContainer` wins over the first one; `HtmlContainer::addComponent()` throws a `LogicException` (use
  `addContent()`).
- `Link::setConfirmationMessage()` JavaScript-escapes the text (an apostrophe broke it); `Dropdown::leftAlign()`
  aligns left; `Carousel` returns its markup in every case.
- `Cell::setData(?string)`: an array is a `TypeError` now instead of the text "Array".
- Previous/Next tab buttons and every LAF script are vanilla JavaScript (no jQuery).

New and optional: `Html::addScript()` / `addStylesheet()` / `setFavicon()` / `setLanguage()`, card options on
`GenericPage`, `Modal` options, `Dropdown::addDivider()` / `addHeader()`, `Link::setDisabled()`, `Alert::setDismissible()`,
`Laf\UI\Flash`. See [11-pages-and-ui.md](11-pages-and-ui.md).

## Grid

`PhpGrid` was rewritten over `GridRequest` and `GridQuery` and `grid.js` as a Bootstrap 5 class (`0a84963`,
`feb14d9`). Every method an application calls keeps its name, arguments and fluent return, and the JSON payload keeps
the shape the old `grid.js` reads, so **new PhpGrid + old grid.js** and **new grid.js + old payload** both work
(verified in the browser with routerarena's copy).

Do:

- Copy `sample_files/grid.js` over your bundled copy and rebuild your assets.
- If an `ActionButton` attribute uses row placeholders inside an event handler
  (`addAttribute('onclick', 'del({id})')`), move the value into a data attribute: the new `grid.js` substitutes
  `{column}` placeholders everywhere except `on*` attributes.

  ```php
  // before
  $button->addAttribute('onclick', 'return confirm("Delete {name}?")');
  // after
  $button->addAttribute('data-name', '{name}')
         ->addAttribute('onclick', 'return confirm("Delete " + this.dataset.name + "?")');
  ```

Behaviour changes: LIKE is case-insensitive on PostgreSQL (`CAST(col AS TEXT) ILIKE`), `execute()` returns false
on an SQL error (`getErrorMessage()`), a refused or failed export answers 403/500, a column callback returning null
draws the default cell. The XLSX export runs on OpenSpout (`export_grid_to_excel=1` keeps working;
`exportToExcelPhpExcel()` / `exportToExcelSpout()` are deprecated aliases of `exportToXlsx()`), and
`phpoffice/phpspreadsheet` is no longer a LAF dependency: require it in your application if you use it yourself.
`setUseIlikeOnPg()` has no effect any more. See [10-grids.md](10-grids.md).

## Documents

`Laf\Filesystem\Document` was rewritten (`fe6a2f2`, `52d9b15`). Every public method keeps its name.

| Before (master) | Now |
|---|---|
| allowed types checked against the browser's `$_FILES[...]['type']` | MIME type read from the file (`finfo`), checked against `upload.allowed_mime_types` (`image/*` wildcards) |
| every upload resized as an image (a PDF failed silently) | only raster images are resized (`upload.max_image_size`, `upload.thumbnail_size`); other files stored unchanged, no thumbnail |
| `upload()` returned null on every failure | null only when nothing usable was posted; directory, image and database failures throw `RuntimeException` |
| every stored type served inline with its own Content-Type | only raster images, PDF and plain text inline; everything else (SVG and HTML included) as an `application/octet-stream` attachment; every response carries `Content-Security-Policy: sandbox; default-src 'none'` and `nosniff` |
| fixed 2030 `Expires` headers, whole file in memory | `ETag` / `Last-Modified` with 304, `Cache-Control: private, max-age=upload.cache_max_age`, streamed |
| missing file redirected to the Settings key `404` | `Document::setNotFoundUrl()` when set, else `upload.not_found_url`, else the legacy `404` key, else a 404 response |
| stored names `microtime_full.ext` | `YYYYMMDDHHMMSS_<12 hex>_full.ext` |

Check every `Document::upload()` caller for the new exception, and check that `upload.documents_directory` exists and is
writable. The `File` input keeps the stored document when no new file is posted. New: `uploadMany()` for
`name[]` fields, `storeFile()` for files already on disk. See [15-files-and-documents.md](15-files-and-documents.md).

## Audit log

The `audit_log` columns, action values and the JSON payload (`[{field, old_value, new_value}]`) are unchanged, so an
application's own model of the table and its archive scripts keep working. `migrations/create_audit_logs_table_postgres.sql`
is new.

Changed: no duplicate UPDATE entry for a second `store()` of an unchanged object; one DELETE entry per soft delete;
a failed hard delete logs nothing (master logged before the statement); INSERT entries skip columns left null;
`created_on` comes from the PHP clock like the rows' own timestamps; `user_id` falls back to
`<package>\Person::getLoggedUserId()`; a failed audit write returns false and is recorded in
`AuditLog::getLastError()` instead of being swallowed (`AuditLog::setStrict(true)` makes it throw). `new AuditLog($id)`
and the readers (`findForObject()`, `findForRecord()`) work now. See [14-audit-log.md](14-audit-log.md).

## Settings

Keys are unchanged; new optional ones were added. `Settings::get()` still throws `MissingConfigParamException` for a
missing key; the new readers take defaults.

| Key | Change |
|---|---|
| `database.port`, `database.engine` | optional now (engine default `mysql`, port by engine) |
| `database.charset`, `socket`, `persistent`, `connect_timeout`, `options`, `dsn`, `dsn_extra`, `timezone`, `schema`, `init_commands`, `empty_string_as_null`, `reconnect_on_loss`, `database.<name>.*` | new, optional |
| `settings.use_pretty_url` | read with `Settings::getBool()`: the string `"false"` (ini file, environment) now means false; master's `(bool)` cast made it true. Default still true |
| `upload.not_found_url` | new; replaces the legacy `404` key (still read as a fallback) |
| `upload.max_image_size`, `upload.thumbnail_size`, `upload.cache_max_age`, `upload.image_mime_types` | new, optional |
| `form.restrict_to_rendered_fields`, `form.native_date_inputs`, `form.select_autocomplete_threshold` | new, default off |
| `orm.validate_on_store` | new, default false |
| `audit.max_value_length` | new, default 0 (unlimited) |
| `locale.language`, `locale.decimal_separator`, `locale.thousands_separator` | new, optional (`locale.*.format` as before) |

`Settings::loadFromFile()` / `saveToFile()` work now (they were stubs returning false); `load()`,
`loadFromEnvironment('LAF_')` and `loadEnvFile()` are new. `sample_files/settings.php` lists every key. See
[16-settings-locale-utilities.md](16-settings-locale-utilities.md).

## UrlParser

Every static method keeps its name and arguments, and links for numeric ids are byte-identical in both URL modes.

- `getId()` returns `int|string|null`: an int for a numeric segment, the lower-cased UUID for a UUID segment, null
  otherwise (master: int or null). `getRawId()` gives the segment as written.
- `getViewLink($uuid)` / `redirectToViewPage($uuid)` use a UUID argument (master silently used the route's own id).
- Module, submodule, action and id are URL-encoded in links; array request values (`?id[]=1`) count as absent.
- `redirectToViewPage($id, false)` / `redirectToListPage(false)` return instead of exiting; `reset()` re-reads the
  request (tests, CLI).

See [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md#urlparser).

## Upgrade checklist

1. **Composer.** While `improvements1` is not merged, require `"lavdiu/laf": "dev-improvements1"` (routerarena:
   change it from `dev-master`); after the merge, `dev-master` (aliased `1.x-dev`) carries the same code. Run
   `composer update lavdiu/laf`. If your code uses PhpSpreadsheet, require `phpoffice/phpspreadsheet` yourself (LAF
   dropped it); `psr/log` now comes with LAF directly, Monolog stays.
2. **Search for removed and narrowed APIs:** `QueryBuilder` / `getQueryBuilder()->join|with|asObject|setCustomSql`,
   `new TableInspector(`, classes that implement `FieldType` or extend LAF form/UI classes,
   `catch (\PDOException` around `hardDelete()` (now `DatabaseException`).
3. **Session first.** Start the session in the bootstrap before any output (CSRF tokens and the form signing key live
   there); call `BaseObject::setActiveUserId()` for audit and `created_by`.
4. **CSRF on hand-written forms and modals** that post to `processForm()`: add the token input or
   `Modal::addHiddenInput()`. Hand-written delete links: build them with `$form->withCsrfToken($url)` and check
   `$form->isValidCsrfToken($_GET[$form->getCsrfSessionKey()] ?? null)` before deleting.
5. **Catch `FormValidationException`** around every `processForm()` and let the page redraw the form (or
   `setValidateOnSubmit(false)` per form until you do).
6. **Escaping review:** grep for `echo` / string concatenation of `get…Val()` into HTML and wrap them in `Util::e()` /
   `Util::attr()`; move markup out of `TabItem` titles into the icon argument; check `Html::setPageTitle()` callers
   that pass entities or markup.
7. **Settings:** check string values of `settings.use_pretty_url`; set `upload.not_found_url` if you use documents;
   decide on `form.restrict_to_rendered_fields` (recommended on, after step 4).
8. **Rewrite rule:** keep the query string (`[QSA]`) so delete tokens and grid parameters survive pretty URLs.
9. **grid.js:** copy `sample_files/grid.js` into your assets and rebuild; move `{column}` placeholders out of `on*`
   attributes.
10. **Regenerate the Base classes** into a scratch folder first and diff:
    `vendor/bin/laf-generate --bootstrap=<your bootstrap> --lib=/tmp/laf-preview --classes`, then
    `diff -r app/lib/<Ns>/Base /tmp/laf-preview/<Ns>/Base`. Read the `Note: ... looks like a linker table` lines,
    mark the linker tables you want pickers for with `{"linker": true}`, rerun and check the new `belongsToMany()`
    lines (see [Linker tables: opt-in pickers](#linker-tables-opt-in-pickers)). Look also for `addUniqueKey()` lines,
    changed field types (`TypeDecimal`, `TypeBool`) and `setDisplayField()` from comments. Then regenerate for real
    (`--classes`; model classes and live pages are kept).
11. **Pages:** regenerate into `generator_cache/pages/` and compare with your live pages; port the try/catch, the CSRF
    delete and `handleLookupRequest()` into pages you maintain by hand, or install the generated page for tables that
    have none (`--install-pages`).
12. **Audit log on PostgreSQL:** create the table from `migrations/create_audit_logs_table_postgres.sql`.
13. **Test:** run LAF's own suites if you changed LAF (`composer test`, `composer stan`; see
    [18-testing-and-tooling.md](18-testing-and-tooling.md)); in the application, click through one create, edit,
    view and delete per module, a form with a validation error, a grid search and export, and a file upload and
    download. `Form::buildRequest()` + `setRequest()` let you script form submissions in your own tests.

## asm and routerarena

Read from their code on 2026-10-14 (`D:\www\asm`, `D:\www\routerarena.com`); recheck before acting, the code moves.

### Both applications

- **`TabItem` titles with markup.** asm `app/pages/person.page` (6 tabs, e.g.
  `new TabItem("<i class='fas fa-user-friends'>&nbsp;</i> Grupet")`) and routerarena `app/pages/article.page`,
  `person.page`, `router.page` (24 tabs) pass `<i>` markup in the title. asm is locked at `3b83f6f`, which already
  escapes titles, so its person page shows the markup as text today. Move the icon class into the second argument.
- **Linker tables.** Linker tables are opt-in, so a regeneration adds no picker until a table is marked
  `{"linker": true}`. The committed Base classes show these candidates for the linker rule (the real schema, column
  nullability and extra foreign keys change the result; the first run's `Note:` lines list the real candidates):

  | Table | asm | routerarena | Mark it? | Picker on |
  |---|---|---|---|---|
  | `person_group` (person ⇄ group) | yes | yes | **mark** | person form "Groups", group form "Persons" |
  | `group_role` (role ⇄ group) | yes | yes | **mark** | role form "Groups", group form "Roles" |
  | `group_routing_table` (routing_table ⇄ group) | yes | yes | **mark** | routing table form "Groups", group form "Routing Tables" |
  | `article_tag`, `article_social_media_account` |  | yes | **mark** (probably wanted) | the article form and the other side |
  | `email_attachment` (email ⇄ document) | yes | yes | leave unmarked (probably unwanted) | |
  | `person_login_log` (person ⇄ person_login_device, + ip_address, user_agent) | yes | yes | leave unmarked: a log, not a relation | |
  | `artikujtgjendja`, `artikujt_cmimi_shitjes`, `artikujt_referenca`, `brendifurnizuesi` | yes |  | leave unmarked: stock and price data | |
  | `article_view`, `article_views`, `manufacturer_view` |  | yes | leave unmarked: view logs | |
  | `user_agent` (user_agent_os + a `record_status_id` foreign key) |  | no | | not a linker: metadata foreign keys do not count |

  The tables left unmarked simply appear as `Note:` lines on every run and get no picker; comment them
  `{"linker": false}` to silence the notes. `--many-to-many=auto` restores the old behaviour (every candidate gets
  pickers) if you prefer to switch the unwanted ones off one by one.

  ```sql
  -- MySQL / MariaDB (both applications)
  ALTER TABLE person_group COMMENT = '{"linker": true}';
  ALTER TABLE group_role COMMENT = '{"linker": true}';
  ALTER TABLE group_routing_table COMMENT = '{"linker": true}';
  -- routerarena
  ALTER TABLE article_tag COMMENT = '{"linker": true}';
  ALTER TABLE article_social_media_account COMMENT = '{"linker": true}';
  ```

  The pickers appear only on forms drawn with `getForm($drawMode)`. Both applications' pages mostly call `getForm()`
  and lay out themselves, so most pages will not show a picker until a page is regenerated or changed; the
  declarations are still in the Base class (and `$obj->manyToMany('person_group')` works). asm's own
  `person_permissions.page` is untouched by this. Label the marked ones in Albanian with `setLabel` on the linker
  column that points at the picked table, or with `->label()` in the model class.
- **grid.js.** Copy `sample_files/grid.js` to asm `assets/src/custom/js/grid.js` and routerarena
  `public/manage/assets/src/grid.js`, then rebuild. Neither application puts `{column}` placeholders into `on*`
  attributes.
- **Generator.** `vendor/bin/laf-generate --bootstrap=data/config.php --lib=app/lib --install-pages` replaces
  `app/util/generate_classes.php` (which keeps working); asm's `data/config.php` guards its session code with
  `php_sapi_name() !== 'cli'`, so it works as the bootstrap. routerarena's `regenerate_single_base.php` is
  `--tables=router --classes`.
- **CSRF.** Checked on 2026-10-12: every `processForm()` call site in both applications is on a form drawn by
  `Form::draw()`; the modals that post data use their own `$_POST` handlers (asm) or embed a LAF form that carries the
  token (routerarena). No change needed unless new hand-written forms post to `processForm()`.
- **`country.label`** carries `{"fields":{"setDisplayField":"label_al"}}`, which master ignored: after regeneration
  `label_al` is the display column of `country` (select options and labels change language).
- **Soft delete** writes `record_status_id = 0` as before; asm's `RecordStatus::DELETED` is 5 and five tables have a
  foreign key on the column, so the `record_status` row 0 must exist there. Regenerated delete actions soft-delete
  every table with `record_status_id`.

### asm only (locked at `3b83f6f`)

Most of this chapter already applies to asm. New with the update: `orm.validate_on_store` and `Autocomplete`
(both off by default), the pickers of the linker tables you mark, the unique keys. asm's 26 jQuery UI autocompletes keep working;
replacing them with `"element":"Autocomplete"` is optional. `Grup::__clone()` does not call `parent::__clone()`, so a
clone shares its `Field` objects with the original; add the call.

### routerarena only (locked at `8f369d6`, master)

Everything in this chapter is new to routerarena. In particular: validation on submit (no routerarena page catches
`FormValidationException`; add the try/catch or `setValidateOnSubmit(false)`), stored values no longer HTML-encoded
(its many hand-written views), the composer constraint (`dev-improvements1` until the merge), and `TabItem` titles.
`ArticleHandler.php` and `ArticleFromUrlHandler.php` use the return value of `insert()` (`true`) as the new id; that
was wrong on master too, the id is `getRecordId()`. routerarena's own `RouterArena\Document` does not use
`Laf\Filesystem\Document` and is unaffected by the document changes.

## Known open issues

Writing and running the examples for these docs (2026-10-14) turned up defects that are not fixed yet; the chapters
describe current behaviour. The full list with file and line is in
[`../agent/2026-10-14-docs-findings.md`](../agent/2026-10-14-docs-findings.md). The ones that matter when upgrading:

- **Generated related-records tabs are always empty** (B1): the generator escapes the id expression into a string
  literal. [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md#8-related-records-view-only) shows the
  workaround.
- **Generated grid links are query-string links** (B2) while `sample_files/settings.php` defaults to pretty URLs,
  and pretty links are root-absolute (break under a sub-path).
- **A bad CSRF token on a generated save is an uncaught `CsrfTokenException`** (B5), because it is not a
  `FormValidationException`.
- `Link` drops a `class`
  given in the attributes array (B3); `Alert` and `TabItem` ignore `addCssClass()` (B4).
- Grid: `setRowsPerPage(0)` becomes 10 without a `limit` in the request (B7) and a client can always ask for
  `limit=0` (all rows) despite `setMaxRowsPerPage()` (B8).
- Autocomplete: the `LafAutocomplete` helpers do not handle the dual list (B9); the search box of an integer key
  inherits `inputmode="numeric"` (B10).
- Objects loaded through a partial `Query::select()` cannot be stored (B11).
- Sharp edges rather than bugs: NOT NULL columns with a default are generated as required; `--exclude` replaces the
  default exclusions; `form.restrict_to_rendered_fields` is off by default; the generator and the runtime pick
  display columns by different rules; `Query::delete()` skips hooks and the audit log.

## See also

- [`../CHANGELOG.md`](../CHANGELOG.md): every change on `improvements1`, by type
- [02-code-generation.md](02-code-generation.md): `laf-generate`, column comments, linker tables, unique keys
- [03-models.md](03-models.md): `BaseObject`, exceptions, hooks
- [04-queries.md](04-queries.md): the `Query` builder that replaces `QueryBuilder`
- [06-forms.md](06-forms.md): CSRF, validation, the mass-assignment guard
- [08-relations.md](08-relations.md): many-to-many pickers and how to configure them
- [10-grids.md](10-grids.md): the PhpGrid JSON contract and `grid.js`
- [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md): anatomy of a regenerated page
- [17-security.md](17-security.md): escaping rules and the raw-HTML APIs
- [18-testing-and-tooling.md](18-testing-and-tooling.md): suites, `buildRequest()`, PHPStan
