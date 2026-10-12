# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

LAF (Lulzim Application Framework) is a PHP 8+ rapid application development framework using a **Database-First Active Record Pattern with Code Generation**. The framework automatically generates model classes and CRUD interfaces from database schemas, enabling rapid development of database-driven web applications.

## Development Commands

### Testing
```bash
# Unit suite: no database needed (run under E_ALL, the code must stay warning-free on PHP 8.5)
php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Unit

# Integration suite against MariaDB or PostgreSQL (tests/bootstrap.php reads LAF_DB_*)
LAF_DB_ENGINE=mysql LAF_DB_HOST=127.0.0.1 LAF_DB_PORT=3306 LAF_DB_NAME=laf_test LAF_DB_USER=root LAF_DB_PASS= \
    php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Integration
LAF_DB_ENGINE=postgres LAF_DB_PORT=5432 LAF_DB_USER=postgres LAF_DB_PASS=postgres \
    php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Integration

# One test file
vendor/bin/phpunit tests/BaseObjectTest.php
```
`docker-compose up -d` provides both databases (MariaDB on 3307, PostgreSQL on 5433).

### Code Quality
```bash
# Static analysis: phpstan.neon = level 5 on src/, PHP 8.0 floor, src/Laf/UI/Grid excluded until it is reviewed; must stay at 0 errors
vendor/bin/phpstan analyse

# Rector is advisory only: rector.php = PHP 8.0 set + dead-code + code-quality on src/ and tests/, apply suggestions by hand
vendor/bin/rector process --dry-run
```
Both write their caches to `var/` (ignored).

### Code Generation
```bash
# Generate model classes from database schema: copy sample_files/class_generator.php into the application
# (asm and routerarena keep it as app/util/generate_classes.php) and run it
php app/util/generate_classes.php

# This creates/updates:
# - Base/Base[TableName].php (auto-generated, overwritten on regeneration)
# - [TableName].php (user customization layer, created once)
# - CRUD page files (.page extension)
```
`new DatabaseGenerator($libraryPath)` reads the namespace from `project.package_name`; `setTables([...])` /
`setExcludedTables(['audit_log', 'phinx_*'])` select tables (base tables of the current database/schema, no views),
`setLabelTranslations()`, `setOutput(callable)`, `setGenerateAutoloader(false)` (Composer apps), `getGeneratedFiles()`,
`getWarnings()`. Per table: `ClassGenerator` / `PageGenerator` (`new X(new Table($name), $config)`, optional injected
`TableInspectorInterface`; `TableInspectorFactory::create($table)` picks `MysqlTableInspector` or `PostgresTableInspector`). Column metadata is the
same shape on both engines (upper-case information_schema keys + `FOREIGN_KEY`; PostgreSQL reads the current schema,
`COMMENT ON COLUMN`, identity columns as auto-increment, single-column unique indexes as `UNI`). JSON column comments
drive the generated field: `{"fields":{"setLabel":"Customer","setHint":"...","setRequired":false,"setType":"uuid",
"setDisplayField":"label_al"},"form":{"readonly":"update","hidden":true,"exclude":["view"],"group":"Address",
"tab":"Pricing","order":20,"element":"TextArea"}}` (see `Laf\Generator\ColumnComment`; `form` lands in
`Field::setFormOptions()` and is read by `FormLayout::auto()`). Generated pages get the form from
`$obj->getForm($drawMode)` (laid out by the model, see Forms), one related-records tab per foreign key column that
points at the table (a junction table shows the table behind it), quoted identifiers in the grid SQL, and delete links
carrying the form's CSRF token (`Form::withCsrfToken()` / `isValidCsrfToken()`; pretty-URL rewrites must keep the
query string). Config keys `page_factory` / `page_factory_method` replace `{namespace}\Factory::GeneralPage()`.

### Dependencies
```bash
# Install dependencies
composer install

# Update dependencies
composer update
```

## Architecture Overview

### Two-Tier Inheritance Pattern

LAF uses a critical two-tier class hierarchy to separate generated code from customizations:

