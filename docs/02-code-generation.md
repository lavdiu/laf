# Code generation

LAF writes the repetitive part of a database application for you: for every table it reads the schema
(columns, types, keys, foreign keys, comments) and generates a Base class that describes the table, a model class
you own, and a CRUD page. This chapter is the complete reference for that step: the `laf-generate` command and
every option, the `DatabaseGenerator` PHP API, what each generated file contains and when it is rewritten, the
JSON column comments that tune the output, how column types map to field types, how linker tables become
many-to-many relations, the page labels, and the table inspectors underneath.

On this page:

- [How generation works](#how-generation-works)
- [The laf-generate command](#the-laf-generate-command)
- [What gets generated](#what-gets-generated)
- [Regeneration rules](#regeneration-rules)
- [DatabaseGenerator (PHP API)](#databasegenerator-php-api)
- [JSON column comments](#json-column-comments)
- [Type mapping](#type-mapping)
- [Display columns](#display-columns)
- [Foreign keys](#foreign-keys)
- [Linker tables and many-to-many relations](#linker-tables-and-many-to-many-relations)
- [PageGenerator: labels, page shell, module names](#pagegenerator)
- [Table inspectors (MySQL and PostgreSQL)](#table-inspectors-mysql-and-postgresql)
- [ClassGenerator reference](#classgenerator-reference)
- [Warnings, plan() and CI](#warnings-plan-and-ci)
- [See also](#see-also)

## How generation works

```
            information_schema / pg_catalog
                         │
          TableInspectorFactory::create($table)
          ┌──────────────┴───────────────┐
  MysqlTableInspector          PostgresTableInspector     (same column shape on both engines)
          └──────────────┬───────────────┘
        ┌────────────────┴─────────────────┐
  ClassGenerator                     PageGenerator
  Base/BaseEmployee.php              generator_cache/pages/employee.page
  Employee.php (once)                (→ pages/employee.page with --install-pages)
        └────────────────┬─────────────────┘
                 DatabaseGenerator          (table selection, directories, output, warnings)
                         │
             vendor/bin/laf-generate        (Laf\Generator\Cli)
```

- A **table inspector** reads one table's metadata and returns rows shaped like MySQL's `information_schema`
  on both engines, so the generators contain no engine branches.
- **`ClassGenerator`** turns those rows into the Base class and, once, the model class.
- **`PageGenerator`** turns them into a `.page` file (list grid, view with related tabs, insert/update forms,
  CSRF-protected delete).
- **`DatabaseGenerator`** runs both for every selected table of the connected database.
- **`laf-generate`** is the command-line front end of `DatabaseGenerator`.

Generation needs a live database connection: the generator reads the schema of the database named in your
settings (MySQL: the current database; PostgreSQL: the current schema, i.e. the first one in `search_path`).
Only base tables are generated; views are left out.

## The laf-generate command

```
Usage: laf-generate --bootstrap=FILE [--lib=DIR] [options]
```

The command loads your application's bootstrap file (which must configure `Settings`), connects to the database
and generates. It is installed as `vendor/bin/laf-generate`; inside a LAF checkout it is `bin/laf-generate`.

### Options

| Option | Short | Value | Default | What it does |
|---|---|---|---|---|
| `--bootstrap=FILE` | | PHP file | `LAF_BOOTSTRAP` env variable | The file that configures `Settings` (`database.*`, `project.package_name`). Required: without it (and without `LAF_BOOTSTRAP`) the command stops with exit code 2. |
| `--lib=DIR` | | folder | `app/lib` | The folder that receives `{Namespace}/`, `{Namespace}/Base/`, `generator_cache/pages/` and `pages/`. Relative paths are relative to the current directory. |
| `--tables=a,b,c` | | comma list | every base table | Only these tables. Names or patterns with `*` (`inv_*`, `*_log`), case-insensitive. |
| `--exclude=x,y` | | comma list | `audit_log, sql_error, object_list, phinx_*` | Tables left out (patterns allowed). **Replaces** the default list; `--exclude=` (empty) keeps every table. |
| `--classes` | | flag | off | Only the Base and model classes, no pages. |
| `--pages` | | flag | off | Only the pages (into `generator_cache/pages/`; add `--install-pages` to also install). Cannot be combined with `--classes`. |
| `--install-pages` | | flag | off | Also copy the generated page of every table that has none in `pages/` yet. Existing live pages are never touched. |
| `--force` | `-f` | flag | off | Overwrite existing model classes (`MyApp/Employee.php`). Base classes are always rewritten. |
| `--list` | `-l` | flag | off | Dry run: print the selected tables and the files a run would write (`write`, `keep`, `install`). Writes nothing. |
| `--labels=FILE` | | `.php` or `.json` | Settings `settings.label.translations` | Page label translations (see [Page labels](#page-labels)). A `.php` file returns an array, a `.json` file holds an object. |
| `--strict` | | flag | off | Exit code 1 when the generator reported warnings (for CI). |
| `--quiet` | `-q` | flag | off | Print only warnings and the summary line. |
| `--help` | `-h` | flag | | Print the usage text and exit 0. |

Values are given as `--lib=app/lib` or `--lib app/lib`. Flags take no value (`--list=1` is an error). Anything
that is not an option (a bare word, an unknown `-x`) is a usage error.

### Exit codes

| Code | Constant | When |
|---|---|---|
| 0 | `Cli::EXIT_OK` | Success, including runs with warnings when `--strict` is not given, `--list` and `--help` |
| 1 | `Cli::EXIT_WARNINGS` | `--strict` and at least one warning |
| 2 | `Cli::EXIT_ERROR` | Usage or configuration error: unknown option, missing value, `--classes` with `--pages`, no bootstrap, bootstrap not found or throwing, `project.package_name` not set, no database connection, unreadable `--labels` file, no table matches the selection, a generation failure (unwritable folder, ...) |

Errors go to stderr, prefixed with `laf-generate:`:

```
$ vendor/bin/laf-generate
laf-generate: No bootstrap file: pass --bootstrap=FILE or set LAF_BOOTSTRAP
$ vendor/bin/laf-generate --bootstrap=app/bootstrap.php --classes --pages
laf-generate: --classes and --pages exclude each other (run with --help for the options)
$ vendor/bin/laf-generate --bootstrap=app/bootstrap.php --tables=nothing_*
laf-generate: No table matches the selection (--tables / --exclude)
```

### Examples

```bash
# everything, and install the pages of new tables (the usual command)
vendor/bin/laf-generate --bootstrap=app/bootstrap.php --lib=app/lib --install-pages

# the bootstrap from the environment
export LAF_BOOTSTRAP=app/bootstrap.php
vendor/bin/laf-generate --lib=app/lib

# only the classes of two tables after a schema change
vendor/bin/laf-generate --bootstrap=app/bootstrap.php --tables=employee,employee_project --classes

# what would a run do? (dry run, honours --tables/--classes/--pages/--install-pages/--force)
vendor/bin/laf-generate --bootstrap=app/bootstrap.php --install-pages --list --tables=emp*

# pages only, translated, quiet
vendor/bin/laf-generate --bootstrap=app/bootstrap.php --pages --labels=app/labels.json -q

# regenerate a model class from scratch (overwrites your code in MyApp/Project.php!)
vendor/bin/laf-generate --bootstrap=app/bootstrap.php --tables=project --classes --force
```

A dry run on the example schema after a first generation (existing files are kept):

```
$ vendor/bin/laf-generate --bootstrap=app/bootstrap.php --install-pages --list --tables=emp*
employee
  write   app/lib/MyApp/Base/BaseEmployee.php
  keep    app/lib/MyApp/Employee.php
  write   app/lib/generator_cache/pages/employee.page
  keep    app/lib/pages/employee.page
employee_project
  write   app/lib/MyApp/Base/BaseEmployeeProject.php
  keep    app/lib/MyApp/EmployeeProject.php
  write   app/lib/generator_cache/pages/employee_project.page
  keep    app/lib/pages/employee_project.page
```

A normal run prints progress lines and ends with a summary:

```
Done: 4 tables, 8 class files, 4 pages, 4 pages installed, 0 warnings
```

> **Note:** `--exclude` replaces the default exclusions instead of adding to them. `--exclude=employee*` brings
> `audit_log` back into the run (a `MyApp\AuditLog` model and an `audit_log.page` you rarely want). Repeat the
> defaults when you exclude more:
> `--exclude=audit_log,sql_error,object_list,phinx_*,tmp_*`.

> **Note:** The class count in the summary counts every model class the run looked at, including the ones it
> kept because they already existed. `--list` shows exactly which files are written and which are kept.

> **Note:** The command never generates `autoload.php` (it assumes Composer autoloads your namespace, see
> [01-getting-started.md](01-getting-started.md#autoload-the-generated-classes)). The PHP API does by default.

## What gets generated

`DatabaseGenerator` derives every path from the library folder (`--lib`) and `project.package_name`:

| Config key | Path | Contents |
|---|---|---|
| `base_class_dir` | `{lib}/{Namespace}/Base` | `Base{Class}.php` per table |
| `class_dir` | `{lib}/{Namespace}` | `{Class}.php` per table (and `autoload.php` from the PHP API) |
| `page_dir` | `{lib}/generator_cache/pages` | `{table}.page` per table, as generated now |
| `live_page_dir` | `{lib}/pages` | `{table}.page` installed by `--install-pages` / `installMissingPages()` |

### Names

| Database name | PHP name | Rule (`Laf\Util\Util`) |
|---|---|---|
| table `employee_project` | class `EmployeeProject`, Base class `Base\BaseEmployeeProject`, page `employee_project.page` | `tableNameToClassName()`: split on `_`, capitalise each part |
| column `first_name` | `setFirstNameVal()`, `getFirstNameVal()`, `getFirstNameFld()`, `getFirstNameFormElement()` | `tableFieldNameToMethodName()` |
| column `company_id` (foreign key) | additionally `getCompanyIdObj()` | |
| column `vat_number` | default label and placeholder "Vat Number" | `tableFieldNameToLabel()` (a comment `setLabel` wins) |

### The Base class

[`examples/generated/MyApp/Base/BaseEmployee.php`](examples/generated/MyApp/Base/BaseEmployee.php) is the
complete output for `employee`. Its parts:

| Part | What it does |
|---|---|
| `__construct($id = null)` | binds the table metadata (`buildClass()`) and loads the row when an id is given; a composite key is `['a' => 1, 'b' => 2]` |
| `select($id): bool` | loads a row by primary key |
| `buildClass()` (private) | one `Field` per column (name, label, placeholder, required, max length, auto-increment, unique, type, then the setters from the column comment and `setFormOptions()`), the `PrimaryKey`, unique fields, the display field when a comment names one, one `ForeignKey` per foreign key column, one `addManyToMany()` per linker table |
| `setXxxVal($value)` | sets a column value, returns the object (chainable) |
| `getXxxVal()` | the column value, typed by its field type |
| `getXxxFld()` | the `Laf\Database\Field\Field` object (metadata, validation rules, value) |
| `getXxxFormElement(?FormElementInterface $override = null)` | the column's form input (see [07-form-inputs.md](07-form-inputs.md)) |
| `getXxxObj(bool $fresh = false)` | foreign key columns only: the referenced model, loaded once per key value (`true` reloads); an empty object when the key is empty |
| `listAllArray(): array` | every row as an associative array, ordered by the primary key |
| `listAllObjects(): array` | every row as a model object (one SELECT) |
| `getAllFieldsAsFormElements(): array` | the form element of every field |

One field definition, with the comment `{"fields":{"setLabel":"E-mail"}}` and a unique index:

```php
$field = (new Field())
    ->setName("email")
    ->setPlaceholder("Email")
    ->setRequired(true)
    ->setMaxLength(150)
    ->setAutoIncrement(false)
    ->setUnique(true)
    ->setType(new Database\Field\TypeVarchar());
$field->setLabel('E-mail');
$this->getTable()->addUniqueField($field);
$this->getTable()->addField($field);
```

What the generator derives from the schema:

| Field setting | Source |
|---|---|
| `setRequired()` | `IS_NULLABLE = 'NO'` (defaults are not considered: see the note below) |
| `setMaxLength()` | `CHARACTER_MAXIMUM_LENGTH` of character columns only (`varchar`, `char`, `enum`, `set`, `binary`, `varbinary`, PostgreSQL `character varying`, `bpchar`, `citext`); text and JSON columns get none |
| `setAutoIncrement()` | `EXTRA` contains `auto_increment` (MySQL), identity or `nextval()` default (PostgreSQL) |
| `setUnique()` + `addUniqueField()` | a single-column unique index (`COLUMN_KEY = 'UNI'`); columns of a composite unique index are not marked |
| `$pk->addField()` | primary key columns, in key order |
| `setType()` | the [type mapping](#type-mapping), or the comment's `setType` |

> **Note:** A `NOT NULL` column with a database default is still generated as required, so forms ask for it and
> `store()` throws `MissingFieldValueException` when it is unset. Add `{"fields":{"setRequired":false}}` to such
> columns (the example does on `record_status_id` and `is_active`): an unset optional column is left out of the
> `INSERT` and the database default applies.

> **Note:** Never edit a Base class. It is rewritten on every run, so your change disappears the next time anyone
> regenerates. Put code in the model class; change labels, rules and form hints through column comments.

### The model class

[`examples/generated/MyApp/Employee.php`](examples/generated/MyApp/Employee.php) is written once and is yours:

```php
class Employee extends Base\BaseEmployee
{
    public function __construct($id = null) { parent::__construct($id); }

    public static function findOne(array $keyValuePairs) : ?Employee { return parent::bOfindOne($keyValuePairs); }

    public static function find(array $keyValuePairs = [], array $orderBy = [], ?int $limit = null, int $offset = 0) : array
    {
        return parent::bOfind($keyValuePairs, $orderBy, $limit, $offset);
    }

    // a commented-out getFormLayout() example
}
```

`find()` / `findOne()` are typed to the class (`Employee[]`, `?Employee`). Add hooks (`beforeInsert()`, ...),
`validateRecord()`, `getFormLayout()`, `configureForm()` and your business methods here: see
[03-models.md](03-models.md), [05-validation.md](05-validation.md) and [06-forms.md](06-forms.md).

### The page

[`examples/generated/pages/employee.page`](examples/generated/pages/employee.page) is a complete CRUD screen:

- `list` (default): a `PhpGrid` over the table, foreign keys joined to the referenced table's
  [display column](#display-columns), with View / Update / Delete buttons and "Add new";
- `view`: the record as a read-only form laid out by the model, an Options menu (Update, Delete with a CSRF
  token), and a "Related information" card with one tab per foreign key pointing at the table (a linker table
  shows the table behind it: the employee page has a "Project" tab, the project page an "Employee" tab);
- `new` / `update`: the form, processed with `processForm()` and redirected to the view page with a flash message;
- `delete`: checks the CSRF token, soft-deletes when the table has `record_status_id`, hard-deletes otherwise.

When the table has a `record_status_id` column, the page sets it to 1 on insert. The page's anatomy, its URL
handling and how to customise it are in [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md).
The grid is covered in [10-grids.md](10-grids.md).

### The autoloader (PHP API only)

`DatabaseGenerator::generateEverything()` writes `{lib}/{Namespace}/autoload.php`, a small `spl_autoload_register()`
loader for the namespace, unless you call `setGenerateAutoloader(false)`. With Composer (PSR-4 entry
`"MyApp\\": "app/lib/MyApp/"`) you do not need it; `laf-generate` never writes it.

## Regeneration rules

| File | First run | Later runs | With `--force` / `setForceRewriteClasses(true)` |
|---|---|---|---|
| `{Namespace}/Base/Base{Class}.php` | written | **rewritten** | rewritten |
| `{Namespace}/{Class}.php` | written | kept | **rewritten** (your code is lost) |
| `generator_cache/pages/{table}.page` | written | **rewritten** | rewritten |
| `pages/{table}.page` | installed with `--install-pages` | kept | kept (never overwritten) |
| `{Namespace}/autoload.php` (PHP API) | written | rewritten | rewritten |

What this means in practice:

- **Add a column** and regenerate: the Base class gains the field and its accessors, your model class keeps
  working, the form shows the new field (unless your `getFormLayout()` lists fields explicitly). The live page
  does not change; its grid SQL lists columns explicitly, so copy the list query from the fresh page in
  `generator_cache/pages/` if you want the column in the list.
- **Rename or drop a column**: the old accessors disappear from the Base class; code in your model class or
  pages that still calls them fails. Search for `getOldNameVal(` before regenerating.
- **Drop a table**: the generator never deletes files. Remove `Base{Class}.php`, `{Class}.php` and the pages
  yourself.
- **Add a table**: run with `--install-pages` to get its page in `pages/`.
- **Change a comment**: only the Base class changes (labels, rules, form options). No page update is needed,
  because pages lay out forms through the model.

> **Note:** Treat the generated Base classes as build output that you commit: they are code your application
> runs, and reviewing their diff after a schema change shows exactly what changed in the model layer.

## DatabaseGenerator (PHP API)

`Laf\Generator\DatabaseGenerator` is what the command runs. Use it directly when generation has to happen from
inside the application (an admin page, a migration script). [`sample_files/class_generator.php`](../sample_files/class_generator.php)
is a complete example:

```php
<?php

declare(strict_types=1);

use Laf\Generator\DatabaseGenerator;
use Laf\Util\Settings;

require_once __DIR__ . '/bootstrap.php';

$generator = new DatabaseGenerator(__DIR__ . '/lib');                    // writes app/lib/MyApp/...
$generator
    ->setExcludedTables(['audit_log', 'sql_error', 'object_list', 'phinx_*'])
    ->setLabelTranslations(Settings::has('settings.label.translations') ? (array)Settings::get('settings.label.translations') : [])
    ->setGenerateAutoloader(false)                                       // Composer autoloads the package
    ->setInstallMissingPages(true)                                       // new tables get their page in pages/
    ->setOutput(static function (string $line): void {
        echo trim($line) !== '' ? trim($line) . (PHP_SAPI === 'cli' ? PHP_EOL : "<br>\n") : '';
    })
    ->generateEverything();

foreach ($generator->getWarnings() as $warning) {
    fwrite(PHP_SAPI === 'cli' ? STDERR : STDOUT, 'Warning: ' . $warning . PHP_EOL);
}
printf('%d files written%s', count($generator->getGeneratedFiles()), PHP_EOL);
exit($generator->getWarnings() === [] ? 0 : 1);
```

> **Note:** The PHP API excludes nothing by default (the command excludes `audit_log`, `sql_error`,
> `object_list`, `phinx_*`). Call `setExcludedTables()` yourself.

### Methods

| Method | What it does | Notes |
|---|---|---|
| `__construct(string $library_path, bool $force_rewrite_class_files = false)` | Derives the four directories from the library path and `project.package_name` | Throws `MissingConfigParamException` when `project.package_name` is not set |
| `setTables(array $tables): static` | Generate only these tables | Names or `*` patterns, case-insensitive; `[]` = every table |
| `setExcludedTables(array $tables): static` | Leave these tables out | Patterns allowed; applied after `setTables()` |
| `getTables(): array` | The selected base tables as `[['table_name' => 'company'], ...]` | Queries the database (`Db::getTableNames()`) |
| `setLabelTranslations(array $labels): static` | Page labels, key => text | See [Page labels](#page-labels) |
| `getLabelTranslations(): array` | The labels set so far | |
| `setOutput(?callable $output): static` | Where progress lines go: `fn (string $line)`; `null` = `echo` | Lines start with `"\n"`; with `echo` on the web, output is flushed as it goes |
| `setLogger(?LoggerInterface $logger): DatabaseGenerator` / `getLogger()` | A PSR-3 logger: progress at `info`, warnings at `warning` | In addition to the output callable |
| `setGenerateAutoloader(bool $generate): static` | Whether `generateEverything()` writes `{Namespace}/autoload.php` | Default `true` |
| `setForceRewriteClasses(bool $force): static` / `isForceRewriteClasses(): bool` | Overwrite existing model classes | Same as the constructor's second argument |
| `setInstallMissingPages(bool $install): static` | Whether `generateEverything()` ends with `installMissingPages()` | Default `false` |
| `generateEverything(array $labelTranslations = []): void` | `checkConfig()`, directories, autoloader (unless off), classes, pages, and the page installation when switched on | A non-empty argument replaces the label translations; throws `MissingConfigParamException` when the config lacks directories or namespace |
| `processTables(): static` | Base and model classes of the selected tables | The class generators create their folders |
| `processPages(): static` | Pages of the selected tables into `page_dir` | |
| `installMissingPages(): static` | Copies `page_dir/{table}.page` to `live_page_dir` for tables without a live page | Never overwrites; warns when no generated page exists; throws `RuntimeException` when a copy fails |
| `plan(): array` | What a run would write, without writing | One entry per table: `table`, `base_class`, `class`, `class_exists`, `page`, `live_page`, `live_page_exists` |
| `generateAutoLoader(): static` | Writes `{class_dir}/autoload.php` | Throws `RuntimeException` when it cannot write |
| `createDirectoryStructure(): static` | Creates `class_dir`, `base_class_dir`, `page_dir` | |
| `checkConfig(): bool` | Whether the config names `class_dir`, `base_class_dir` and `namespace` | Writes an error line when not |
| `getConfig(): array` / `setConfig(array $config): void` | The generator config | `setConfig()` replaces the whole array: merge with `getConfig()` (see below) |
| `getGeneratedFiles(): array` | Every file the run wrote or considered (model classes that were kept included), installed pages and the autoloader | |
| `getInstalledPages(): array` | The pages `installMissingPages()` copied | |
| `getWarnings(): array` | Messages from the generators, prefixed with the table name for class warnings | See [Warnings](#warnings-plan-and-ci) |

### The config array

`getConfig()` returns:

```php
[
    'namespace'      => 'MyApp',
    'base_class_dir' => '/path/app/lib/MyApp/Base',
    'class_dir'      => '/path/app/lib/MyApp',
    'page_dir'       => '/path/app/lib/generator_cache/pages',
    'live_page_dir'  => '/path/app/lib/pages',
    'rewrite_class'  => false,
]
```

The page generator also reads three optional keys that have no setter and no command-line option; add them
through `setConfig()`:

```php
$generator = new DatabaseGenerator(__DIR__ . '/lib');
$generator->setConfig([
    'module_for_table' => ['employee' => 'staff'],      // table => module of the page that shows its rows
    'page_factory' => 'MyApp\\Ui\\Shell',               // class of the page shell (default {namespace}\Factory)
    'page_factory_method' => 'admin',                   // its static method (default GeneralPage)
] + $generator->getConfig());
$generator->setTables(['company'])->processPages();
```

The company page then starts with `use MyApp\Ui\Shell;` / `$html = Shell::admin();`, and its "Employee" tab
links to `?module=staff&action=view&id={employee_id}`. See [PageGenerator](#pagegenerator).

### plan()

```php
$generator = new DatabaseGenerator(__DIR__ . '/lib');
print_r($generator->setTables(['employee'])->plan());
```

```
Array
(
    [0] => Array
        (
            [table] => employee
            [base_class] => /path/app/lib/MyApp/Base/BaseEmployee.php
            [class] => /path/app/lib/MyApp/Employee.php
            [class_exists] => 1
            [page] => /path/app/lib/generator_cache/pages/employee.page
            [live_page] => /path/app/lib/pages/employee.page
            [live_page_exists] => 1
        )
)
```

`plan()` inspects the tables (it needs the database) but writes nothing; `laf-generate --list` prints it.

## JSON column comments

A column comment that is a JSON object configures the generated field. MySQL puts it in the column definition,
PostgreSQL in `COMMENT ON COLUMN`:

```sql
-- MySQL / MariaDB
ALTER TABLE `employee` MODIFY `email` varchar(150) NOT NULL
    COMMENT '{"fields":{"setLabel":"E-mail","setHint":"Work address"},"form":{"group":"Contact"}}';

-- PostgreSQL
COMMENT ON COLUMN employee.email IS '{"fields":{"setLabel":"E-mail","setHint":"Work address"},"form":{"group":"Contact"}}';
```

A comment that does not start with `{` or is not valid JSON is ignored, so ordinary comments keep working. After
changing a comment, regenerate the classes (`--classes` is enough).

The object has two keys:

```json
{
  "fields": { "setLabel": "Customer", "setHint": "Billing contact", "setRequired": false,
              "setType": "uuid", "setDisplayField": true },
  "form":   { "readonly": "update", "hidden": true, "exclude": ["view"], "group": "Address",
              "tab": "Pricing", "order": 20, "element": "TextArea", "create": true, "multi": false }
}
```

### "fields": Field setters

Every key of `"fields"` names a method of `Laf\Database\Field\Field`; the generator emits a call with the value
as argument after the schema-derived settings, so a comment value wins:

```json
{"fields": {"setLabel": "VAT number", "setHint": "Leave empty for private persons"}}
```

```php
$field->setLabel('VAT number');
$field->setHint('Leave empty for private persons');
```

Rules:

- **A JSON list spreads into several arguments**: `"setPattern": ["/^[A-Z]/", "Start with a capital letter"]`
  becomes `$field->setPattern('/^[A-Z]/', 'Start with a capital letter');`.
- **Scalars are cast to the parameter's declared type**: `"setMinLength": "3"` becomes `setMinLength(3)`;
  `"setRequired": "no"` becomes `setRequired(false)` (for `bool` parameters the strings `""`, `"0"`, `"false"`,
  `"no"` are false); numeric strings stay strings where the parameter accepts them (`"setMaxValue": "0.01"` keeps
  the decimal exact).
- **Unknown methods are skipped with a warning** (`column comment names an unknown Field method "setFoo"`).
- **`setType` and `setDisplayField` are handled by the generator**, not called on the field (see below).

The useful keys:

| Key | Argument(s) | Effect |
|---|---|---|
| `setLabel` | string | The label (forms, grids, error messages); replaces the derived label |
| `setPlaceholder` | string | The input placeholder; replaces the derived one |
| `setHint` | string | Help text under the input |
| `setRequired` | bool | Override the `NOT NULL` rule (typically `false` for columns with a default) |
| `setMinLength` / `setMaxLength` | int | Length rules |
| `setMinValue` / `setMaxValue` | int, float or numeric string | Range rules (`"0.01"` stays exact for decimals) |
| `setPattern` | `[regex, message]` or `regex` | Regex rule; the message is optional |
| `setIncrementStep` | int | Step attribute of numeric inputs |
| `setInvalidValueErrorMessage` | string | Message shown when the type rejects the value |
| `setAttribute` | `[name, value]` | An extra HTML attribute on the input |
| `setAllowHtml` | bool | Allow HTML in the value (raw output opt-in, see [17-security.md](17-security.md)) |
| `setFormElementClass` | class name | The input class (the `form.element` key below is the usual way) |
| `setType` | type name | **Replaces the schema type**: `"uuid"` turns a MySQL `char(36)` into `TypeUuid`. Any name from the [type mapping](#type-mapping) works, with arguments in the name if needed (`"decimal(12,2)"`). |
| `setDisplayField` | `true` or a column name | **Makes a column the table's display field**: `true` on the column itself, or the name of another column of the table (`"label_al"`). Emits `$this->getTable()->setDisplayField(...)`; when several columns name one, the last wins with a warning. |

Validation rules are described in [05-validation.md](05-validation.md).

> **Note:** An unknown `setType` name does not warn: the field silently becomes `TypeText`. Check the generated
> `setType(new Database\Field\Type...)` line after adding one.

### "form": layout hints

`"form"` holds hints that `FormLayout::auto()` reads when it lays out the model's forms. The generator validates
and normalises them and stores them with `$field->setFormOptions([...])`:

| Key | Value | Effect |
|---|---|---|
| `exclude` | `true`, a draw mode or a list of modes | Leave the field out of the form in these modes (`true` = all modes) |
| `readonly` | `true`, a mode or a list | Draw the field read-only in these modes; it posts nothing and is not changed by the submission (`true` = `insert` and `update`) |
| `hidden` | `true`, a mode or a list | Hide the field's wrapper (`d-none`); the value still posts (`true` = all modes) |
| `group` | string | Put the field in a fieldset with this legend |
| `tab` | string | Put the field in a tab with this title (wins over `group`) |
| `order` | integer | Position; fields without one get 10, 20, 30... in column order, so `"order": 15` lands between the first and second column |
| `element` | class name | The input class: a short name under `Laf\UI\Form\Input` (`TextArea`, `Select`, `Radio`, `Autocomplete`, `Number`, `Tel`, `Range`, `Email`, `Url`, `Color`, `Password`, `DualList`, `Checkboxes`, ...) or a fully qualified class implementing `FormElementInterface` |
| `create` | bool | On a foreign key drawn as `Autocomplete`: offer "+ Create ..." to add a missing row of the referenced table. On a linker column: the same for the chips picker |
| `multi` | bool | On a linker table column only: `false` leaves the many-to-many picker out of the other side's form |

Draw modes are `view`, `insert` and `update` (`Laf\UI\Form\DrawMode`). Unknown modes, unknown keys and element
names that do not resolve to a form element class are dropped with a warning. `create` and `multi` accept
`true`/`false` or the strings `"yes"`/`"no"`, `"1"`/`"0"`, `"true"`/`"false"`.

What the normaliser makes of a comment, verified with `ColumnComment::formOptions()`:

```json
{"form":{"readonly":"update","hidden":true,"exclude":["view","edit"],"group":"Address","tab":"Pricing",
         "order":"20","element":"TextArea","create":"yes","multi":false,"width":3}}
```

```php
[
    'readonly' => ['update'],
    'hidden'   => ['view', 'insert', 'update'],
    'exclude'  => ['view'],                       // "edit" is not a draw mode: warning
    'group'    => 'Address',
    'tab'      => 'Pricing',
    'order'    => 20,
    'element'  => 'Laf\UI\Form\Input\TextArea',
    'create'   => true,
    'multi'    => false,
]
// warnings: unknown draw mode "edit" for form option "exclude"; ignored form option "width"
```

How the layout uses these options, and how to override them in the model's `getFormLayout()`, is described in
[06-forms.md](06-forms.md); the inputs in [07-form-inputs.md](07-form-inputs.md); `create` and the pickers in
[08-relations.md](08-relations.md).

### Examples on the example schema

```sql
-- company: two columns in a "Contact" fieldset
`email` varchar(150) DEFAULT NULL COMMENT '{"fields":{"setLabel":"E-mail"},"form":{"group":"Contact"}}',
`phone` varchar(30) DEFAULT NULL COMMENT '{"form":{"group":"Contact"}}',

-- employee: the notes in their own tab
`notes` text DEFAULT NULL COMMENT '{"form":{"tab":"Notes"}}',

-- a defaulted NOT NULL column that forms must not require
`is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '{"fields":{"setLabel":"Active","setRequired":false}}',

-- employee: pick the company with a search box, and allow creating one on the fly
`company_id` int(11) DEFAULT NULL COMMENT '{"fields":{"setLabel":"Company"},"form":{"element":"Autocomplete","create":true}}',

-- a salary that cannot be negative, shown read-only when editing
`salary` decimal(10,2) DEFAULT NULL COMMENT '{"fields":{"setMinValue":"0"},"form":{"readonly":"update"}}',

-- a code that must look like ABC-123
`code` varchar(7) NOT NULL COMMENT '{"fields":{"setPattern":["/^[A-Z]{3}-[0-9]{3}$/","Format: ABC-123"]}}',
```

## Type mapping

`Laf\Database\Field\FieldTypeFactory` maps the column's `DATA_TYPE` (plus `COLUMN_TYPE`, precision and scale) to
a field type. The field type decides how values are converted and validated and which input the form draws.

| Database types | Field type (generated) | Default form input |
|---|---|---|
| `int`, `integer`, `int4`, `serial` | `TypeInteger('int')` (`TypeInteger('int', true)` for `unsigned`) | `Integer` |
| `smallint`, `int2`, `smallserial` / `mediumint` / `bigint`, `int8`, `bigserial` / `year` | `TypeInteger('smallint')` / `('mediumint')` / `('bigint')` / `('year')` | `Integer` |
| `tinyint(1)`, `tinyint` without a width, `bit(1)`, `bool`, `boolean` | `TypeBool()` | `Checkbox` |
| `tinyint(4)` (any width but 1) | `TypeInteger('tinyint')` | `Integer` |
| `bit(n)`, n > 1 | `TypeInteger('bigint')` | `Integer` |
| `decimal(p,s)`, `numeric`, `dec`, `fixed`, `money` | `TypeDecimal(p, s)` (`money`: scale 2) | `Text` |
| `float`, `double`, `real`, `double precision`, `float4`, `float8` | `TypeFloat()` | `Text` |
| `varchar`, `character varying`, `citext`, `inet`, `cidr`, `macaddr`, `macaddr8`, `interval` | `TypeVarchar()` | `Text` |
| `char`, `character`, `bpchar` | `TypeChar()` | `Text` |
| `text`, `tinytext`, `mediumtext`, `longtext`, `name`, `xml`, `array`, `tsvector` | `TypeText()` | `TextArea` |
| `date` | `TypeDate()` | `Date` |
| `datetime`, `timestamp`, `timestamp without time zone` | `TypeDateTime()` | `DateTime` |
| `timestamp with time zone`, `timestamptz` | `TypeDateTime(true)` | `DateTime` |
| `time`, `time with(out) time zone`, `timetz` | `TypeTime()` | `Time` |
| `json`, `jsonb` (and MariaDB `JSON`, see below) | `TypeJson()` | `TextArea` |
| `blob`, `tinyblob`, `mediumblob`, `longblob`, `binary`, `varbinary`, `bytea` | `TypeBlob()` | `Text` |
| `enum('a','b')`, PostgreSQL enum types | `TypeEnum(['a', 'b'])` | `Select` with the values |
| `set('x','y')` | `TypeEnum(['x', 'y'], true)` | `CheckboxCollection` |
| `uuid` (or comment `"setType":"uuid"`) | `TypeUuid()` | `Text` |
| anything else (`geometry`, PostgreSQL composite or domain types, ...) | `TypeText()` | `TextArea` |

The role of a column overrides the type's input: a **foreign key** is a `Select` of the referenced table's rows
(an `Autocomplete` above `form.select_autocomplete_threshold` rows or when the comment says so, a `File` upload
when it points at the `document` table), and an **auto-increment primary key** is `Hidden`. The comment's
`form.element` overrides both.

Engine details:

- **MariaDB `JSON`** is an alias for `LONGTEXT` with a `CHECK (json_valid(col))` constraint. The MySQL inspector
  reads the check constraints and reports such columns as `json`, so they get `TypeJson`. Servers without the
  `CHECK_CONSTRAINTS` view (MySQL < 8.0.16, MariaDB < 10.2) skip this silently.
- **PostgreSQL enum types** report `USER-DEFINED`; the inspector loads their labels from `pg_enum` in declaration
  order. A user-defined type that is not an enum becomes `TypeText`.

> **Note:** MySQL 8.0.19 and later no longer report integer display widths: a `tinyint(4)` column comes back as
> plain `tinyint`, and a `tinyint` without a width maps to `TypeBool`. On MySQL 8, use `smallint` for small
> numeric codes such as `record_status_id` (MariaDB keeps the width and is not affected), or force the type with
> `{"fields":{"setType":"smallint"}}`.

Field types and their conversion rules are documented in [03-models.md](03-models.md).

## Display columns

Wherever a row has to be shown as one value (the joined column in list grids, foreign key selects, many-to-many
pickers), LAF uses the table's display column. The generator's inspectors choose it in this order:

1. the column named by a comment `{"fields":{"setDisplayField": ...}}` on any column of the table;
2. a column called `label`;
3. a column called `name`;
4. the second column of the table (else the first).

For the example, `company` and `project` show `name`. `employee` has neither `label` nor `name`; without help,
the generated grids that join `employee` (the `employee_project` list) would show `company_id`, its second
column. That is why the example schema names the display column on `employee.email`:

```sql
`email` varchar(150) NOT NULL COMMENT '{"fields":{"setLabel":"E-mail","setDisplayField":true}}',
```

The grid then joins `` `employee`.`email` AS `employee_email` ``, and the generator also writes
`$this->getTable()->setDisplayField($this->getTable()->getField("email"));` into `BaseEmployee`, so the runtime
(selects, pickers, autocomplete) uses the same column. `"setDisplayField": "last_name"` on any column of the table
would name another column instead.

> **Note:** Without a comment, the runtime `Table::getDisplayField()` uses its own order: `name`, `label`,
> `description`, then the second column. A table with both `label` and `name` columns therefore shows `label`
> in generated grids but `name` in form selects. Name the display column in a comment to make both agree.

## Foreign keys

Every foreign key constraint becomes a `ForeignKey` in the Base class and a `getXxxObj()` accessor:

```php
$this->getTable()->addForeignKey(
    (new ForeignKey())
        ->setField($this->getTable()->getField("company_id"))
        ->setKeyName('employee_company_fk')
        ->setReferencingTable("company")
        ->setReferencingField("id")
);
```

`ON DELETE` / `ON UPDATE` rules are emitted only when they change behaviour (`CASCADE`, `SET NULL`,
`SET DEFAULT`); `NO ACTION` and `RESTRICT` produce no line. The linker table's keys in
[`BaseEmployeeProject.php`](examples/generated/MyApp/Base/BaseEmployeeProject.php) carry `->setOnDelete('CASCADE')`.

On the referenced side, every foreign key that points at a table's primary key gives the generated page a
related-records tab (the company page lists the company's employees). Navigation, validation and the form inputs
of foreign keys are covered in [03-models.md](03-models.md) and [08-relations.md](08-relations.md).

## Linker tables and many-to-many relations

A **linker table** (junction table) connects two tables so that a row of one can be linked to several rows of
the other. `employee_project(id, employee_id → employee, project_id → project)` links employees and projects.
`Laf\Generator\JunctionDetector` recognises one by this rule:

1. it has **exactly two foreign key columns**, pointing at **two different tables**;
2. besides the foreign keys, the primary key and the metadata columns (`id`, `created_on`, `created_by`,
   `updated_on`, `updated_by`, `is_deleted`, `deleted`, `record_status_id`), it has **at most two other columns**
   (`JunctionDetector::MAX_EXTRA_COLUMNS`), e.g. a `position` or a `role`.

So `invoice_line(invoice_id, product_id, quantity, price, discount)` is not a linker table (three data columns):
it is a child table and gets ordinary related tabs. A table whose two keys point at the same table
(`transfer(from_account_id, to_account_id)`) is not a linker either.

For each linker table that points at a table, the generator writes one `addManyToMany()` into that table's Base
class; both sides get one:

```php
// BaseEmployee
$this->getTable()->addManyToMany("employee_project", ['owner' => 'employee_id', 'other' => 'project_id', 'table' => 'project', 'label' => 'Projects']);

// BaseProject
$this->getTable()->addManyToMany("employee_project", ['owner' => 'project_id', 'other' => 'employee_id', 'table' => 'employee', 'label' => 'Team members']);
```

| Definition key | Meaning | Source |
|---|---|---|
| `owner` | the linker column that points at this table | schema |
| `other` | the linker column that points at the other table | schema |
| `table` | the other table | schema |
| `label` | the picker's label | the `setLabel` of the comment on the **`other` column**, else the other table's name in plural ("Project" gives "Projects", "Category" "Categories", "Address" "Addresses") |
| `hint` | help text under the picker | `setHint` of the same comment (only when set) |
| `required` | at least one row must be picked | `"setRequired": true` in the same comment (only when true) |
| `form` | layout hints: `order`, `group`, `tab`, `exclude`, `readonly`, `hidden`, `element`, `create` | the `"form"` object of the same comment (`multi` is consumed by the generator) |

The comment that configures a side's picker is the one on the linker column pointing **at the other table**: the
Employee form's picker is labelled by `employee_project.project_id` ("Projects"), the Project form's picker by
`employee_project.employee_id` ("Team members"). That way each comment describes the rows the picker offers.

A fuller example: a `project_tag(id, project_id, tag_id)` linker whose `tag_id` comment is

```json
{"fields":{"setLabel":"Tags","setHint":"Pick a few","setRequired":true},
 "form":{"element":"Autocomplete","create":true,"group":"Classification"}}
```

generates in `BaseProject`:

```php
$this->getTable()->addManyToMany("project_tag", ['owner' => 'project_id', 'other' => 'tag_id', 'table' => 'tag', 'label' => 'Tags', 'hint' => 'Pick a few', 'required' => true, 'form' => ['element' => 'Laf\\UI\\Form\\Input\\Autocomplete', 'create' => true, 'group' => 'Classification']]);
```

Pickers: `element` may be `Checkboxes` / `CheckboxCollection` (the default for small tables), `Autocomplete`
(chips with search; the default above `form.select_autocomplete_threshold` rows) or `DualList` (two lists with
Add / Remove buttons). The relation's API (`$employee->manyToMany('employee_project')->sync([...])`) and the
pickers are documented in [08-relations.md](08-relations.md).

When the generator writes **no** relation:

- the comment on the `other` column says `"form":{"multi":false}`: the relation is left out on that side
  (use it for a linker that is maintained elsewhere, or for one side only);
- the linker table has a data column that is `NOT NULL` without a default (and not auto-increment): a picker
  could not insert links. The generator skips the relation with the warning
  `table project: linker table project_tag has a required column without a default, no multi-value picker`.
  Give the column a default or make it nullable to get the picker.

On the generated pages, a linker table is shown as the table behind it: the employee view page gets a "Project"
tab listing the employee's projects (joined through `employee_project`), with View buttons that open the project
page.

## PageGenerator

`Laf\Generator\PageGenerator` writes one `.page` per table. `DatabaseGenerator` passes it the config and the label
translations; you rarely use it directly.

### Page labels

The generated pages contain these texts. Override any of them with `--labels=FILE`, Settings
`settings.label.translations`, `DatabaseGenerator::setLabelTranslations()` or `generateEverything($labels)`:

| Key | English default |
|---|---|
| `view` | View |
| `cancel` | Cancel |
| `options` | Options |
| `add-new` | Add new |
| `update` | Update |
| `delete` | Delete |
| `list` | List |
| `delete-confirmation` | Are you sure you want to delete this? |
| `record-saved` | Record saved successfully |
| `record-deleted` | Record deleted successfully |
| `record-not-found` | Record not found |
| `record-not-saved` | The record was not saved, please correct the fields marked below |
| `record-not-deleted` | The record was not deleted: the link is not valid, open the record and try again |
| `related-information` | Related information |

The keys are `PageGenerator::LABEL_DEFAULTS`. `labels.json`:

```json
{"add-new": "Regjistro", "list": "Lista", "delete-confirmation": "A jeni i sigurt?"}
```

or `labels.php`:

```php
<?php
return ['add-new' => 'Regjistro', 'list' => 'Lista', 'delete-confirmation' => 'A jeni i sigurt?'];
```

```bash
vendor/bin/laf-generate --bootstrap=app/bootstrap.php --pages --labels=app/labels.json
```

The texts are written into the page source as string literals (`new Link('Regjistro', ...)`,
`$grid->setTitle('Company Lista')`, `confirm("A jeni i sigurt?")`), so changing a label means regenerating the
pages. Grid titles are the table label plus the `list` label.

### Config keys

| Key | Default | Effect |
|---|---|---|
| `namespace` | `project.package_name` | `use MyApp\Company;` at the top of the page |
| `page_dir` / `live_page_dir` | from the library path | where `savePageToFile()` writes (`setWriteOnLiveDirectory(true)` writes to the live folder) |
| `page_factory` | `{namespace}\Factory` | the class that provides the page shell (an `Laf\UI\Page\Html`) |
| `page_factory_method` | `GeneralPage` | its static method: the page calls `$html = Factory::GeneralPage();` |
| `module_for_table` | the table name | the module whose page shows a table's rows: an array table => module, or a callable `fn (string $table): string`. Used for the View / Update buttons of related tabs, which open the other table's page |

Related tabs list rows of another table, so their buttons go to that table's page (View and Update only; deleting
is the other page's job, with its own CSRF token). The page's own list acts on the current module.

### Methods

| Method | What it does |
|---|---|
| `__construct(Table $table, array $config, array $labelTranslations = [], ?TableInspectorInterface $tableInspector = null)` | One page generator per table; the inspector is optional (created for the engine when omitted) |
| `generatePageFile(): void` | Builds the page source (`getPageFile()`) |
| `savePageToFile(): static` | Builds and writes it, creating the folder; throws `RuntimeException` on failure |
| `getPageFilePath(): string` / `pageFileExists(): bool` | `{page_dir}/{table}.page` (or the live folder) |
| `buildGrid(string $table_name, string $grid_name = 'grid', array $filters = [], ?string $gridId = null, ?string $targetModule = null): string` | PHP source of a list grid (used for the list and the related tabs) |
| `buildManyToManyGrid(string $otherTable, string $gridName, string $junctionTable, string $fkToCurrent, string $fkToOther, string $currentTable, string $currentPk, ?string $gridId = null): string` | PHP source of a related tab through a linker table |
| `moduleForTable(string $table): string` | The module for a table (config `module_for_table`, else the table name) |
| `getTableInspector(): TableInspectorInterface` / `addTableInspector(TableInspectorInterface $inspector): static` | The page table's inspector; inject inspectors for any table (tests, no database) |
| `setEngine(?string $engine): static` / `getEngine(): string` | The engine the generated SQL is quoted for (default: the connection's) |
| `getLabelTranslations()` / `setLabelTranslations(array $labels): PageGenerator` | The label overrides |
| `isWriteOnLiveDirectory()` / `setWriteOnLiveDirectory(bool $write): PageGenerator` | Write to `live_page_dir` instead of `page_dir` |
| `getTable()` / `setTable(Table $table)`, `getConfig()` / `setConfig(array $config)`, `getPageFile()` / `setPageFile(?string $source)` | Accessors |
| `getWarnings(): array` | e.g. `table x has no primary key: the generated page cannot view, update or delete rows` |

## Table inspectors (MySQL and PostgreSQL)

`Laf\Generator\TableInspectorFactory::create(string $table, ?string $engine = null)` returns the inspector for
the engine (`mysql`, `mariadb` → `MysqlTableInspector`; `postgres`, `postgresql`, `pgsql` →
`PostgresTableInspector`; `null` = the default connection's engine, else Settings `database.engine`).
`TableInspector` is the former name of the MySQL inspector, kept as a deprecated alias.

```php
use Laf\Generator\TableInspectorFactory;

$inspector = TableInspectorFactory::create('employee_project');
$inspector->inspect();                                    // runs the queries

$inspector->getPrimaryColumnName();                       // 'id'
array_keys($inspector->getColumns());                     // ['id', 'employee_id', 'project_id']
$inspector->getColumns()['project_id'];
```

```php
[
    'COLUMN_NAME' => 'project_id',
    'IS_NULLABLE' => 'NO',
    'DATA_TYPE' => 'int',
    'COLUMN_TYPE' => 'int(11)',
    'COLUMN_KEY' => 'MUL',
    'EXTRA' => '',
    'COLUMN_COMMENT' => '{"fields":{"setLabel":"Projects"}}',
    // ... every other information_schema.columns field ...
    'FOREIGN_KEY' => [
        'column_name' => 'project_id',
        'constraint_name' => 'employee_project_project_fk',
        'referenced_table_name' => 'project',
        'referenced_column_name' => 'id',
        'delete_rule' => 'CASCADE',
        'update_rule' => 'RESTRICT',
    ],
]
```

`php sample_files/inspect_table.php employee` (with your bootstrap next to it) prints a table's columns in this
shape; it is the quickest way to see what the generator sees.

### The column row on both engines

Column rows use the upper-case `information_schema` names on both engines (`COLUMN_NAME`, `DATA_TYPE`,
`IS_NULLABLE`, `CHARACTER_MAXIMUM_LENGTH`, `NUMERIC_PRECISION`, `NUMERIC_SCALE`, `COLUMN_DEFAULT`, `COLUMN_KEY`,
`EXTRA`, `COLUMN_COMMENT`, ...) plus `FOREIGN_KEY` on foreign key columns. The PostgreSQL inspector fills the
MySQL-only fields itself:

| Field | MySQL / MariaDB | PostgreSQL |
|---|---|---|
| `COLUMN_KEY` | from `information_schema` (`PRI`, `UNI`, `MUL`, `''`) | `PRI` for primary key columns, `UNI` for a single-column unique index without a predicate, else `''` |
| `EXTRA` | `auto_increment` etc. | `auto_increment` for identity columns and `nextval()` defaults (serial) |
| `COLUMN_COMMENT` | the column comment | `col_description()`, i.e. `COMMENT ON COLUMN` |
| `DATA_TYPE` | as reported; MariaDB `JSON` columns relabelled `json` | as reported; enum types are `USER-DEFINED` with `ENUM_OPTIONS` |
| Scope | the current database (`DATABASE()`) | the current schema (`current_schema()`, from `search_path` / `database.schema`) |
| Foreign keys | `KEY_COLUMN_USAGE` + `REFERENTIAL_CONSTRAINTS` | `pg_constraint`, composite keys matched position by position |

### Inspector methods

| Method | Returns |
|---|---|
| `inspect(): void` | Loads columns, foreign keys and referencing tables |
| `isInspected(): bool` | Whether `inspect()` has run (`AbstractTableInspector`) |
| `getTable(): string` / `setTable(string $table): static` | The table name (`setTable()` resets the inspected state) |
| `getColumns(): array` / `setColumns(array $columns): static` | Column name => row |
| `hasColumn(string $name): bool`, `getColumn(string $name): ?array` | One column |
| `isForeignKeyColumn(string $name): bool`, `getForeignKey(string $name): ?array` | The column's `FOREIGN_KEY` entry |
| `hasForeignKeys(): bool` | Whether any column is a foreign key |
| `hasReferencingTables(): bool`, `getReferencingTables(): array` | Foreign keys of other tables that point at this table's primary key: `['table_name', 'column_name', 'constraint_name']` (upper-case keys too) |
| `populateReferencingTables(): void` | Reloads just the referencing tables |
| `getPrimaryColumnName(): ?string`, `getPrimaryColumnNames(): array` | First / all primary key columns; `setPrimaryColumnName(string\|array\|null)` replaces them |
| `getDisplayColumnName(): string` | The [display column](#display-columns); throws `RuntimeException` when the table has no columns (does not exist) |

`JunctionDetector::detect(TableInspectorInterface $junction, string $currentTable, ?string $viaColumn = null): ?array`
applies the linker rule to an inspected table. For `employee_project` seen from `employee`:

```php
[
    'junction_table' => 'employee_project',
    'other_table'    => 'project',
    'fk_to_current'  => 'employee_id',
    'fk_to_other'    => 'project_id',
    'other_column'   => [/* the project_id column row */],
    'insertable'     => true,      // every data column can be left out of an INSERT
]
```

`JunctionDetector::isForeignKey(array $column): bool` is the foreign key test it uses;
`JunctionDetector::METADATA_COLUMNS` and `MAX_EXTRA_COLUMNS` are the rule's constants.

## ClassGenerator reference

`Laf\Generator\ClassGenerator` generates the two classes of one table. Use it directly to generate a single table
from code, or with injected inspectors to test generation without a database.

| Method | What it does |
|---|---|
| `__construct(Table $table, array $config, ?TableInspectorInterface $tableInspector = null)` | Config keys `namespace`, `base_class_dir`, `class_dir`, `rewrite_class` |
| `processBaseClass(): static` / `saveBaseClassToFile(): static` | Build / build and write the Base class |
| `processClass(): static` / `saveClassToFile(): static` | Build / write the model class (only when missing or `rewrite_class` is set) |
| `generateFields(): string`, `generateForeignKeys(): string`, `generateManyToMany(): string` | The parts of `buildClass()` |
| `detectManyToMany(): array` | Linker table => `addManyToMany()` definition |
| `addTableInspector(TableInspectorInterface $inspector): static` | Metadata of another table (a linker), e.g. in tests |
| `getTableInspector()` / `setTableInspector(TableInspectorInterface $inspector): static` | The table's own inspector |
| `getTableColumns(): array`, `populateForeignKeys(): void`, `getFkTable($column): string` | The inspected columns; the referenced table of a column (`''` when not a foreign key) |
| `getClassFilePath()`, `classFileExists()`, `getBaseClassFilePath()`, `baseClassFileExists()` | Paths |
| `getBaseClassFile()` / `setBaseClassFile()`, `getClassFile()` / `setClassFile()` | The generated sources |
| `getTable()` / `setTable(Table $table)`, `getConfig()` / `setConfig(array $config)` | Accessors |
| `getWarnings(): array` | Comment and linker warnings of the last `processBaseClass()` |
| `static exportArray(array $value): string` | Short-array PHP source of an array (used for form options and definitions) |

Generating one table from code, without the database, with a hand-made inspector (any class implementing
`TableInspectorInterface`; `inspect()` can be a no-op):

```php
use Laf\Database\Table;
use Laf\Generator\ClassGenerator;

$generator = new ClassGenerator(
    new Table('project'),
    ['namespace' => 'MyApp', 'base_class_dir' => '/tmp/MyApp/Base', 'class_dir' => '/tmp/MyApp'],
    $projectInspector                                   // returns the column rows shown above
);
$generator->addTableInspector($projectTagInspector);    // the linker table, for addManyToMany()
echo $generator->processBaseClass()->getBaseClassFile();
print_r($generator->getWarnings());
```

`ColumnComment` (the comment parser) is public too: `ColumnComment::parse(?string)`, `fieldSetters(?string,
array &$warnings)`, `typeOverride(?string)`, `displayField(?string, string $column, array $columnNames)`,
`formOptions(?string, array &$warnings)`, and the constants `FORM_KEYS`, `FORM_MODE_KEYS`, `FORM_BOOL_KEYS`.

## Warnings, plan() and CI

The generators report what they could not use instead of failing. Warnings appear as `Warning: ...` progress
lines, in `DatabaseGenerator::getWarnings()`, and make `laf-generate --strict` exit with 1:

| Warning | Cause |
|---|---|
| `employee: column comment names an unknown Field method "setFoo"` | a `"fields"` key that is not a `Field` method |
| `employee: column comment: ignored form option "width"` | an unknown `"form"` key |
| `employee: column comment: unknown draw mode "edit" for form option "exclude"` | a mode other than `view` / `insert` / `update` |
| `employee: column comment: "Foo" is not a form element class` | an `element` that does not resolve |
| `employee: table employee: several columns name a display field; "x" wins` | `setDisplayField` on more than one column |
| `project: table project: linker table project_tag has a required column without a default, no multi-value picker` | see [linker tables](#linker-tables-and-many-to-many-relations) |
| `project: table project: cannot inspect project_tag (...)` | a referencing table could not be read |
| `table log has no primary key: the generated page cannot view, update or delete rows` | page generator |
| `log: no generated page to install (...)` | `installMissingPages()` without a generated page |

A CI check that the committed Base classes match the schema:

```bash
vendor/bin/laf-generate --bootstrap=app/bootstrap.php --classes --strict -q
git diff --exit-code app/lib/MyApp/Base
```

> **Note:** The element name `TransferList` (an alias of `DualList` accepted at runtime by `ManyToMany`) is not
> accepted in a column comment: the generator drops it with a "not a form element class" warning. Use
> `"element":"DualList"` in comments.

## See also

- [01-getting-started.md](01-getting-started.md): the first generation run, the page shell, the front controller
- [03-models.md](03-models.md): what you do with the generated classes
- [05-validation.md](05-validation.md): the rules `"fields"` setters configure
- [06-forms.md](06-forms.md): `FormLayout::auto()` and the `"form"` hints
- [07-form-inputs.md](07-form-inputs.md): the input classes `element` names
- [08-relations.md](08-relations.md): foreign key inputs, `create`, many-to-many pickers and `ManyToMany`
- [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md): the anatomy of a generated page
- [`sample_files/class_generator.php`](../sample_files/class_generator.php), [`sample_files/inspect_table.php`](../sample_files/inspect_table.php)
