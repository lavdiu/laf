# Getting started

This chapter takes you from an empty folder to a working LAF application: install the framework, configure it
with one settings file, create the four example tables (`company`, `employee`, `project`, `employee_project`),
generate a model class and a CRUD page per table, store your first record from PHP and open your first page in
the browser. LAF is **database-first**: the tables come first, the generator reads them and writes the PHP, and you
add behaviour on top. Every later chapter builds on the application set up here.

On this page:

- [Requirements](#requirements)
- [Install](#install)
- [Project layout](#project-layout)
- [Bootstrap and settings](#bootstrap-and-settings)
- [Create the example tables](#create-the-example-tables)
- [The audit_log table](#the-audit_log-table)
- [Generate the models and pages](#generate-the-models-and-pages)
- [Autoload the generated classes](#autoload-the-generated-classes)
- [Your first record](#your-first-record)
- [Your first page](#your-first-page)
- [Where to go next](#where-to-go-next)
- [See also](#see-also)

## Requirements

| What | Version / notes |
|---|---|
| PHP | 8.0 or newer (tested on 8.1 to 8.5; the code is warning-free under `E_ALL` on 8.5) |
| PHP extensions | `pdo` plus `pdo_mysql` or `pdo_pgsql`, `mbstring`, `json`, `gd` (image thumbnails) |
| Recommended extensions | `fileinfo` (MIME detection of uploads), `bcmath` (exact `DECIMAL` arithmetic in `TypeDecimal`) |
| Database | MySQL 5.7+/8, MariaDB 10.2+, or PostgreSQL |
| Composer | 2.x |
| Browser side | the generated pages use Bootstrap 5 markup, Font Awesome icons and the `grid.js` client that ships with LAF (no jQuery) |

Composer pulls in `claviska/simpleimage` (thumbnails), `openspout/openspout` (grid exports to XLSX),
`monolog/monolog` and `psr/log`.

## Install

```bash
mkdir myapp && cd myapp
composer init --name=acme/myapp --no-interaction
composer require lavdiu/laf
```

> **Note:** This documentation describes the `improvements1` branch of LAF (CSRF-protected forms, the
> `laf-generate` command, `Query`, many-to-many pickers, ...). If Composer resolves an older release, require the
> branch explicitly: `composer require lavdiu/laf:dev-improvements1`.

The package installs the `laf-generate` command in `vendor/bin/`, and its archive also contains the
`migrations/`, `sample_files/` and `docs/` folders you will copy files from below.

## Project layout

LAF does not impose a folder structure, but the generator and the sample bootstrap assume this one, and the rest
of the documentation uses it:

```
myapp/
├── composer.json
├── vendor/                         # lavdiu/laf and its dependencies
├── app/
│   ├── bootstrap.php               # autoloader + settings + session (shared by web, CLI and generator)
│   ├── settings.php                # every setting, as one nested array
│   └── lib/                        # --lib of laf-generate
│       ├── MyApp/
│       │   ├── Base/               # BaseCompany.php, ... regenerated on every run: never edit
│       │   ├── Company.php         # your model classes: written once, then yours
│       │   ├── Employee.php
│       │   └── Factory.php         # the page shell the generated pages ask for (you write it)
│       ├── generator_cache/pages/  # freshly generated .page files, rewritten on every run
│       └── pages/                  # the live .page files the application serves (yours once installed)
└── public/                         # web root
    ├── index.php                   # front controller: dispatches ?module=company to pages/company.page
    └── assets/grid.js              # copied from vendor/lavdiu/laf/sample_files/grid.js
```

Two folders deserve a word now (details in [02-code-generation.md](02-code-generation.md)):

- `MyApp/Base/` belongs to the generator. Each `Base*.php` describes the table (fields, keys, foreign keys,
  accessors) and is overwritten whenever you regenerate. Your code goes into `MyApp/Company.php` and friends, which
  extend the Base classes and are never overwritten (unless you pass `--force`).
- `generator_cache/pages/` is always rewritten; `pages/` is what the application serves. `--install-pages` copies
  a page into `pages/` only when the table has none yet, so a page you customised is never replaced.

## Bootstrap and settings

All configuration lives in the `Laf\Util\Settings` singleton as dotted keys (`database.hostname`,
`project.package_name`, ...). The simplest setup is one PHP file returning a nested array, loaded by a bootstrap
file that every entry point (web front controller, CLI scripts, the generator) requires.

`app/settings.php` (start from `vendor/lavdiu/laf/sample_files/settings.php`, which lists every key LAF reads):

```php
<?php

return [
    'app' => [
        'env' => 'development',           // anything but 'production' shows errors (see bootstrap.php)
        'timezone' => 'UTC',
    ],
    'project' => [
        'package_name' => 'MyApp',        // namespace of the generated classes: app/lib/MyApp/
    ],
    'database' => [
        'engine' => 'mysql',              // mysql | mariadb | postgres (also postgresql, pgsql)
        'hostname' => '127.0.0.1',
        'port' => 3306,                   // 5432 for PostgreSQL
        'database_name' => 'myapp',
        'username' => 'myapp',
        'password' => 'secret',
    ],
    'settings' => [
        'use_pretty_url' => false,        // ?module=company&action=view&id=1 (no rewrite rules needed)
    ],
    'form' => [
        'native_date_inputs' => true,     // <input type="date">: no date-picker library needed
    ],
];
```

`app/bootstrap.php` (adapted from `vendor/lavdiu/laf/sample_files/bootstrap.php`):

```php
<?php

declare(strict_types=1);

use Laf\Database\BaseObject;
use Laf\Util\Settings;

require_once __DIR__ . '/../vendor/autoload.php';

// 1. every setting from one array; nested keys become dotted keys (database.hostname, ...)
if (!Settings::getInstance()->loadFromFile(__DIR__ . '/settings.php')) {
    throw new RuntimeException('settings.php is missing or does not return an array');
}

// 2. environment overrides, so one bootstrap serves the workstation, CI and production
foreach ([
    'APP_ENV' => 'app.env',
    'LAF_DB_ENGINE' => 'database.engine',
    'LAF_DB_HOST' => 'database.hostname',
    'LAF_DB_PORT' => 'database.port',
    'LAF_DB_NAME' => 'database.database_name',
    'LAF_DB_USER' => 'database.username',
    'LAF_DB_PASS' => 'database.password',
] as $variable => $key) {
    $value = getenv($variable);
    if ($value !== false) {
        Settings::set($key, $value);
    }
}

date_default_timezone_set(Settings::getString('app.timezone', 'UTC'));

if (Settings::getString('app.env', 'production') !== 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// 3. web requests only: the session (CSRF tokens, flash messages) and the user the audit log records
if (PHP_SAPI !== 'cli') {
    session_start();
    if (isset($_SESSION['user_id'])) {
        BaseObject::setActiveUserId((int)$_SESSION['user_id']);
    }
}
```

Why this shape:

- **One bootstrap for everything.** `laf-generate --bootstrap=app/bootstrap.php` requires the same file your
  front controller does, so the generator always talks to the database your application uses.
- **The session is started here.** Forms keep their CSRF token in the session and generated pages queue flash
  messages there; without `session_start()` a submitted form is refused.
- **`BaseObject::setActiveUserId()`** tells the model layer who is acting: it fills `created_by` / `updated_by`
  and attributes [audit log](14-audit-log.md) entries. When it is not set, LAF falls back to
  `MyApp\Person::getLoggedUserId()` if such a class exists, otherwise the user stays `null`.

`Settings` also reads `.json` and `.ini` files, the environment (`Settings::loadFromEnvironment('LAF_')`) and
`.env` files (`Settings::loadEnvFile()`); see [16-settings-locale-utilities.md](16-settings-locale-utilities.md).
The connection options beyond the six basic keys (`database.charset`, `database.schema`, `database.dsn_extra`,
named second connections, ...) are in [13-database.md](13-database.md).

> **Note:** Keep `settings.php` out of version control when it holds production credentials, or leave the
> password out and set `LAF_DB_PASS` in the environment: the bootstrap above lets the environment win.

## Create the example tables

The example schema used throughout the documentation is shipped in
[`examples/schema.mysql.sql`](examples/schema.mysql.sql) and
[`examples/schema.postgres.sql`](examples/schema.postgres.sql):

| Table | Role in the examples |
|---|---|
| `company` | a standalone table: unique `name`, `vat_number`, `email`, `phone`, `record_status_id` (soft delete), `created_*` / `updated_*` metadata |
| `employee` | foreign key `company_id → company` (one company per employee), unique `email`, `birth_date`, `salary` decimal, `is_active` boolean, `notes` text |
| `project` | a standalone table: unique `name`, `starts_on`, `budget` |
| `employee_project` | a linker table: employees ⇄ projects, many-to-many |

MySQL / MariaDB:

```sql
CREATE TABLE `company` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `vat_number` varchar(20) DEFAULT NULL COMMENT '{"fields":{"setLabel":"VAT number","setHint":"Leave empty for private persons"}}',
  `email` varchar(150) DEFAULT NULL COMMENT '{"fields":{"setLabel":"E-mail"},"form":{"group":"Contact"}}',
  `phone` varchar(30) DEFAULT NULL COMMENT '{"form":{"group":"Contact"}}',
  `record_status_id` tinyint(4) NOT NULL DEFAULT 1 COMMENT '{"fields":{"setRequired":false}}',
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `employee` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL COMMENT '{"fields":{"setLabel":"Company"}}',
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL COMMENT '{"fields":{"setLabel":"E-mail","setDisplayField":true}}',
  `birth_date` date DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '{"fields":{"setLabel":"Active","setRequired":false}}',
  `notes` text DEFAULT NULL COMMENT '{"form":{"tab":"Notes"}}',
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_email_unique` (`email`),
  KEY `employee_company_fk` (`company_id`),
  CONSTRAINT `employee_company_fk` FOREIGN KEY (`company_id`) REFERENCES `company` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `project` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `starts_on` date DEFAULT NULL,
  `budget` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `employee_project` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL COMMENT '{"fields":{"setLabel":"Team members"}}',
  `project_id` int(11) NOT NULL COMMENT '{"fields":{"setLabel":"Projects"}}',
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_project_unique` (`employee_id`, `project_id`),
  KEY `employee_project_project_fk` (`project_id`),
  CONSTRAINT `employee_project_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_project_project_fk` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

PostgreSQL (the comments move to `COMMENT ON COLUMN`, `SERIAL` replaces `AUTO_INCREMENT`, `BOOLEAN` replaces
`tinyint(1)`):

```sql
CREATE TABLE company (
  id SERIAL PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  vat_number VARCHAR(20),
  email VARCHAR(150),
  phone VARCHAR(30),
  record_status_id SMALLINT NOT NULL DEFAULT 1,
  created_on TIMESTAMP,
  created_by INTEGER,
  updated_on TIMESTAMP,
  updated_by INTEGER,
  CONSTRAINT company_name_unique UNIQUE (name)
);
COMMENT ON COLUMN company.vat_number IS '{"fields":{"setLabel":"VAT number","setHint":"Leave empty for private persons"}}';
COMMENT ON COLUMN company.email IS '{"fields":{"setLabel":"E-mail"},"form":{"group":"Contact"}}';
COMMENT ON COLUMN company.phone IS '{"form":{"group":"Contact"}}';
COMMENT ON COLUMN company.record_status_id IS '{"fields":{"setRequired":false}}';

CREATE TABLE employee (
  id SERIAL PRIMARY KEY,
  company_id INTEGER REFERENCES company (id),
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  email VARCHAR(150) NOT NULL,
  birth_date DATE,
  salary NUMERIC(10,2),
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  notes TEXT,
  created_on TIMESTAMP,
  created_by INTEGER,
  updated_on TIMESTAMP,
  updated_by INTEGER,
  CONSTRAINT employee_email_unique UNIQUE (email)
);
COMMENT ON COLUMN employee.company_id IS '{"fields":{"setLabel":"Company"}}';
COMMENT ON COLUMN employee.email IS '{"fields":{"setLabel":"E-mail","setDisplayField":true}}';
COMMENT ON COLUMN employee.is_active IS '{"fields":{"setLabel":"Active","setRequired":false}}';
COMMENT ON COLUMN employee.notes IS '{"form":{"tab":"Notes"}}';

CREATE TABLE project (
  id SERIAL PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  starts_on DATE,
  budget NUMERIC(12,2),
  CONSTRAINT project_name_unique UNIQUE (name)
);

CREATE TABLE employee_project (
  id SERIAL PRIMARY KEY,
  employee_id INTEGER NOT NULL REFERENCES employee (id) ON DELETE CASCADE,
  project_id INTEGER NOT NULL REFERENCES project (id) ON DELETE CASCADE,
  CONSTRAINT employee_project_unique UNIQUE (employee_id, project_id)
);
COMMENT ON COLUMN employee_project.employee_id IS '{"fields":{"setLabel":"Team members"}}';
COMMENT ON COLUMN employee_project.project_id IS '{"fields":{"setLabel":"Projects"}}';
```

Load either file with the database's own client:

```bash
mysql -u myapp -p myapp < vendor/lavdiu/laf/docs/examples/schema.mysql.sql
psql -U myapp -d myapp -f vendor/lavdiu/laf/docs/examples/schema.postgres.sql
```

A few schema habits pay off with LAF, and the example follows them:

- **A single-column primary key** (`id`, auto-increment / serial). Composite keys work in the model layer, but
  the generated pages need one key column to build view / update / delete links.
- **Real foreign key constraints.** The generator only sees relations the database declares: they become
  `ForeignKey` objects, `getCompanyIdObj()` accessors, select boxes on forms, related-record tabs on pages and,
  for a linker table like `employee_project`, many-to-many pickers on both sides.
- **The metadata columns** `created_on`, `created_by`, `updated_on`, `updated_by` are filled automatically by
  `store()`, left out of insert/update forms and shown in a "Details" tab on view forms.
- **`record_status_id`** is LAF's soft-delete convention: `softDelete()` sets it to 0, and the generated page
  deletes softly when the column exists (see [03-models.md](03-models.md)).
- **A display column** per table: the column that represents a row in selects and joined grid columns. LAF
  picks `label` or `name` when present; `employee` has neither, so its schema names `email` with
  `"setDisplayField":true`.
- **JSON column comments** fine-tune the generated code: labels, hints, form sections and tabs. The full
  reference is in [02-code-generation.md](02-code-generation.md#json-column-comments).

> **Note:** A `NOT NULL` column is generated as a *required* field even when it has a database default. Forms
> would then demand a value for `record_status_id` and `is_active`. The comment `{"fields":{"setRequired":false}}`
> turns that off; an unset optional field is left out of the `INSERT`, so the database default applies.

## The audit_log table

Every insert, update and delete done through a model is written to an `audit_log` table, with the old and new
values as JSON. The table is optional (without it the data write still succeeds and the audit write fails
silently, see [14-audit-log.md](14-audit-log.md)), but create it now:

```bash
mysql -u myapp -p myapp < vendor/lavdiu/laf/migrations/create_audit_logs_table.sql
psql -U myapp -d myapp -f vendor/lavdiu/laf/migrations/create_audit_logs_table_postgres.sql
```

MySQL / MariaDB version ([`migrations/create_audit_logs_table.sql`](../migrations/create_audit_logs_table.sql)):

```sql
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `table_name` VARCHAR(100) NOT NULL,
    `record_id` VARCHAR(50) NOT NULL,
    `action` ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    `changes` TEXT NOT NULL,
    `created_on` DATETIME NOT NULL,
    INDEX `idx_audit_logs_table_record` (`table_name`, `record_id`),
    INDEX `idx_audit_logs_user_id` (`user_id`),
    INDEX `idx_audit_logs_created_on` (`created_on`),
    INDEX `idx_audit_logs_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

The PostgreSQL version ([`migrations/create_audit_logs_table_postgres.sql`](../migrations/create_audit_logs_table_postgres.sql))
stores `changes` as `JSONB` and replaces the `ENUM` with a `CHECK` constraint.

> **Note:** `laf-generate` leaves `audit_log` (and `sql_error`, `object_list`, `phinx_*`) out by default, so no
> model class is generated for it. You read the history through `Laf\Database\AuditLog` instead.

## Generate the models and pages

Run the generator with your bootstrap. First a dry run, which connects, lists the tables and prints what a real
run would write without touching the disk:

```bash
vendor/bin/laf-generate --bootstrap=app/bootstrap.php --lib=app/lib --install-pages --list
```

```
company
  write   app/lib/MyApp/Base/BaseCompany.php
  write   app/lib/MyApp/Company.php
  write   app/lib/generator_cache/pages/company.page
  install app/lib/pages/company.page
employee
  write   app/lib/MyApp/Base/BaseEmployee.php
  ...
```

Then the real run:

```bash
vendor/bin/laf-generate --bootstrap=app/bootstrap.php --lib=app/lib --install-pages
```

```
Checking the environment
Creating directory structure
Starting to generate Classes
Processed table: company
Processed table: employee
Processed table: employee_project
Processed table: project
Starting to generate Pages
Processed page: company
Processed page: employee
Processed page: employee_project
Processed page: project
Installing the pages of new tables
Installed page: company.page
Installed page: employee.page
Installed page: employee_project.page
Installed page: project.page
Done: 4 tables, 8 class files, 4 pages, 4 pages installed, 0 warnings
```

For every table you now have:

| File | What it is | Regenerated? |
|---|---|---|
| `app/lib/MyApp/Base/BaseEmployee.php` | fields, primary key, foreign keys, many-to-many relations, `setXxxVal()` / `getXxxVal()` / `getXxxFld()` / `getXxxFormElement()` / `getCompanyIdObj()` | every run |
| `app/lib/MyApp/Employee.php` | your model: extends `Base\BaseEmployee`, typed `find()` / `findOne()` | only when missing (or `--force`) |
| `app/lib/generator_cache/pages/employee.page` | the CRUD page as generated now | every run |
| `app/lib/pages/employee.page` | the page your application serves | only when missing (`--install-pages`) |

The real output for the example schema is checked in under [`examples/generated/`](examples/generated/), e.g.
[`BaseEmployee.php`](examples/generated/MyApp/Base/BaseEmployee.php),
[`Employee.php`](examples/generated/MyApp/Employee.php) and
[`employee.page`](examples/generated/pages/employee.page). Notice in `BaseEmployee` how the comments became code:

```php
$field = (new Field())
    ->setName("is_active")
    ->setPlaceholder("Is Active")
    ->setRequired(true)                       // NOT NULL
    ->setAutoIncrement(false)
    ->setUnique(false)
    ->setType(new Database\Field\TypeBool());  // tinyint(1)
$field->setLabel('Active');                   // from the column comment
$field->setRequired(false);                   // from the column comment
```

and how the linker table became a many-to-many relation on both sides:

```php
// BaseEmployee
$this->getTable()->addManyToMany("employee_project", ['owner' => 'employee_id', 'other' => 'project_id', 'table' => 'project', 'label' => 'Projects']);
// BaseProject
$this->getTable()->addManyToMany("employee_project", ['owner' => 'project_id', 'other' => 'employee_id', 'table' => 'employee', 'label' => 'Team members']);
```

Rerun the same command whenever the schema changes: the Base classes and the cached pages follow the database,
your model classes and live pages are kept. [02-code-generation.md](02-code-generation.md) covers every option.

## Autoload the generated classes

Tell Composer where the `MyApp` namespace lives (PSR-4: `MyApp\Base\BaseEmployee` maps to
`app/lib/MyApp/Base/BaseEmployee.php`), then dump the autoloader:

```json
{
    "require": {
        "lavdiu/laf": "dev-improvements1"
    },
    "autoload": {
        "psr-4": {
            "MyApp\\": "app/lib/MyApp/"
        }
    }
}
```

```bash
composer dump-autoload
```

New tables need no further step: PSR-4 finds new class files by their path.

## Your first record

A short CLI script, `app/try.php`, run with `php app/try.php`:

```php
<?php

declare(strict_types=1);

use Laf\Database\BaseObject;
use Laf\Exception\UniqueFieldDuplicateValueException;
use MyApp\Company;
use MyApp\Employee;

require __DIR__ . '/bootstrap.php';

BaseObject::setActiveUserId(1);                     // CLI: say who is acting (created_by, audit log)

// insert: store() decides between INSERT and UPDATE
$company = new Company();
$company->setNameVal('Acme Ltd')
    ->setEmailVal('office@acme.example');           // setters return the object: chain them
$company->store();                                  // true
echo $company->getIdVal(), PHP_EOL;                 // 11 (the new auto-increment id)

// columns the database filled (DEFAULT 1) are read back with reload()
$company->reload();
var_dump($company->getRecordStatusIdVal());         // int(1)
echo $company->getCreatedOnVal(), PHP_EOL;          // 2026-10-14 18:56:15 (filled by store())

// a row with a foreign key
$ada = new Employee();
$ada->setFirstNameVal('Ada')
    ->setLastNameVal('Lovelace')
    ->setEmailVal('ada@acme.example')
    ->setCompanyIdVal($company->getIdVal())
    ->setSalaryVal('4200.50');
$ada->store();

// load by primary key and follow the foreign key
$again = new Employee($ada->getIdVal());
echo $again->getFirstNameVal(), ' works at ', $again->getCompanyIdObj()->getNameVal(), PHP_EOL;
// Ada works at Acme Ltd
var_dump($again->getIsActiveVal());                 // bool(true): the database default, typed by TypeBool
var_dump($again->getSalaryVal());                   // string(7) "4200.50": decimals stay exact strings

// find by column values
$found = Employee::findOne(['email' => 'ada@acme.example']);   // MyApp\Employee or null

// unique columns are checked before the statement runs
try {
    (new Company())->setNameVal('Acme Ltd')->store();
} catch (UniqueFieldDuplicateValueException $ex) {
    echo $ex->getMessage(), PHP_EOL;
    // Field name must be unique: value 'Acme Ltd' already exists in company
}
```

The `audit_log` now holds an `INSERT` entry per row, for example for the company:

```json
[{"field":"name","old_value":null,"new_value":"Acme Ltd"},{"field":"email","old_value":null,"new_value":"office@acme.example"}]
```

Models are covered in depth in [03-models.md](03-models.md), queries in [04-queries.md](04-queries.md).

## Your first page

A generated `.page` file is a plain PHP script that reads the route (`UrlParser::getAction()`, `getId()`),
builds the form and the grid, and echoes a complete HTML document. Two small pieces of your own make it run:
a **page shell** and a **front controller**.

### The page shell: `MyApp\Factory`

Every generated page starts with `$html = Factory::GeneralPage();` (the class is `MyApp\Factory` by default;
[02-code-generation.md](02-code-generation.md#pagegenerator) shows how to name another one). LAF does not
generate it, because it is where your application's look lives: stylesheets, scripts, the menu.

`app/lib/MyApp/Factory.php`:

```php
<?php

namespace MyApp;

use Laf\UI\Page\Html;

class Factory
{
    /**
     * The document every generated page draws into
     */
    public static function GeneralPage(): Html
    {
        return (new Html())
            ->setPageTitle('MyApp')
            ->addStylesheet('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css')
            ->addStylesheet('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css')
            ->addScript('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], true)
            ->addScript('assets/grid.js', [], true)        // the PhpGrid client, see below
            ->setMenu('<nav class="navbar navbar-dark bg-dark mb-3"><div class="container-fluid">'
                . '<a class="navbar-brand" href="?module=company">MyApp</a>'
                . '<a class="nav-link text-light" href="?module=employee">Employees</a>'
                . '<a class="nav-link text-light" href="?module=project">Projects</a>'
                . '</div></nav>');
    }
}
```

Copy the grid client next to it: `cp vendor/lavdiu/laf/sample_files/grid.js public/assets/grid.js`.

> **Note:** `setMenu()` takes raw HTML and `setPageTitle()` is escaped. Which page APIs escape and which take raw
> HTML is listed in [11-pages-and-ui.md](11-pages-and-ui.md) and [17-security.md](17-security.md).

### The front controller: `public/index.php`

```php
<?php

declare(strict_types=1);

use Laf\Util\UrlParser;

require __DIR__ . '/../app/bootstrap.php';

$module = UrlParser::getModule();                 // ?module=company -> "company"
if ($module === '' || $module === 'home') {
    $module = 'company';                          // the start page
}
// only plain table names: never include a path taken from the request
if (!preg_match('/^[a-z0-9_]+$/', $module)) {
    http_response_code(404);
    exit('Page not found');
}
$page = __DIR__ . '/../app/lib/pages/' . $module . '.page';
if (!is_file($page)) {
    http_response_code(404);
    exit('Page not found');
}
require $page;
```

Point your web server's document root at `public/` (or, on a development server that serves a whole folder,
open `http://localhost/myapp/public/`). Then:

| URL | What you get |
|---|---|
| `?module=company` | the list: a grid with paging, sorting, search and export; "Add new" button |
| `?module=company&action=new` | an empty form; saving redirects to the view page with a flash message |
| `?module=company&action=view&id=11` | the record as a read-only form, an Options menu (Update / Delete) and a "Related information" tab with the company's employees |
| `?module=company&action=update&id=11` | the edit form |
| `?module=employee&action=new` | the employee form: a Company select, the "Projects" checkboxes from the linker table, the "Notes" tab from the column comment |

With `settings.use_pretty_url` set to `false`, links are query strings and no rewrite rule is needed. Pretty URLs
(`/company/company/view/11`), the URL anatomy and how the generated page dispatches its actions are described in
[12-routing-and-generated-pages.md](12-routing-and-generated-pages.md).

> **Note:** The delete links carry the form's CSRF token, and form submissions are checked against the token kept
> in the session. If saving a form always answers with a CSRF error, the session is not started before the page
> runs (see the bootstrap above).

The generated page is yours once it is in `pages/`: edit it freely. When you want the latest generator output
after a schema change, compare it with `generator_cache/pages/<table>.page` and merge what you need.

## Where to go next

1. [02-code-generation.md](02-code-generation.md): every generator option, the column-comment reference, what
   is regenerated and what is not.
2. [03-models.md](03-models.md) and [04-queries.md](04-queries.md): working with records.
3. [06-forms.md](06-forms.md) and [08-relations.md](08-relations.md): customising the generated forms.
4. [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md): the anatomy of a `.page` file.

## See also

- [README.md](README.md): the documentation index
- [02-code-generation.md](02-code-generation.md): `laf-generate`, `DatabaseGenerator`, JSON column comments
- [03-models.md](03-models.md): `BaseObject`, the generated accessors, hooks, deletes
- [13-database.md](13-database.md): every `database.*` setting, named connections
- [14-audit-log.md](14-audit-log.md): the `audit_log` table and reading history
- [16-settings-locale-utilities.md](16-settings-locale-utilities.md): `Settings` readers and loaders
- [`sample_files/bootstrap.php`](../sample_files/bootstrap.php), [`sample_files/settings.php`](../sample_files/settings.php)