```
BaseObject (abstract ORM foundation)
    ↓
Base/Base[TableName].php (auto-generated from schema)
    ↓
[TableName].php (developer customizations)
```

**IMPORTANT**: Never modify files in `Base/` directories - they are regenerated from the database schema. All customizations go in the main class files.

### Core Components

#### 1. Database/ORM Layer (`src/Laf/Database/`)

**BaseObject** is the foundation providing Active Record functionality:
- CRUD operations: `select()`, `insert()`, `update()`, `store()`, `hardDelete()`, `softDelete()`
- Query building: `find()`, `findOne()`, `bOfind()`, `bOfindOne()`, `findAsArray()`, `query()` (single-table `Query`)
- Lifecycle hooks: `beforeInsert/afterInsert/beforeUpdate/afterUpdate/beforeDelete/afterDelete/afterLoad`
- State: `isNew()`, `isDirty()`, `getChangedFields()`, `isLoaded()`, `validate()`, `transaction()`
- Automatic audit logging of all data changes
- Automatic timestamp management (`created_on`, `updated_on`, etc.)
- Foreign key validation and object navigation
- Change tracking via `hasChanged()` on fields

**Key Classes**:
- `Table`: Metadata representation of database tables
- `Field`: Column definitions with validation rules and type information (typed since 2026-10-12: getters for an
  unset label/hint/placeholder/attribute return null, `getPrimaryKey()` null without a key, `getLoadedOnTs()` /
  `getUpdatedOnTs()`, `isValidValue()` = `validate()` empty; `Table::getNameRot13()` is the plain table name on purpose,
  forms use it as id and marker)
- `FieldType`: Strong typing system (TypeInteger, TypeVarchar, TypeDate, TypeBool, etc.)
- `ForeignKey`: Manages relationships between tables
- `Query`: single-table fluent finder (where/orderBy/limit, get/first/count/exists), engine-aware quoting, typed bindings
- `Identifier`: the one quoting rule (backticks on MySQL, double quotes on PostgreSQL)
- `Db`: Singleton PDO wrapper for database connections
- `AuditLog`: Automatic change tracking (requires `BaseObject::setActiveUserId()`)

#### 2. UI Component System (`src/Laf/UI/`)

Hierarchical component-based rendering:
- **Form**: Auto-generates forms from BaseObject fields, processes submissions via `processForm()`
- **Grid Components**: SimpleTable, PhpGrid for data tables with pagination
- **HTML table** (`src/Laf/UI/Table/`): `Table` is a ComponentInterface (`Table::fromArray($rows, $headers)`,
  `addRow($values)` escaped, `bootstrap()`, `setResponsive()`, `setCaption()`, `addTheadRow()`, `getTfoot()`), rows are
  `Tr` (`addCells($values, $header)`), cells `Td` / `Th` (`Cell`): `setText()` is escaped, the constructor and
  `setData()` are raw HTML, `alignRight()` etc.; ids `tbl_{row}_{col}` come from the table id. Typed since 2026-10-12,
  fluent setters return `static`.
- **Containers** (`src/Laf/UI/Container/`): `GenericContainer` (= `Div`) draws its children in order, hands its draw
  mode only to children without one, pushes the row display mode down, renders its attribute bag (`setAttribute('id', …)`,
  `data-*`) and `setTag('section')`; `Row` / `Column` (Bootstrap grid), `Card` + `CardHeader/Body/Footer`, `Fieldset($legend)`,
  `HtmlContainer($html)` (raw, `addContent()`, holds no children), `TabContainer($id)` + `TabItem($title, $icon)`
  (`setActiveTab()`, `getTabs()`, Previous/Next buttons, ids `{id}_tab_links` / `{title}-tab`), `Modal($id)` (title raw HTML,
  `setSize(Modal::SIZE_*)`, `setCentered()`, `setStaticBackdrop()`, `setIncludeForm()` + `addHiddenInput()` for a CSRF token,
  `addFooterButton()`, `getModalOpenButton($label)`)
