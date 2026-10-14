# Tutorial: build a team directory with LAF

This tutorial takes you from an empty folder to a small but complete application: a **team directory** of
companies, employees and projects. You create the database, let LAF generate the models and the CRUD pages, serve
them through a front controller, then make the application your own: rules and a hook on the employee model, a
custom form with a project picker that can create projects on the fly, a searchable and exportable directory, a
dashboard, friendly messages for duplicate values and an audit history on every employee. Every file you create is
shown in full, and the output shown at each step comes from running exactly these files. The reference chapters
(01 to 18) explain each piece in depth; this page is the guided path through them.

On this page:

- [What you will build](#what-you-will-build)
- [Step 1: the project, the settings and the database](#step-1-the-project-the-settings-and-the-database)
- [Step 2: generate the models and pages](#step-2-generate-the-models-and-pages)
- [Step 3: a front controller and a page shell](#step-3-a-front-controller-and-a-page-shell)
- [Step 4: customise the Employee model](#step-4-customise-the-employee-model)
- [Step 5: a directory grid and a dashboard](#step-5-a-directory-grid-and-a-dashboard)
- [Step 6: unique values](#step-6-unique-values)
- [Step 7: the history of an employee](#step-7-the-history-of-an-employee)
- [Step 8: where to go next](#step-8-where-to-go-next)
- [See also](#see-also)

## What you will build

```
teamdir/
├── composer.json
├── app/
│   ├── settings.php                  step 1: every setting
│   ├── bootstrap.php                 step 1: autoloader + settings, shared by web, CLI and generator
│   └── lib/
│       ├── MyApp/
│       │   ├── Base/                 step 2: generated, rewritten on every run
│       │   ├── Company.php ...       step 2: generated once, then yours (Employee.php: step 4)
│       │   └── Factory.php           step 3: the page shell
│       ├── generator_cache/pages/    step 2: fresh page output on every run
│       └── pages/                    step 2: the live pages; directory.page + dashboard.page: step 5
├── bin/unique-check.php              step 6: a CLI script
├── database/schema.sql               step 1
└── public/                           the web root
    ├── index.php                     step 3: the front controller
    └── assets/grid.js                step 3: the grid client
```

You need PHP 8.0 or newer with `pdo_mysql` (or `pdo_pgsql`), `mbstring`, `json` and `gd`, Composer 2, and a MySQL /
MariaDB (or PostgreSQL) server. The full requirements are in
[01-getting-started.md](01-getting-started.md#requirements).

**URLs.** The generated pages build query-string links (`?module=employee&action=view&id=1`), all relative to
the current page. So the application works from any folder: at the root of a host, or under a sub-folder such as
`http://localhost/teamdir/public/`. This tutorial uses that sub-folder address. Pretty URLs
(`/employee/employee/view/1`) are root-absolute and the generated pages do not support them, see
[12-routing-and-generated-pages.md](12-routing-and-generated-pages.md#urlparser).

## Step 1: the project, the settings and the database

### composer.json

```bash
mkdir teamdir && cd teamdir
mkdir -p app/lib bin database public/assets
```

`composer.json`:

```json
{
    "name": "acme/teamdir",
    "description": "Team directory built with LAF",
    "type": "project",
    "require": {
        "php": ">=8.0",
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
composer install
```

The `autoload` entry maps the namespace of the generated classes, `MyApp`, to the folder the generator writes
into. Composer finds new classes by their path, so you never have to touch it again.

### Settings and bootstrap

`app/settings.php` holds every setting as a nested array (nested keys become dotted keys such as
`database.hostname`):

```php
<?php

// Every setting of the team directory. Nested keys become dotted keys: database.hostname, ...
return [
    'app' => [
        'env' => 'development',           // anything but 'production' shows PHP errors
        'timezone' => 'UTC',
    ],
    'project' => [
        'package_name' => 'MyApp',        // namespace of the generated classes: app/lib/MyApp/
    ],
    'database' => [
        'engine' => 'mysql',              // mysql | mariadb | postgres
        'hostname' => '127.0.0.1',
        'port' => 3306,                   // 5432 for PostgreSQL
        'database_name' => 'teamdir',
        'username' => 'teamdir',
        'password' => 'secret',
    ],
    'settings' => [
        'use_pretty_url' => false,        // the generated pages need ?module=...&action=... links
    ],
    'form' => [
        'native_date_inputs' => true,     // <input type="date">: no date-picker library needed
        'restrict_to_rendered_fields' => true,   // ignore posted fields the form did not draw
    ],
];
```

Use your own database user and password. Two of these settings deserve a word:

- `settings.use_pretty_url = false` is required by the generated pages (their grids build query-string links).
- `form.restrict_to_rendered_fields = true` switches on the mass-assignment guard: a form applies only the fields
  it drew, so nobody can post `created_by` or another column the form does not show. It is off by default for
  backwards compatibility; turn it on in every new application ([06-forms.md](06-forms.md#the-mass-assignment-guard)).

`app/bootstrap.php` is required by every entry point: the web front controller, CLI scripts and the generator.

```php
<?php

declare(strict_types=1);

use Laf\Util\Settings;

require_once __DIR__ . '/../vendor/autoload.php';

// 1. every setting from one file
if (!Settings::getInstance()->loadFromFile(__DIR__ . '/settings.php')) {
    throw new RuntimeException('app/settings.php is missing or does not return an array');
}

// 2. the environment wins over the file (CI, production, a second database)
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
```

The session is not started here, because the generator and CLI scripts do not need one; the front controller of
step 3 starts it. Settings and their loaders are described in
[16-settings-locale-utilities.md](16-settings-locale-utilities.md).

### The schema

The four tables are the example schema used throughout this documentation. Save them as `database/schema.sql`:

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='{"linker": true}';
```

What the schema tells LAF, and why it is written this way:

- **Real foreign keys** (`employee.company_id → company`, both columns of `employee_project`). The generator only
  sees relations the database declares. They become select boxes, `getCompanyIdObj()` navigation and related-record
  tabs.
- **A linker table** (`employee_project`: two foreign keys to two different tables, nothing else but the key),
  marked with the table comment `{"linker": true}`. LAF turns it into a many-to-many picker on both forms:
  "Projects" on employees, "Team members" on projects (the labels come from the column comments). Linker tables are
  opt-in: without the marker the generator only prints a note and writes no picker.
- **Unique keys.** `company.name`, `employee.email`, `project.name` and the pair `(employee_id, project_id)` become
  checks that run before every write, with friendly messages on the forms (step 6).
- **JSON column comments** fine-tune the generated code: labels, hints, form sections and tabs. `setDisplayField`
  on `employee.email` makes the e-mail the text that represents an employee in select boxes and pickers (the table
  has no `name` or `label` column, which LAF would pick by itself).
- **`{"fields":{"setRequired":false}}`** on `record_status_id` and `is_active`: a `NOT NULL` column is generated
  as required even when it has a default; this comment lets the database default apply when the value is left
  out.
- **The metadata columns** `created_on`, `created_by`, `updated_on`, `updated_by` are filled by LAF on every write
  and shown in a "Details" tab. `record_status_id` makes deletes soft (`record_status_id = 0`).

The full comment reference is in [02-code-generation.md](02-code-generation.md#json-column-comments).

### Create the database and load the schema

```bash
mysql -u root -p -e "CREATE DATABASE teamdir CHARACTER SET utf8mb4"
mysql -u root -p teamdir < database/schema.sql
mysql -u root -p teamdir < vendor/lavdiu/laf/migrations/create_audit_logs_table.sql
```

The third file creates `audit_log`, where LAF records every insert, update and delete made through a model, with the
old and new values. Step 7 shows that history on the employee page.

> **Note (PostgreSQL):** set `'engine' => 'postgres'` and `'port' => 5432` in `settings.php`, and load
> `vendor/lavdiu/laf/docs/examples/schema.postgres.sql` (the same tables with `SERIAL`, `BOOLEAN`, `NUMERIC` and
> `COMMENT ON COLUMN`) and `vendor/lavdiu/laf/migrations/create_audit_logs_table_postgres.sql`. Everything else in
> this tutorial is the same: the generator quotes identifiers for the engine, and the hand-written SQL of step 5
> uses only functions both engines have.

## Step 2: generate the models and pages

First a dry run, which connects, reads the tables and lists what would be written, without touching the disk:

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
  write   app/lib/MyApp/Employee.php
  write   app/lib/generator_cache/pages/employee.page
  install app/lib/pages/employee.page
employee_project
  write   app/lib/MyApp/Base/BaseEmployeeProject.php
  write   app/lib/MyApp/EmployeeProject.php
  write   app/lib/generator_cache/pages/employee_project.page
  install app/lib/pages/employee_project.page
project
  write   app/lib/MyApp/Base/BaseProject.php
  write   app/lib/MyApp/Project.php
  write   app/lib/generator_cache/pages/project.page
  install app/lib/pages/project.page
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

`audit_log` was skipped on purpose (the generator leaves it out by default); you read it through
`Laf\Database\AuditLog`.

### A tour of what was generated

**Two classes per table.** [`Base/BaseEmployee.php`](examples/generated/MyApp/Base/BaseEmployee.php) describes the
table: one `Field` per column with its type, label and rules, the primary key, the foreign keys and the
many-to-many relations, plus typed accessors for every column (`setFirstNameVal()`, `getFirstNameVal()`,
`getFirstNameFld()`, `getFirstNameFormElement()`) and `getCompanyIdObj()` to follow the foreign key. It is
**rewritten on every run**, so never edit it. [`Employee.php`](examples/generated/MyApp/Employee.php) extends it
and is **written only once**: it is your class, the place for everything in step 4.

The column comments became code. From `BaseEmployee`:

```php
$this->getTable()->setPrimaryKey($pk);
$this->getTable()->setDisplayField($this->getTable()->getField("email"));

/**
 * Generating Foreign keys
 */
$this->getTable()->addForeignKey(
	(new ForeignKey())
		->setField($this->getTable()->getField("company_id"))
		->setKeyName('employee_company_fk')
		->setReferencingTable("company")
		->setReferencingField("id")
);

/**
 * Many-to-many relations through linker tables
 */
$this->belongsToMany("project")->through("employee_project")->keys("employee_id", "project_id")->label("Projects");
```

**The linker table became a relation.** `employee_project` is marked `{"linker": true}`, and it follows the linker
rule (exactly two foreign keys, to two different tables, no data columns besides its key), so `BaseEmployee` got
the `belongsToMany()` above, and `BaseProject` got the same relation seen from the other side:

```php
$this->belongsToMany("employee")->through("employee_project")->keys("project_id", "employee_id")->label("Team members");
```

That one line is why the employee form will show a "Projects" picker and the project form a "Team members" picker,
and why `$employee->manyToMany('employee_project')` (or `manyToMany('project')`) works. Without the table comment
the run would have printed `Note: employee_project looks like a linker table (employee ⇄ project): mark it with the
table comment {"linker": true} for many-to-many pickers` and written no such line. The marker and the rule are in
[02-code-generation.md](02-code-generation.md#linker-tables-and-many-to-many-relations).

**The unique key over two columns** became, in
[`BaseEmployeeProject.php`](examples/generated/MyApp/Base/BaseEmployeeProject.php):

```php
$this->getTable()->addUniqueKey("employee_project_unique", ['employee_id', 'project_id']);
```

so LAF checks the pair before inserting a link, as it checks `employee.email` (step 6).

**Pages.** `app/lib/generator_cache/pages/*.page` is the page as the current schema generates it, rewritten on
every run. `--install-pages` copied each one to `app/lib/pages/` because none existed yet; those live pages are
yours from now on and the generator never overwrites them. After a schema change, rerun the same command and
compare (`diff app/lib/generator_cache/pages/employee.page app/lib/pages/employee.page`). The anatomy of a page is
in [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md#anatomy-of-employeepage).

## Step 3: a front controller and a page shell

A generated page is a PHP script that reads the action and the id from the URL, builds its form and grid, and
prints a whole HTML document (or a redirect, or the JSON a grid asks for). It needs three things from you:
something that includes it (the front controller), an active session, and `MyApp\Factory::GeneralPage()`, the
document shell every page draws into.

### The page shell: `app/lib/MyApp/Factory.php`

```php
<?php

namespace MyApp;

use Laf\UI\Page\Html;
use Laf\Util\UrlParser;
use Laf\Util\Util;

/**
 * The page shell every page of the team directory draws into
 */
class Factory
{
    /** module => menu caption */
    private const MENU = [
        'employee' => 'Employees',
        'company' => 'Companies',
        'project' => 'Projects',
    ];

    public static function GeneralPage(): Html
    {
        return (new Html())
            ->setPageTitle('Team directory')
            ->addStylesheet('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css')
            ->addStylesheet('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css')
            ->addScript('assets/grid.js')
            ->addScript('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], true)
            ->setMenu(self::menu());
    }

    /** The navigation bar: raw HTML, so every value in it is escaped here */
    private static function menu(): string
    {
        $current = UrlParser::getModule();
        $links = '';
        foreach (self::MENU as $module => $caption) {
            $active = $module === $current ? ' active' : '';
            $links .= '<a class="nav-link' . $active . '" href="?module=' . Util::attr($module) . '">'
                . Util::e($caption) . '</a>';
        }
        return '<nav class="navbar navbar-expand bg-dark navbar-dark px-3 mb-2">'
            . '<a class="navbar-brand" href="./">Team directory</a>'
            . '<div class="navbar-nav flex-wrap">' . $links . '</div>'
            . '</nav>';
    }
}
```

All links are relative (`?module=…`, `./`, `assets/grid.js`), which is what lets the application live under any
sub-folder. `setMenu()` takes raw HTML, so the menu escapes what it prints with `Util::attr()` / `Util::e()`
([17-security.md](17-security.md)).

Copy the grid client that ships with LAF next to the front controller:

```bash
cp vendor/lavdiu/laf/sample_files/grid.js public/assets/grid.js
```

### The front controller: `public/index.php`

```php
<?php

declare(strict_types=1);

use Laf\Database\BaseObject;
use Laf\Util\UrlParser;

require __DIR__ . '/../app/bootstrap.php';

// 1. the session holds the CSRF tokens of the forms and the flash messages
session_start();

// 2. who is acting: created_by / updated_by and the audit log (no login in this tutorial: user 1)
BaseObject::setActiveUserId((int)($_SESSION['user_id'] ?? 1));

// 3. the module names the page file: ?module=employee -> app/lib/pages/employee.page
$module = UrlParser::getModule();
if ($module === '' || $module === 'home') {
    header('Location: ?module=employee');          // the start page, so every link carries a module
    exit;
}
$pageFile = __DIR__ . '/../app/lib/pages/' . $module . '.page';
if (!preg_match('/^[a-z0-9_]+$/', $module) || !is_file($pageFile)) {
    http_response_code(404);
    echo 'Page not found';
    exit;
}

// 4. the page reads the action and the id itself and prints the answer
require $pageFile;
```

Why each line is there:

- **`session_start()` before the page.** Forms keep their CSRF token in the session and the pages queue "Record
  saved" messages there for the next request. Without a session every form submission is refused.
- **`setActiveUserId()`.** There is no login in this tutorial, so everything is recorded as user 1. In a real
  application put the logged-in user's id in `$_SESSION['user_id']` at login.
- **The redirect for an empty module.** The generated pages build their links from the current module
  (`?module=employee&action=view&id=1`). Opening the bare folder redirects to a page that names its module, so those
  links are right from the first click.
- **The whitelist pattern and `is_file()`** keep `?module=../../something` from being included.
- **Nothing is printed before `require`**, because a page may answer with a redirect or with JSON.

### Fix the related-record tabs

The view page of a record has a "Related information" card with one tab per table that points at it (an
employee's projects, a company's employees, a project's team members). The current generator writes the record id
into those tabs' SQL with escaped quotes, so the tabs always come back empty
([details](12-routing-and-generated-pages.md#8-related-records-view-only)). Until that is fixed, bind the id as a
parameter in the three live pages; it is a one-line change each.

In `app/lib/pages/employee.page`, replace

```php
) l1 INNER JOIN `employee_project` jt ON l1.`project_id` = jt.`project_id` WHERE jt.`employee_id` = \' . ((int)UrlParser::getId()) . \'');
```

with

```php
) l1 INNER JOIN `employee_project` jt ON l1.`project_id` = jt.`project_id` WHERE jt.`employee_id` = :employee_id')
			->setParamsList(['employee_id' => (int)UrlParser::getId()]);
```

In `app/lib/pages/project.page`, replace

```php
) l1 INNER JOIN `employee_project` jt ON l1.`employee_id` = jt.`employee_id` WHERE jt.`project_id` = \' . ((int)UrlParser::getId()) . \'');
```

with

```php
) l1 INNER JOIN `employee_project` jt ON l1.`employee_id` = jt.`employee_id` WHERE jt.`project_id` = :project_id')
			->setParamsList(['project_id' => (int)UrlParser::getId()]);
```

In `app/lib/pages/company.page`, replace

```php
	WHERE 1=1  AND `employee`.`company_id` = \' . ((int)UrlParser::getId()) . \'

) l1 ');
```

with

```php
	WHERE 1=1  AND `employee`.`company_id` = :company_id

) l1 ')
			->setParamsList(['company_id' => (int)UrlParser::getId()]);
```

(On PostgreSQL the identifiers in these lines are quoted with `"` instead of backticks.)

### Open it

Serve `public/` with your web server. On a development server that serves a whole folder (XAMPP, a `www`
directory), with the project in `teamdir/`, open:

```
http://localhost/teamdir/public/
```

> **Note:** In production, make `public/` the document root. If the whole project folder sits inside a web root (as
> on a development server), `app/` is reachable too, and a `.page` file would be served as plain text. With Apache,
> an `app/.htaccess` file containing the line `Require all denied` closes it (do the same for `vendor/`).

What you get, page by page:

| URL (after `http://localhost/teamdir/public/`) | What you see |
|---|---|
| (nothing) | a 302 redirect to `?module=employee` |
| `?module=employee` | the "Employee List" card: a grid with paging, sorting, a search box per column and an export menu; "Add new" at the top right |
| `?module=company&action=new` | an empty company form; the "Contact" fieldset comes from the `group` comments |
| `?module=employee&action=new` | the employee form: a Company select, a "Projects" checkbox per project, a "Notes" tab |
| `?module=employee&action=view&id=1` | the record (values, not inputs), an Options menu with Update and Delete, and "Related information" with the employee's projects |
| `?module=employee&action=update&id=1` | the edit form with a Cancel link |
| `?module=nosuch` | 404 `Page not found` from the front controller |

Try it: add the company "Northwind Ltd", the projects "Apollo" (starts 2026-11-01, budget 25000.00) and "Gemini"
(budget 8000.00), then the employee Ada Lovelace (ada@northwind.example, born 1985-12-10, salary 5200.00, company
Northwind Ltd, project Apollo). Saving redirects to the view page, which starts with the message queued by the save:

```html
<div class='alert alert-success alert-dismissible fade show' role='alert'>
  <p>Record saved successfully</p>
  <button type='button' class='btn-close' data-dismiss='alert' data-bs-dismiss='alert' aria-label='Close'></button>
</div>
```

The list is filled by `grid.js`, which asks the same URL for its rows
(`?module=employee&action=list&load_grid_by_name=employee_list&page=1&limit=10`); the page answers with JSON
before it draws anything. Ada's row in that answer:

```json
{"employee_id":1,"employee_company_id":1,"company_name":"Northwind Ltd","employee_first_name":"Ada","employee_last_name":"Lovelace","employee_email":"ada@northwind.example","employee_birth_date":"1985-12-10","employee_salary":"5200.00","employee_is_active":0,"employee_notes":null,"employee_created_on":"2026-10-14 19:41:47","employee_created_by":1,"employee_updated_on":null,"employee_updated_by":null}
```

Two details to notice. `company_name` is there because the grid SQL joins every foreign key to show the referenced
row's display column. And `employee_is_active` is `0`: on the "new" form the Active checkbox starts unticked
(the record has no value yet, and the database default only applies when the column is left out of the INSERT,
while an unticked checkbox posts "no"). Step 4 fixes that in the model.

Delete works from the Options menu of a view page (try it on a scratch project). The link carries the form's CSRF
token (`?module=project&submodule=&action=delete&id=4&_csrf_token_project=1d1dbf4d…`), so a forged link
(`…&_csrf_token_project=forged`) deletes nothing and the list shows "The record was not deleted: the link is not valid, open the record and try again".
Companies are deleted softly (`record_status_id = 0`), employees and projects for real.

## Step 4: customise the Employee model

Everything that should hold for every form, page and script that touches an employee goes into
`app/lib/MyApp/Employee.php`: the generated file, extended with a helper, a validation rule, two hooks, a form
layout and a form hook. Replace the file with:

```php
<?php

namespace MyApp;

use Laf\UI\Form\DrawMode;
use Laf\UI\Form\Form;
use Laf\UI\Form\FormLayout;
use Laf\UI\Form\Input\ManyToManyAutocomplete;
use Laf\UI\Form\Input\Text;

/**
 * Class Employee
 * @package MyApp
 * Main Class for Table employee
 * This class inherits functionality from BaseEmployee.
 * It is generated only once, please include all logic and code here
 */
class Employee extends Base\BaseEmployee
{
	/**
	 * Employee constructor.
	 * @param int|string|array|null $id
	 */
	public function __construct($id = null)
	{
		parent::__construct($id);
	}

	/**
	 * Find one row by using the first result
	 * @param array $keyValuePairs
	 * @return Employee|null
	 * @throws \Exception
	 */
	public static function findOne(array $keyValuePairs) : ?Employee
	{
		return parent::bOfindOne($keyValuePairs);
	}

	/**
	 * @param array $keyValuePairs
	 * @param array $orderBy field => ASC|DESC
	 * @return Employee[]
	 * @throws \Exception
	 */
	public static function find(array $keyValuePairs = [], array $orderBy = [], ?int $limit = null, int $offset = 0) : array
	{
		return parent::bOfind($keyValuePairs, $orderBy, $limit, $offset);
	}

	/**
	 * "Ada Lovelace": for page titles and lists
	 */
	public function getFullName(): string
	{
		return trim($this->getFirstNameVal() . ' ' . $this->getLastNameVal());
	}

	/**
	 * Rules that involve more than one field, or today's date.
	 * field name => message ('' = a message about the whole record)
	 */
	protected function validateRecord(): array
	{
		$errors = [];
		$salary = $this->getSalaryVal();
		if ($this->getIsActiveVal() && ($salary === null || (float)$salary <= 0)) {
			$errors['salary'] = 'Active employees need a salary above 0';
		}
		$birthDate = $this->getBirthDateVal();
		if ($birthDate !== null && $birthDate >= date('Y-m-d')) {
			$errors['birth_date'] = 'The birth date must be in the past';
		}
		return $errors;
	}

	/**
	 * E-mail addresses are stored in lower case, whoever writes the row (form, import, script)
	 */
	protected function beforeInsert(): void
	{
		$this->normaliseEmail();
	}

	protected function beforeUpdate(): void
	{
		$this->normaliseEmail();
	}

	private function normaliseEmail(): void
	{
		$email = $this->getEmailVal();
		if ($email !== null) {
			$this->setEmailVal(mb_strtolower(trim($email)));
		}
	}

	/**
	 * The layout of every employee form (view, new, update)
	 */
	public function getFormLayout(): ?FormLayout
	{
		return FormLayout::create()
			->fieldset('Person', ['first_name', 'last_name', 'birth_date'], 3)
			->fieldset('Job', ['company_id', 'email', 'salary', 'is_active'], 2)
			->fieldset('Projects', ['employee_project'])
			->tab('Notes', ['notes'])
			->tab('Details', ['created_on', 'created_by', 'updated_on', 'updated_by'], 2, [DrawMode::VIEW])
			->exclude(['created_on', 'created_by', 'updated_on', 'updated_by'], DrawMode::EDIT)
			->hidden(['id'])
			->element('employee_project', 'Autocomplete');
	}

	/**
	 * Runs at the end of every getForm(): tweaks that need the built elements
	 */
	protected function configureForm(Form $form): void
	{
		// a new employee starts as active (an empty checkbox would post "not active")
		if ($form->getDrawMode() === DrawMode::INSERT && $this->getIsActiveVal() === null) {
			$this->setIsActiveVal(true);
		}

		$projects = $form->getRelationElement('employee_project');
		if ($projects instanceof ManyToManyAutocomplete) {
			$projects->setMinChars(0)                 // show the list as soon as the box gets focus
				->setAllowCreate(true)                // "+ Create "...": adds a project on the fly
				->setHint('Type to search, or type a new project name to create it');
		}

		$salary = $form->getFieldElement('salary');
		if ($salary instanceof Text) {
			$salary->setSuffix('EUR / month');
		}
		$form->setSubmitButtonLabel('Save employee');
	}
}
```

Nothing else changes: the generated `employee.page` calls `$employee->getForm($mode)`, which now applies this
layout and runs `configureForm()`. Here is what each part does.

### The display field

An employee is represented by its **display field** wherever another record shows it: the "Team members" picker on
the project form, the Team members select on the linker page, the joined column in grids. The schema chose
`email` (`"setDisplayField":true`), which is unique and therefore unambiguous. It has to be a real column, because
LAF selects and sorts it in SQL; for a combined text such as the full name, use a helper like `getFullName()` in
your own pages (the dashboard and the history card below do).

To label employees differently everywhere, set another column in the constructor, after `parent::__construct()`:

```php
$this->getTable()->setDisplayField($this->getTable()->getField('last_name'));
```

With that line, `Project::findOne(['name' => 'Apollo'])->manyToMany('employee_project')->options()` returns
`[2 => 'Hopper', 1 => 'Lovelace']` instead of the e-mail addresses (ordered by the display field). The tutorial
keeps the e-mail. See [02-code-generation.md](02-code-generation.md#display-columns).

### The record rules: `validateRecord()`

Column rules (required, maximum length, number range) are generated from the schema. Rules that combine fields or
depend on today's date go into `validateRecord()`, which returns `field => message`. Forms run it after the field
rules and show each message under its field; `$employee->validate()` runs it from code. Posting the new-employee
form with Active ticked, no salary and a birth date in 2031 answers with the form again:

```html
<div class='alert alert-danger' role='alert'>
  <p>The record was not saved, please correct the fields marked below</p>
</div>
...
<div id='employee_errors' class='alert alert-danger laf-form-errors' role='alert'>
	<ul class='mb-0'>
		<li>Active employees need a salary above 0</li>
		<li>The birth date must be in the past</li>
	</ul>
</div>
...
<input class='Laf-UI-Form-Input-Date form-control is-invalid' style='' type="date" name="ovegu_qngr" id="ovegu_qngr"
       value="2031-01-01" placeholder="Birth Date" aria-describedby='ovegu_qngr_feedback' aria-invalid='true' />
<div id='ovegu_qngr_feedback' class='invalid-feedback d-block'>The birth date must be in the past</div>
...
<div class='input-group'><input class='Laf-UI-Form-Input-Text form-control is-invalid' style='' type="text" name="fnynel"
       id="fnynel" placeholder="Salary" inputmode="decimal" step="0.01" aria-describedby='fnynel_feedback' aria-invalid='true' /><span
       class='input-group-text'>EUR / month</span></div>
<div id='fnynel_feedback' class='invalid-feedback d-block'>Active employees need a salary above 0</div>
```

(The input names are the ROT13 of the column names, `birth_date` = `ovegu_qngr`: an old LAF naming convention, not
a security feature.) What the user typed is kept, and the projects chosen so far stay as chips. Store calls from
your own code skip `validateRecord()` unless you switch on validation on store
([05-validation.md](05-validation.md#validation-on-store)).

### The hooks: `beforeInsert()` / `beforeUpdate()`

The hooks run inside `insert()` and `update()`, before the required and unique checks and before the SQL, so the
lower-cased address is what gets checked and stored, whether the row comes from a form, an import or a script.
Saving Grace Hopper with the e-mail `  Grace.Hopper@Northwind.Example ` stores `grace.hopper@northwind.example`. The
other hooks (`afterInsert()`, `afterUpdate()`, `beforeDelete()`, `afterDelete()`, `afterLoad()`) are listed in
[03-models.md](03-models.md#hooks).

### The layout: `getFormLayout()`

`FormLayout::create()` starts an empty layout (the generated `FormLayout::auto()` already places every field, so a
hand-made layout starts empty):

- three fieldsets, "Person" in three columns, "Job" in two, "Projects" for the picker (a relation is placed by the
  linker table's name, `employee_project`, like a field);
- two tabs: as soon as there is a tab, everything before it becomes a leading "General" tab. "Details" is limited
  to `DrawMode::VIEW`, and the metadata columns are excluded from the two edit modes (otherwise LAF would append
  them, since fields that no section lists are appended);
- `hidden(['id'])` keeps the key in the form without showing it;
- `element('employee_project', 'Autocomplete')` turns the Projects checkboxes into a search box with chips.

The new form now starts:

```html
<ul class='nav nav-tabs' id='employee_form_tabs_tab_links' role='tablist'> ... General ... Notes ... </ul>
...
<fieldset style='' class='Laf-UI-Container-Fieldset'>
	<legend>Person</legend>
	... First Name, Last Name, Birth Date side by side ...
<fieldset style='' class='Laf-UI-Container-Fieldset'>
	<legend>Job</legend>
	... Company, E-mail, Salary, Active ...
<fieldset style='' class='Laf-UI-Container-Fieldset'>
	<legend>Projects</legend>
```

and the view page shows the tabs General, Notes and Details. All `FormLayout` options are in
[06-forms.md](06-forms.md#laying-out-the-form-formlayout).

### The form hook: `configureForm()`

`configureForm()` runs at the end of every `getForm()`, after the layout, with the elements built:

- **Active by default.** On a new form the employee has no `is_active` value yet; setting it to `true` here ticks
  the checkbox (a field's value is mirrored into its element). A submitted form still wins, because the posted
  value is applied after this.
- **The Projects picker.** `getRelationElement('employee_project')` is the chips picker chosen by the layout.
  `setAllowCreate(true)` ends the result list with `+ Create "…"` when the typed text matches no project, and
  `setMinChars(0)` lists projects as soon as the box gets focus.
- **A suffix and a button label**, which are element options, so they belong here rather than in the layout.

The picker type, "create new" and the hint can also be said where the relation is declared. In the constructor,
after `parent::__construct($id)`, this refines the generated `belongsToMany()` (options given win, the rest is
kept):

```php
$this->belongsToMany('project')
	->chips()
	->creatable()
	->hint('Type to search, or type a new project name to create it');
```

With it, the `element('employee_project', 'Autocomplete')` line of the layout and the `setAllowCreate()` and
`setHint()` calls are not needed, and every layout of the model gets the chips, not only this one. `setMinChars(0)`
stays in `configureForm()`, because it is an option of the element, not of the relation. The tutorial keeps the
form-level version; both produce the same picker. The declaration is described in
[08-relations.md](08-relations.md#many-to-many-declaring-a-relation).

The picker in the form:

```html
<div class="laf-autocomplete laf-autocomplete-multi position-relative"
	data-laf-autocomplete="employee_project.project_id"
	data-multiple="1"
	data-name="employee_project[]"
	data-min-chars="0"
	data-no-results="No matches"
	data-create="1"
	data-create-text="Create &quot;%s&quot;"
	data-csrf-name="_csrf_token_employee"
	data-csrf-token="352341c1…">
	<input type="hidden" class="laf-autocomplete-empty" name="employee_project[]" value="" />
	<div class="laf-autocomplete-chips d-flex flex-wrap gap-1 mb-1" id="m2m_employee_project_chips" aria-live="polite">
	</div>
	<input type="text" class="Laf-UI-Form-Input-ManyToManyAutocomplete form-control laf-autocomplete-input" ... />
	...
</div>
<small id='m2m_employee_project_hint' class='form-text text-muted'>Type to search, or type a new project name to create it</small>
```

The searches and the creates go to the page itself: `employee.page` calls `$form->handleLookupRequest()` right
after `getForm()`, which answers them with JSON and ends the request. From the running application:

| Request | Answer |
|---|---|
| `GET ?module=employee&action=new&laf_lookup=employee_project.project_id&laf_q=` | `{"results":[{"value":"1","label":"Apollo"},{"value":"2","label":"Gemini"}],"more":false}` |
| `GET …&laf_q=gem` | `{"results":[{"value":"2","label":"Gemini"}],"more":false}` |
| `POST …&laf_create=1` with `laf_q=Hermes` and the form's `_csrf_token_employee` | `{"value":"3","label":"Hermes","created":true}`: a new `project` row |
| the same POST again | `{"value":"3","label":"Hermes","created":false}`: the existing row is reused |
| `GET …&laf_create=1` | 405 `{"error":"Use POST"}` |

Creating writes to the database, so it needs the form's CSRF token (the script sends the one in `data-csrf-token`)
and only works on edit forms. The new project goes through `Project::insert()`, so it is audited like any other
row. Saving Grace Hopper with the chips Apollo and Hermes stores the employee and both links in one transaction.
The JSON contract, the dual-list picker and the JavaScript events are in [08-relations.md](08-relations.md).

## Step 5: a directory grid and a dashboard

Pages do not have to be generated. Any `.page` file in `app/lib/pages/` is served by the front controller, so a
hand-written page is just another file there.

### `app/lib/pages/directory.page`

A searchable, sortable, exportable list of everyone, with the company name and the number of projects:

```php
<?php

use Laf\UI\Component\Link;
use Laf\UI\Container\ContainerType;
use Laf\UI\Container\HtmlContainer;
use Laf\UI\Grid\PhpGrid\ActionButton;
use Laf\UI\Grid\PhpGrid\Column;
use Laf\UI\Grid\PhpGrid\PhpGrid;
use Laf\UI\Page\AdminPage;
use MyApp\Factory;

// 1. the grid: the same name and SQL on every request (grid.js asks this page for the rows)
$grid = new PhpGrid('directory');
$grid->setTitle('Team directory')
	->setSqlQuery("
		SELECT e.id,
		       CONCAT(e.first_name, ' ', e.last_name) AS full_name,
		       e.email,
		       c.name AS company,
		       e.salary,
		       e.is_active,
		       (SELECT COUNT(*) FROM employee_project ep WHERE ep.employee_id = e.id) AS projects
		FROM employee e
		LEFT JOIN company c ON c.id = e.company_id
	")
	->setRowsPerPage(10)
	->setRowsPerPageOptions([10, 25, 50, 0])   // 0 = All
	->setSortDetails('full_name', 'ASC')       // until the user clicks a header
	->setEnableWildCardSearch(true)            // "lace" finds "Lovelace"
	->setColumnTotals(['salary'])
	->setCsvBom(true);                         // Excel opens the CSV export with the right accents

$grid->addColumn((new Column('id', 'ID', false))->setExportable(false));    // hidden, used in the links
$grid->addColumn((new Column('full_name', 'Name'))->setHref('?module=employee&action=view&id={id}'));
$grid->addColumn(new Column('email', 'E-mail'));
$grid->addColumn((new Column('company', 'Company'))->setCaseInsensitive(true));
$grid->addColumn(new Column('salary', 'Salary'));
$grid->addColumn((new Column('is_active', 'Active'))->setSearchOperator('eq'));
$grid->addColumn((new Column('projects', 'Projects'))->setSearchOperator('eq'));

$grid->addActionButton(new ActionButton('View', '?module=employee&action=view&id={id}', 'fa fa-eye'));
$grid->addActionButton(new ActionButton('Edit', '?module=employee&action=update&id={id}', 'fa fa-edit'));

// 2. a data or export request from grid.js is answered here (JSON, XLSX or CSV) and ends the request
if ($grid->isReadyToHandleRequests()) {
	$grid->bootstrap();
}

// 3. the page itself: an empty table that grid.js fills
$page = new AdminPage();
$page->setTitle('Directory');
$page->setTitleIcon('fa fa-address-book');
$page->setContainerType(ContainerType::TYPE_FLUID);
$page->addLink(new Link('Add employee', '?module=employee&action=new', 'fa fa-plus-square', [], ['btn', 'btn-sm', 'btn-outline-success']));
$page->addComponent(new HtmlContainer($grid->draw()));

$html = Factory::GeneralPage();
$html->addComponent($page);
echo $html->draw();
```

How it works: `draw()` prints an empty table and a small script; `grid.js` then requests
`?module=directory&load_grid_by_name=directory&page=1&limit=10` (plus `sort`, `dir` and `searchParams` once the user
sorts or searches), and `bootstrap()` answers with one page of rows as JSON. The grid wraps your SQL as a derived
table, so the column names are the aliases (`full_name`, `company`), searches and sorting work on them without a
`WHERE` of your own, and every searched value is bound as a parameter. The first page (excerpt):

```json
{
    "rowCount": 2,
    "sort": "full_name",
    "columnTotals": {
        "salary": "11300.50"
    },
    "rows": [
        {
            "id": 1,
            "full_name": "Ada Lovelace",
            "email": "ada@northwind.example",
            "company": "Northwind Ltd",
            "salary": "5200.00",
            "is_active": 0,
            "projects": 1
        },
        {
            "id": 2,
            "full_name": "Grace Hopper",
            "email": "grace.hopper@northwind.example",
            "company": "Northwind Ltd",
            "salary": "6100.50",
            "is_active": 1,
            "projects": 2
        }
    ]
}
```

Typing `hop` in the Name search box narrows it to Grace Hopper, and the salary total follows the filter
(`6100.50`). The export menu offers Excel and CSV, made in the browser or on the server; the server CSV
(`…&export_grid_to_csv=1`) is:

```text
Name,E-mail,Company,Salary,Active,Projects
"Ada Lovelace",ada@northwind.example,"Northwind Ltd",5200.00,0,1
"Grace Hopper",grace.hopper@northwind.example,"Northwind Ltd",6100.50,1,2
```

(starting with a UTF-8 byte order mark because of `setCsvBom(true)`; the hidden `id` column is left out because it
is not exportable). The search operators (`=`, `>=`, `!=` typed in a search box), totals, exports and the JSON
contract are in [10-grids.md](10-grids.md).

### `app/lib/pages/dashboard.page`

A start page with a few numbers, computed with the `Query` builder of each model, and the newest employees:

```php
<?php

use Laf\UI\Container\ContainerType;
use Laf\UI\Container\Div;
use Laf\UI\Container\HtmlContainer;
use Laf\UI\Flash;
use Laf\UI\Page\AdminPage;
use Laf\UI\Table\Table;
use Laf\Util\Locale;
use Laf\Util\Util;
use MyApp\Company;
use MyApp\Employee;
use MyApp\Project;
use MyApp\Factory;

// 1. the numbers: one query each, built by the models (column names are checked, values bound)
$employees = Employee::query()->count();
$active = Employee::query()->where('is_active', true)->count();
$payroll = Employee::query()->where('is_active', true)->sum('salary');       // "11300.50" or null
$averageSalary = Employee::query()->where('is_active', true)->avg('salary');
$companies = Company::query()->where('record_status_id', '!=', 0)->count();  // soft-deleted ones left out
$projects = Project::query()->count();
$budget = Project::query()->sum('budget');

$cards = [
	['Employees', (string)$employees, $active . ' active', '?module=directory'],
	['Monthly payroll', Locale::formatNumber($payroll, 2, '0.00'), 'average ' . Locale::formatNumber($averageSalary, 2, '-'), '?module=directory'],
	['Companies', (string)$companies, '', '?module=company'],
	['Projects', (string)$projects, 'budget ' . Locale::formatNumber($budget, 2, '0.00'), '?module=project'],
];

// 2. one Bootstrap card per number: full width on phones, two per row on tablets, four on desktops
$row = new Div(['row', 'g-3', 'mb-3']);
foreach ($cards as [$title, $number, $detail, $href]) {
	$row->addComponent((new Div(['col-12', 'col-sm-6', 'col-xl-3']))->addComponent(new HtmlContainer(
		'<a class="card h-100 text-decoration-none" href="' . Util::attr($href) . '"><div class="card-body">'
		. '<div class="text-muted small">' . Util::e($title) . '</div>'
		. '<div class="fs-3 fw-bold">' . Util::e($number) . '</div>'
		. '<div class="small">' . Util::e($detail) . '</div>'
		. '</div></a>'
	)));
}

// 3. the five newest employees, as a plain table (cells are escaped by Table)
$newest = [];
foreach (Employee::query()->orderBy('id', 'DESC')->limit(5)->get() as $employee) {
	$newest[] = [
		$employee->getFullName(),
		(string)$employee->getCompanyIdObj()->getNameVal(),
		Locale::formatDate($employee->getCreatedOnVal(), '-'),
	];
}

$page = new AdminPage();
$page->setTitle('Dashboard');
$page->setTitleIcon('fa fa-gauge');
$page->setContainerType(ContainerType::TYPE_FLUID);
foreach (Flash::drain() as $alert) {
	$page->addComponent($alert);
}
$page->addComponent($row);
$page->addComponent(new HtmlContainer('<h2 class="h6 mt-2">Newest employees</h2>'));
$page->addComponent(Table::fromArray($newest, ['Name', 'Company', 'Added on'])->bootstrap());

$html = Factory::GeneralPage();
$html->addComponent($page);
echo $html->draw();
```

The aggregates return what the driver returns (a numeric string such as `"6100.50"` for a DECIMAL sum, `null` when
no row matches), and `Locale::formatNumber()` turns them into display text with an "empty" fallback. With the data
so far the four cards read:

| Card | Number | Detail |
|---|---|---|
| Employees | 2 | 1 active |
| Monthly payroll | 6,100.50 | average 6,100.50 |
| Companies | 1 | |
| Projects | 3 | budget 33,000.00 |

(Ada is still inactive from step 3; step 7 changes that.) The table below lists Grace Hopper and Ada Lovelace,
both of Northwind Ltd, "Added on" October 14, 2026 (`Locale::formatDate()` in the default `locale` format). The
cards are anchors with escaped content, because `HtmlContainer` prints raw HTML; `Table` escapes its cells itself.
The `Query` methods are in [04-queries.md](04-queries.md), the containers in [11-pages-and-ui.md](11-pages-and-ui.md).

### Menu and start page

Add the two pages to the menu in `app/lib/MyApp/Factory.php`:

```php
    /** module => menu caption */
    private const MENU = [
        'dashboard' => 'Dashboard',
        'directory' => 'Directory',
        'employee' => 'Employees',
        'company' => 'Companies',
        'project' => 'Projects',
    ];
```

and make the dashboard the start page in `public/index.php`:

```php
    header('Location: ?module=dashboard');         // the start page, so every link carries a module
```

`http://localhost/teamdir/public/` now opens the dashboard, with "Dashboard" highlighted in the menu.

## Step 6: unique values

The schema has four unique keys. LAF reads them into the models (`setUnique(true)` on a column,
`addUniqueKey()` for the pair) and checks them before every write, so users get a message on the field instead of
a database error.

**An e-mail that exists.** A new employee "Ada King" with the e-mail `ADA@northwind.example` (Ada Lovelace has
`ada@northwind.example`; MySQL compares case-insensitively here):

```html
<div id='employee_errors' class='alert alert-danger laf-form-errors' role='alert'>
	<ul class='mb-0'>
		<li>E-mail: this value is already in use</li>
	</ul>
</div>
...
<input class='Laf-UI-Form-Input-Text form-control is-invalid' style='' type="text" name="rznvy" id="rznvy"
       value="ADA@northwind.example" maxlength="150" required="required" placeholder="Email"
       aria-describedby='rznvy_feedback' aria-invalid='true' />
<div id='rznvy_feedback' class='invalid-feedback d-block'>E-mail: this value is already in use</div>
```

The form reports taken values together with the other messages, and if two users save the same address at the
same moment, the database's own refusal is turned into the same field message rather than an error page.

**The same project twice.** On the employee form this cannot happen: the chips search leaves out projects that are
already chosen, and the server collapses duplicate keys in a submission. The linker table has a page of its own,
though (`?module=employee_project&action=new`, generated like the others), and there the pair can be entered by
hand. Choosing Grace Hopper (the Team members select shows e-mails, the employee's display field) and Apollo again
gives:

```html
<div id='employee_project_errors' class='alert alert-danger laf-form-errors' role='alert'>
	<ul class='mb-0'>
		<li>Team members + Projects: this combination is already in use</li>
	</ul>
</div>
...
<div id='rzcyblrr_vq_feedback' class='invalid-feedback d-block'>Team members + Projects: this combination is already in use</div>
```

The message names both columns by their labels. Most applications never link to the linker page (the pickers
maintain the links); you can delete `employee_project.page` or leave it for administrators.

**From code.** `bin/unique-check.php` shows the same checks without a form:

```php
<?php

declare(strict_types=1);

use Laf\Database\BaseObject;
use Laf\Exception\UniqueFieldDuplicateValueException;
use MyApp\Employee;
use MyApp\EmployeeProject;
use MyApp\Project;

require __DIR__ . '/../app/bootstrap.php';
BaseObject::setActiveUserId(1);

$grace = Employee::findOne(['email' => 'grace.hopper@northwind.example']);
$apollo = Project::findOne(['name' => 'Apollo']);          // Grace is on Apollo already

// 1. a second link row for the same pair
$link = (new EmployeeProject())
    ->setEmployeeIdVal($grace->getIdVal())
    ->setProjectIdVal($apollo->getIdVal());
print_r($link->duplicateUniqueKeys());                     // the unique keys these values would break
try {
    $link->store();
} catch (UniqueFieldDuplicateValueException $ex) {
    echo $ex->getMessage(), PHP_EOL;
}

// 2. the relation: duplicates in the list are collapsed, links that exist are kept
$projects = $grace->manyToMany('employee_project');
$ids = $projects->ids();
print_r($projects->sync(array_merge($ids, $ids)));         // nothing to add, nothing to remove
```

```bash
php bin/unique-check.php
```

```text
Array
(
    [0] => Array
        (
            [0] => employee_id
            [1] => project_id
        )

)
Fields employee_id, project_id must be unique together: values '2', '1' already exist in employee_project
Array
(
    [added] => Array
        (
        )

    [removed] => Array
        (
        )

)
```

`duplicateUniqueKeys()` lists the keys another row already holds, one array of columns per key, before anything
is written; `store()` throws `UniqueFieldDuplicateValueException` for the first one. `sync()` makes the employee
linked to exactly the given projects and writes only the difference. More in
[03-models.md](03-models.md#exceptions) and [05-validation.md](05-validation.md).

## Step 7: the history of an employee

Every write through a model has been recorded in `audit_log` since step 3. Show it on the employee's view page:
three small edits in `app/lib/pages/employee.page`, the live page that is yours since step 2.

1. Add four imports after `use MyApp\Employee;` at the top:

```php
use Laf\Database\AuditLog;
use Laf\UI\Table\Table;
use Laf\Util\Locale;
use Laf\Util\Util;
```

2. In `case 'view':`, find

```php
		$dd->addLink($updateLink)
			->addLink($deleteLink);
		$page->addLink($dd);

		$html->addComponent($page);
```

and replace it with

```php
		$dd->addLink($updateLink)
			->addLink($deleteLink);
		$page->addLink($dd);
		$page->setSubtitle(Util::e($employee->getFullName()));

		$html->addComponent($page);

		// History: every insert / update / delete of this employee, newest first (audit_log)
		$show = fn ($value): string => $value === null ? '(empty)' : (is_bool($value) ? ($value ? 'Yes' : 'No') : (string)$value);
		$history = [];
		foreach (AuditLog::findForObject($employee, 50) as $entry) {
			$changes = [];
			foreach ($entry->getChanges() as $change) {
				$name = (string)$change['field'];
				$label = $employee->getTable()->hasField($name) ? $employee->getTable()->getField($name)->getLabel() : $name;
				$changes[] = sprintf('%s: %s → %s', $label, $show($change['old_value']), $show($change['new_value']));
			}
			$history[] = [
				Locale::formatDateTime($entry->getCreatedOnVal()),
				(string)$entry->getActionVal(),
				'user ' . $entry->getUserIdVal(),
				implode('; ', $changes),
			];
		}
		$historyPage = new AdminPage();
		$historyPage->setTitle('History');
		$historyPage->setTitleIcon('fa fa-clock-rotate-left');
		$historyPage->setContainerType(ContainerType::TYPE_FLUID);
		$historyPage->addComponent(Table::fromArray($history, ['When', 'Action', 'By', 'Changes'])->bootstrap());
		$html->addComponent($historyPage);
```

The subtitle is raw HTML (`setSubtitle()` prints what it gets), hence `Util::e()`; the table cells are escaped by
`Table`. `AuditLog::findForObject()` returns the entries of this row, newest first, and `getChanges()` decodes the
JSON list of `{field, old_value, new_value}`.

Now edit Ada Lovelace: tick Active, set the salary to 5400.00 and write "Team lead from November" in the Notes tab.
Her view page gets a subtitle "Ada Lovelace" next to the card title, and between the record and "Related
information" a History card:

| When | Action | By | Changes |
|---|---|---|---|
| October 14, 2026 19:42 | UPDATE | user 1 | Salary: 5200.00 → 5400.00; Active: No → Yes; Notes: (empty) → Team lead from November |
| October 14, 2026 19:41 | INSERT | user 1 | Company: (empty) → 1; First Name: (empty) → Ada; Last Name: (empty) → Lovelace; E-mail: (empty) → ada@northwind.example; Birth Date: (empty) → 1985-12-10; Salary: (empty) → 5200.00; Active: (empty) → No |

An update records only the columns that changed (saving the form unchanged writes nothing and logs nothing), and
the user is the one given to `BaseObject::setActiveUserId()` in the front controller.

> **Note:** Project links are rows of `employee_project`, so adding or removing a project is logged under that
> table (one INSERT or DELETE per link, with `employee_id` and `project_id` in the changes), not under the
> employee. Read them with `AuditLog::findBy(['table_name' => 'employee_project'])` or
> `findForRecord('employee_project', $linkId)`; see [14-audit-log.md](14-audit-log.md).

The dashboard now counts two active employees, with a monthly payroll of 11,500.50.

## Step 8: where to go next

You have used most of LAF's moving parts once. To go further:

- **Log in real users.** Put the user's id in `$_SESSION['user_id']` at login and call
  `$form->regenerateCsrfToken()` after login and logout; add the access check where the front controller says "the
  page does the rest" (a module whitelist per role is enough to start). See [17-security.md](17-security.md).
- **Change the schema.** Add a column, rerun `vendor/bin/laf-generate --bootstrap=app/bootstrap.php --lib=app/lib
  --install-pages`, then compare `generator_cache/pages/*.page` with your live pages and carry over what you want.
  Your model classes are never overwritten ([02-code-generation.md](02-code-generation.md#regeneration-rules)).
- **Shape the generated pages without editing them**: labels, hints, sections and the picker type through JSON
  column comments, page texts through label translations
  ([12-routing-and-generated-pages.md](12-routing-and-generated-pages.md#generator-options-for-pages)).
- **Hide soft-deleted companies** in the company list grid (`AND record_status_id <> 0` in its SQL) and in the
  Company select of the employee form (`setDbSelectionCriteria()` on the field, [08-relations.md](08-relations.md)).
- **Turn on validation on store** (`orm.validate_on_store`) so imports and scripts obey `validateRecord()` too
  ([05-validation.md](05-validation.md#validation-on-store)).
- **Write tests** for the model rules and the forms with `Form::buildRequest()` and `setRequest()`
  ([18-testing-and-tooling.md](18-testing-and-tooling.md)).
- **Upgrading an older LAF application?** See [20-upgrading.md](20-upgrading.md).

## See also

- [01-getting-started.md](01-getting-started.md): installation, layout and the first page, in reference form
- [02-code-generation.md](02-code-generation.md): `laf-generate`, column comments, linker tables, unique keys
- [03-models.md](03-models.md) and [04-queries.md](04-queries.md): records, hooks, `Query`
- [05-validation.md](05-validation.md): field rules, `validateRecord()`, validation on store
- [06-forms.md](06-forms.md): `FormLayout`, `configureForm()`, CSRF, the mass-assignment guard
- [08-relations.md](08-relations.md): foreign-key inputs, the lookup endpoint, many-to-many pickers
- [10-grids.md](10-grids.md): `PhpGrid` and `grid.js`
- [11-pages-and-ui.md](11-pages-and-ui.md): `Html`, `AdminPage`, containers, `Flash`
- [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md): `UrlParser`, the front controller, the generated pages
- [14-audit-log.md](14-audit-log.md): the `audit_log` table and reading history
