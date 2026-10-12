# LAF

LAF (Lulzim Application Framework) is a PHP 8 framework for database-driven web applications. It is
**database-first**: you design the tables, the generator writes one Active Record model and one CRUD page per
table, and you add behaviour in the model class. MySQL/MariaDB and PostgreSQL are supported.

- Models: `Laf\Database\BaseObject` (CRUD, hooks, validation, audit log, transactions, a single-table `query()`)
- Forms: `$model->getForm($drawMode)` renders and processes Bootstrap 5 forms with CSRF protection
- Pages and UI: `Laf\UI\Page\*`, containers, components, an HTML table, grids
- Files: `Laf\Filesystem\Document` stores uploads with thumbnails and serves them
- Code generation: `Laf\Generator\DatabaseGenerator` from `information_schema` and JSON column comments

Requires PHP 8.0 or newer with `pdo`, `mbstring`, `json` and `gd`.

```bash
composer require lavdiu/laf
```

## Quick start

### 1. Bootstrap

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Laf\Database\BaseObject;
use Laf\Util\Settings;

Settings::set('database.engine', 'mysql');          // or 'postgres'
Settings::set('database.hostname', 'localhost');
Settings::set('database.database_name', 'myapp');
Settings::set('database.username', 'myapp');
Settings::set('database.password', 'secret');
Settings::set('project.package_name', 'MyApp');     // namespace of the generated classes

BaseObject::setActiveUserId($_SESSION['user_id'] ?? null);   // who the audit log attributes changes to
```

`Settings::load([...])` and `Settings::loadFromFile('config.php'|'.json'|'.ini')` take a nested array and
flatten it to dotted keys.

### 2. Generate the models and pages

```php
use Laf\Generator\DatabaseGenerator;

$generator = new DatabaseGenerator(__DIR__ . '/app/lib');       // writes app/lib/MyApp/...
$generator
    ->setExcludedTables(['audit_log'])
    ->setGenerateAutoloader(false)                                 // Composer autoloads the package
    ->generateEverything();
```

For every table this creates `MyApp\Base\BaseCustomer` (regenerated every run, never edit), `MyApp\Customer`
(created once, your code goes here) and a `customer.page` CRUD page. JSON column comments tune the result:

```sql
COMMENT ON COLUMN customer.email IS '{"fields":{"setLabel":"E-mail","setRequired":true},"form":{"group":"Contact"}}';
```

### 3. Work with records

```php
use MyApp\Customer;

$customer = new Customer();
$customer->setNameVal('Ada Lovelace');
$customer->setEmailVal('ada@example.com');
$customer->store();                                // insert or update, audited

$customer = new Customer(42);                      // load by id
$order->getCustomerIdObj()->getNameVal();          // follow a foreign key

$recent = Customer::query()
    ->where('city', 'London')
    ->whereNull('deleted_on')
    ->orderBy('created_on', 'DESC')
    ->limit(10)
    ->get();                                       // Customer[]
```

Override `beforeInsert()`, `afterUpdate()`, `validate()` and friends in `MyApp\Customer`. Wrap several writes
in `BaseObject::transaction(fn () => ...)`.

### 4. Forms

```php
use Laf\UI\Form\DrawMode;
use Laf\Util\UrlParser;

$customer = new Customer(UrlParser::getId());
$form = $customer->getForm(DrawMode::UPDATE);      // laid out by the model's getFormLayout()

if ($form->isSubmitted()) {
    try {
        $id = $form->processForm();                // CSRF check, validation, insert or update
        UrlParser::redirectToViewPage($id);
    } catch (\Laf\Exception\FormValidationException $ex) {
        // the redrawn form shows $ex->getErrors() next to the fields
    }
}
$page->addComponent($form);
```

### 5. Routing

Pages are addressed as `/module/submodule/action/id` (or `?module=…&submodule=…&action=…&id=…` when
`settings.use_pretty_url` is false). `UrlParser::getModule()`, `getAction()`, `getId()` read the request;
`getViewLink($id)`, `getListLink()`, `getNewLink()` build links.

## Audit log

Every insert, update and delete made through a model is written to the `audit_log` table (old and new values
as JSON). Create it with `migrations/create_audit_logs_table.sql` or `…_postgres.sql`. See
`examples/audit_logging_example.php` and `AuditLog::findForObject($model)`.

## Development

```bash
composer install
php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Unit        # no database needed

# integration tests against a database (MariaDB or PostgreSQL)
LAF_DB_ENGINE=mysql LAF_DB_HOST=127.0.0.1 LAF_DB_PORT=3306 LAF_DB_NAME=laf_test LAF_DB_USER=root LAF_DB_PASS= \
    php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Integration
LAF_DB_ENGINE=postgres LAF_DB_PORT=5432 LAF_DB_USER=postgres LAF_DB_PASS=postgres \
    php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Integration

vendor/bin/phpstan analyse            # level 5 (phpstan.neon)
vendor/bin/rector process --dry-run   # advisory only, PHP 8.0 set (rector.php)
```

`docker-compose up -d` starts a MariaDB (port 3307) and a PostgreSQL (port 5433) for the integration suite;
`.github/workflows/ci.yml` runs the unit suite on PHP 8.1 to 8.5, PHPStan, and the integration suite on both
engines.

`CLAUDE.md` holds the detailed architecture notes; `sample_files/` shows a bootstrap, a generator script and a
grid.

## License

MIT