- **Components** (`src/Laf/UI/Component/`): `Link($text, $href, $icon, $attributes, $classes)` (caption raw HTML,
  `setConfirmationMessage()` JS-escaped, `setTarget(Target::*)`, `setRel(Rel::*)`, `setDisabled()`), `Dropdown($text, $class,
  $icon, $rightAlign)` (`addLink()`, `addDivider()`, `addHeader()`, `addHtml()`, `setId()` names the toggle), `Image($src, $alt)`
  (`setHeight/Width()` as px style, `setLazy()`), `Alert($title, $message, Alert::Type_*)` (raw HTML, `setDismissible()`,
  `setIcon()`), `Carousel($documentIds, $id, $imageUrls)` (`setDocumentUrlBuilder()`, `useBgImages()`). Unbound elements
  return null from `getField()`.
- **Page Components** (`src/Laf/UI/Page/`): `Html` is the document (`setPageTitle()` escaped, `addHeaderComponent()`,
  `addScript($src, $attrs, $atBodyEnd)` / `addInlineScript()` / `addStylesheet()` / `addInlineStyle()` / `addMeta()` /
  `addLinkTag()` / `setFavicon()`, `setLanguage()` else Settings `locale.language` else `en`, `setBodyAttributes()` /
  `addBodyCssClass()`, `setMenu()` raw HTML, `addComponent()`, `addFooterComponent()` / `setFooterHtml()` before the
  body-end scripts, `draw(true)` stops after the components). `GenericPage` / `AdminPage` is the card every page puts
  its components into: `setTitle()` and `setSubtitle()` are **raw HTML** (pages pass anchors), `setTitleIcon()`,
  `addLink()` (Link / Dropdown, right side), `addFooterComponent()` (card footer), `setHeader()` / `setFooter()` raw
  HTML around the card, `setShowCardHeader(false)`, `setCardCssClasses()` / `setCardHeaderCssClasses()`, the
  component's attributes land on the wrapper div, `disable()` draws nothing. `Page` is the banner variant
  (`setBannerImage()`). `HeaderComponent` is one head tag (`::script()`, `::stylesheet()`, `::meta()`, `::link()`,
  `::inlineScript()`, `::inlineStyle()`; a null attribute value draws a bare attribute such as `defer`; `meta`/`link`/
  `base` are always void).
- **Draw Modes**: `DrawMode::VIEW`, `DrawMode::INSERT`, `DrawMode::UPDATE`

All components support fluent interfaces and CSS/attribute management.

#### 3. Code Generators (`src/Laf/Generator/`)

- **ClassGenerator**: Generates Base and main class files from database schema (JSON column comments → field setters and form options)
- **PageGenerator**: Creates complete CRUD pages with action-based routing (model-driven form layout, per-FK related tabs, CSRF-protected delete)
- **DatabaseGenerator**: Orchestrates generation for entire database (table selection, output callback, file list)
- **MysqlTableInspector** / **PostgresTableInspector** (via **TableInspectorFactory**; `TableInspector` is a deprecated alias of the MySQL one): same-shaped table metadata on both engines; **ColumnComment** parses the JSON comment

#### 4. Routing System (`src/Laf/Util/UrlParser.php`)

URL structure: `/module/submodule/action/id`

- `module`: Main functional area
- `submodule`: Specific entity/table (maps to .page file)
- `action`: CRUD operation (`list`, `view`, `new`, `update`, `delete`)
- `id`: Record identifier

Generated pages use switch statements to route actions.

`UrlParser::getId()` is an int, a lower-cased UUID or null; `getRawId()` is the segment as written. Link builders
(`getViewLink($id)`, `getUpdateLink($id)`, `getDeleteLink($id)`, `getListLink()`, `getNewLink()`, generic
`getLink($action, $id, $withId)`) accept a number or a UUID and fall back to the route's id; module and submodule are
URL-encoded. `UrlParser::reset()` re-reads the request (tests, CLI).

#### 5. Stored files (`src/Laf/Filesystem/Document.php`)

`Laf\Filesystem\Document` wraps the application's `<package>\Document` model (columns `file_name`, `file_name_original`,
`file_extension`, `thumbnail_name`, `file_size`, `mime_type`, optional `encrypt_key`; `Document::setModelClass()`
overrides the class). `Document::upload('field')` stores the file posted in a form field (or a `$_FILES`-shaped array
given as second argument) and returns the new id, or null when nothing usable was posted;
`Document::uploadMany('field')` takes a multi-file input (`name="field[]" multiple`) and returns the ids stored, in
posted order; `Document::storeFile($path, $originalName)` stores a file that is already on disk. The MIME type is read from the file (`finfo`), never from the
request, and checked against `upload.allowed_mime_types` (exact types or `image/*`); images are resized to
`upload.max_image_size` (1200) with a `upload.thumbnail_size` (300) thumbnail, other files are stored unchanged.
Storage failures throw `RuntimeException` (files written so far are removed). `new Document($id)` gives
`getModel()`, `getId()`, `recordExists()`, `fileFullSizeExists()`, `fileThumbnailExists()`, `getFullPathFullSize()`,
`getFullPathThumbnail()`, `getMimeType()`, `isImage()` (`upload.image_mime_types`), `hardDelete()` (record, then
files) and `handleRequest('view'|'download'|'thumbnail')`, which streams the file with `Content-Type`,
`Content-Length`, `Last-Modified`, `ETag` (304 on a conditional request), `Cache-Control: private, max-age=
upload.cache_max_age` and a `Content-Disposition` named after the original file, then exits (`handleRequest($action,
false)` returns instead). Only raster images, PDF and plain text are sent inline; SVG, HTML and every other type go
out as an `application/octet-stream` attachment under a sandboxing Content-Security-Policy (uploads never run as a
page of the site). A missing record, an encrypted record or a missing file redirects to
`upload.not_found_url` (fallback: the legacy `404` key, `Document::setNotFoundUrl()`), or answers 404 when none is set.
`upload.documents_directory` must exist and be writable; the path is joined with exactly one slash.

### Application Bootstrap Pattern

```php
// 1. Configuration
Settings::set('database.hostname', 'localhost');
Settings::set('database.database_name', 'mydb');
Settings::set('database.username', 'user');
Settings::set('database.password', 'pass');
Settings::set('database.engine', 'mysql'); // or 'postgres'
Settings::set('project.package_name', 'MyApp'); // Namespace for generated classes

// 2. Set active user for audit logging
BaseObject::setActiveUserId($_SESSION['user_id']);

// 3. Route to page files based on URL
// Pages live in namespace packages and use .page extension
```

### Working with Generated Classes

**Creating/Reading Records**:
```php
// Create
$customer = new Customer();
$customer->setNameVal('John Doe');
$customer->setEmailVal('john@example.com');
$customer->store(); // Auto-determines insert vs update

// Read
$customer = new Customer(123); // Constructor auto-loads record ID 123

// Query
$customers = Customer::find(['city' => 'New York']);
$customer = Customer::findOne(['email' => 'john@example.com']);
```

**Complex Queries** (single table; column names are validated against the model, values bound through the field types):
```php
$results = Customer::query()
    ->where('city', 'New York')          // = (null → IS NULL, array → IN)
    ->orWhere('city', '=', 'Boston')
    ->where('balance', '>', 0)
    ->whereNull('deleted_on')
    ->orderBy('name', 'ASC')
    ->limit(10)
    ->get();                             // Customer[] from one SELECT; also first(), arrays(), column(), count(), exists(), delete()
```

**Write path**: `store()` = `insert()` when `isNew()` else `update()` (only changed fields; nothing changed = nothing written).
Unset nullable columns are left out of the INSERT so database defaults apply (`reload()` reads them back). `created_on/by`
and `updated_on/by` are filled automatically (user from `BaseObject::setActiveUserId()`, else `<package>\Person::getLoggedUserId()`).
Required and single-column unique constraints are checked before the statement (`MissingFieldValueException`,
`UniqueFieldDuplicateValueException`); statement failures throw `Laf\Exception\DatabaseException` (`getSql()`, `getSqlState()`,
`isUniqueViolation()`, `isForeignKeyViolation()`, previous = the `PDOException`). Override the protected hooks
`beforeInsert()`, `afterInsert()`, `beforeUpdate()`, `afterUpdate()`, `beforeDelete($mode)`, `afterDelete($mode)`, `afterLoad()`
in a model class instead of `insert()`/`update()`. Group writes with `BaseObject::transaction(fn () => ...)` (nested calls
join the outer transaction).

**Form Handling**:
```php
$customer = new Customer(UrlParser::getId());
$form = $customer->getForm(DrawMode::UPDATE); // laid out by the model; getForm() alone = every field, flat

if ($form->isSubmitted()) {
    try {
        $id = $form->processForm(); // reads $_POST, validates, inserts or updates
        UrlParser::redirectToViewPage($id);
    } catch (\Laf\Exception\FormValidationException $ex) {
        $errors = $ex->getErrors(); // field => messages; the redrawn form shows them (is-invalid + invalid-feedback)
    }
}

$page->addComponent($form);
```
Form layout: `$obj->getForm($drawMode)` applies `$obj->getFormLayout()` (override in the model class; null =
`FormLayout::auto($table)`: columns by field count, `created_*`/`updated_*` left out of insert/update, metadata in a
"Details" tab on view, plus the fields' form options from the column comments) through `Form::applyLayout($mode)`.
`FormLayout` (fluent, `toArray()/fromArray()`): `fields($names, $columns, $modes)`, `fieldset($legend, ...)`,
`tab($title, ...)`, `exclude($names, $modes)`, `readOnly($names, $modes)` (element drawn in VIEW mode inside the edit
form: posts nothing, untouched on processing), `hidden($names, $modes)` (wrapper `d-none`, still posts),
`element($name, $class)`; unlisted fields are appended. The layout owns the elements' draw mode and hidden state; the
`configureForm(Form)` hook on the model runs at the end of every `getForm()`. Existing pages that call `getForm()` and
lay out themselves are unaffected.
Forms: every field element comes from `Field::getFormElement()` (`FormElementFactory::create()`); inputs render Bootstrap 5
markup and never change their own state when drawn; a field value change is mirrored into its element, so a form drawn
after a submission shows what was submitted. A form reads only the request of its own method (POST by default, so a GET
submission is ignored) or the arrays given to `setRequest($data, $files)`. `processForm()` = `detectSubmission()` + CSRF
(one token per session and form, `CsrfTokenException`) + `applySubmittedValues()` + `validate()` (opt-out
`setValidateOnSubmit(false)`) + insert/update; a write refused for a missing required or a duplicate unique value is also a
`FormValidationException` (message on the field, `getPrevious()` = the original exception). The drawn form posts a signed
list of its editable elements (`{form}_fields`): a boolean drawn disabled or read-only, or not drawn at all, is left alone
when absent, and `setRestrictToRenderedFields(true)` / Settings `form.restrict_to_rendered_fields` ignores posted fields
outside that list (mass-assignment guard; values from `setSubmittedFieldValue()` always apply). When the form has errors it
draws an alert above the fields (`setShowErrorSummary(false)`, `drawErrorSummary()`) and `is-invalid` on the elements;
`addError($field, $message)` adds an application message. A checkbox posts a hidden `0` so unchecked is distinguishable from
"not on the form"; `Select`/`Radio`/`CheckboxCollection` take `setOptions()` or the FK table; Date/DateTime/Time inputs show
the locale format (`locale.*.format`) with a picker class, or native inputs with `setNativeInput(true)` / Settings
`form.native_date_inputs`.
Every input takes `setPrefix()` / `setSuffix()` (text addons, an input group; `*Html` variants are raw), `setSuggestions()`
(a `<datalist>`), and the element's `setErrors()` is announced with `aria-describedby` / `aria-invalid`; `Checkbox::setSwitch(true)`
draws a toggle; a checkbox never carries `required` (the hidden `0` satisfies NOT NULL). Opt-in inputs `Number` (type=number),
`Tel`, `Range` (column comment `"element":"Number"`). `File::setDocumentUrlBuilder(fn($action, $id))` replaces the default
`?module=document&submodule=…` links; edit mode shows the stored file (`setShowCurrentFile(false)`). The form footer takes
`addButton()` (drawn bare, before the submit button), `setCancelLink($href)`, `setSubmitButtonLabel()`; `Button::setIcon()`.
`FormLayout::html($html, $modes)` puts raw HTML between sections. `Settings::getBool($key, $default)` / `Settings::has()` read
optional flags.

**Foreign Key Navigation**:
Generated classes include methods to traverse relationships:
```php
$order = new Order(123);
$customer = $order->getCustomerIdObj(); // Returns Customer object
echo $customer->getNameVal();
```

## Key Architectural Patterns

1. **Active Record**: Each table = class with data + behavior
2. **Singleton**: `Db`, `Settings`, `UrlParser` classes
3. **Factory**: `FieldTypeFactory`, `FormElementFactory`
4. **Builder**: `Query` for single-table SQL, `Form` for markup
5. **Template Method**: `BaseObject` defines CRUD skeleton, subclasses customize
6. **Strategy**: `FieldType` classes for different data types

## Configuration via Settings Class

The `Settings` singleton stores all configuration. Common keys:

- `database.*`: Connection parameters (hostname, database_name, username, password, port, engine)
  Optional: `database.charset` (MySQL, default utf8mb4), `database.socket` (MySQL unix socket), `database.persistent`,
  `database.connect_timeout`, `database.options` (PDO attribute => value), `database.dsn` (full override), `database.dsn_extra`
  (e.g. `sslmode=require`), `database.timezone` (session time zone), `database.schema` (PostgreSQL search_path),
  `database.init_commands` (SQL run after connecting), `database.empty_string_as_null` (default true),
  `database.reconnect_on_loss` (default true). `port` and `engine`
  default to 3306/mysql; engine accepts mysql, mariadb, postgres, postgresql, pgsql. A second connection reads
  `database.<name>.*` and is reached with `Db::getInstance('<name>')`.
- `project.package_name`: Root namespace for generated classes
- `settings.use_pretty_url`: Enable/disable pretty URLs (default: true)
- `settings.label.translations`: UI label translations
- Readers: `Settings::get($key)` throws `MissingConfigParamException`; `has()`, `getBool()`, `getString()`, `getInt()` take a
  default. `Settings::load([...])` / `loadFromFile('config.php'|'.json'|'.ini')` flatten nested arrays to dotted keys
  (lists stay values); `saveToFile('.php'|'.json')` writes the map back; `remove()`, `getProperties()`.

## Audit Logging

LAF includes automatic audit logging for all data changes:

1. Set active user once: `BaseObject::setActiveUserId($userId)` (falls back to `<package>\Person::getLoggedUserId()`)
2. All INSERT/UPDATE/DELETE operations are automatically logged (soft delete = one DELETE entry with the status change)
3. Only changed fields are recorded for updates; a clean object logs nothing
4. Changes stored as JSON with old/new values (`[{field, old_value, new_value}]`)
5. Disable per-object: `$object->auditLogDisable()`; process-wide: `AuditLog::disable()`
6. A failed audit write never breaks the data write (`AuditLog::getLastError()`); `AuditLog::setStrict(true)` makes it throw
7. Read history: `AuditLog::findForObject($obj)`, `findForRecord($table, $id)`, `$entry->getChanges()`, `new AuditLog($id)`

Requires `audit_log` table: `migrations/create_audit_logs_table.sql` (MySQL) or `migrations/create_audit_logs_table_postgres.sql`. See `examples/audit_logging_example.php`.

## Database Support

- **MySQL/MariaDB**: Primary support
- **PostgreSQL**: Full support via `PostgresTableInspector`

Connection managed through `Db` (one instance per name, `Db::getInstance()` connects on first use and caches only a
successful connection). `Db::getAllAssoc/getRowAssoc/getOne/getColumn/getPairs($sql, $params)` bind values by PHP type
(`int` → PARAM_INT, `bool` → 0/1, `null` → PARAM_NULL, or an explicit `[value, PDO::PARAM_*]` pair); `$db->statement()` is
the typed prepare+execute every helper uses; `$db->transaction(fn)` is nest-aware (an inner call runs in a `SAVEPOINT`,
also when the application opened the transaction on the raw PDO, so an inner failure the outer code catches undoes only the
inner work). Plain-table writes without a model: `$db->insert($table, $data)`, `insertGetId($table, $data, 'id')`
(`RETURNING` on PostgreSQL), `update($table, $data, $where)`, `delete($table, $where)` with `$where` as column => value
(null → `IS NULL`, list → `IN`, empty where refused); `fetchAllKeyed()/getAllKeyed()` key rows by a column;
`tableExists()/columnExists()/getTableNames()` read `information_schema` on both engines. Statement failures are the driver's
`PDOException` (recorded in `hasError()/getErrorMessage()` and written to the `SqlErrorLoggerInterface` table when one is
set; inside a transaction the entry waits until the transaction ends so it is neither rolled back nor refused); a failed
connection is `DbConnectException` (a `PDOException`). A lost connection outside a transaction is reopened and the statement
retried once (`database.reconnect_on_loss`, default true; `Db::isLostConnection($ex)` is the rule). `Identifier::engine()`
follows the registered default connection, then Settings. `setLogger()` takes a PSR-3 logger (statements at debug,
failures at error, reconnects at warning); `enableQueryLog()` keeps every statement with its time.

## File Structure Conventions

```
src/Laf/              # Framework core (never modify)
app/src/[Namespace]/  # Generated application code
  ├── Base/           # Auto-generated base classes (DO NOT EDIT)
  ├── *.php           # Main model classes (customize here)
  └── pages/          # CRUD page files (.page extension)
app/util/             # Utility scripts (generators, migrations)
tests/                # PHPUnit tests
examples/             # Usage examples
```

## Example Application

No example application is checked in. `sample_files/` holds the bootstrap pieces (`config.php`, `database.php`,
`constants.php`, `functions.php`, `class_generator.php`, `test.php`, `grid-callback-examples.md`),
`examples/audit_logging_example.php` walks through the audit log, and `sample_project/` (gitignored) is a copy of
the production ASM ERP built on LAF, kept for reference only.

## Field Types and Validation

The framework includes comprehensive field types:
- `TypeInteger`, `TypeFloat`, `TypeVarchar`, `TypeText`, `TypeChar`
- `TypeDate`, `TypeDateTime`, `TypeTime`
- `TypeBool`, `TypeJson`, `TypeBlob`

Each type provides:
- Database format conversion
- PDO parameter binding
- Form element generation
- Validation rules (required, maxLength, unique, etc.)

## Common Development Workflow

1. **Design database schema** (tables, columns, foreign keys)
2. **Run code generator** to create/update Base classes
3. **Add business logic** to main class files (not Base/)
4. **Customize generated pages** if needed
5. **Schema changes**: Rerun generator (Base classes regenerated, main classes preserved)

## Testing Notes

- PHPUnit 9.6; `phpunit.xml` defines the `Unit` suite (everything under `tests/` except the database tests) and the
  `Integration` suite (`DbTest`, `BaseObjectTest`, `PhpGridTest`, `GeneratorTest`, `DocumentIntegrationTest`)
- `tests/bootstrap.php` configures Settings from the `LAF_DB_*` environment variables; integration tests skip
  themselves when no connection can be made, and create their own tables (`dummy_table`, `code_table`, `link_table`,
  `status_table`, `audit_log`, `document`)
- `tests/Fixtures/LafShell/` holds the models the unit suite uses without a database (`DummyTable`, `CodeTable`,
  `LinkTable`, `Document`): the `Base/` classes are `ClassGenerator` output (`tests/Fixtures/regenerate.php` writes
  them to `tests/Fixtures/generated/<engine>/` from the integration tables; copy them over after a generator change),
  the leaf classes are hand-kept
- CI (`.github/workflows/ci.yml`): unit suite on PHP 8.1–8.5, PHPStan, integration suite on MariaDB and PostgreSQL
  (PHP 8.1 and 8.5); `Dockerfile` is `php:8.5-cli`
