# Testing and tooling

LAF ships with a PHPUnit setup split into a database-free unit suite and an integration suite that runs against
MariaDB and PostgreSQL, a Docker Compose file that provides both databases, PHPStan at level 5, an advisory Rector
configuration, and a GitHub Actions workflow that runs all of it. This chapter explains how to run each of them
when you work on LAF itself, and how to test your own application's models and forms with the same tools,
including submitting a form from a test without a browser.

On this page:

- [The two suites](#the-two-suites)
- [Running the unit suite](#running-the-unit-suite)
- [Running the integration suite](#running-the-integration-suite)
- [Databases with Docker Compose](#databases-with-docker-compose)
- [Composer scripts](#composer-scripts)
- [Testing your application](#testing-your-application)
  - [Bootstrap](#bootstrap)
  - [Models](#models)
  - [Forms without a browser](#forms-without-a-browser)
  - [Documents and redirects](#documents-and-redirects)
- [The LafShell fixtures](#the-lafshell-fixtures)
- [PHPStan](#phpstan)
- [Rector](#rector)
- [Continuous integration](#continuous-integration)

## The two suites

`phpunit.xml` (PHPUnit 9.6) defines two suites with `tests/bootstrap.php` as bootstrap:

| Suite | Contents | Needs |
|---|---|---|
| `Unit` | everything under `tests/` except the files below | nothing: no database, no web server |
| `Integration` | `DbTest`, `BaseObjectTest`, `PhpGridTest`, `GeneratorTest`, `DocumentIntegrationTest`, `GeneratorCliIntegrationTest` | a MariaDB/MySQL or PostgreSQL database |

The unit suite covers settings, routing, fields and field types, keys, forms and every input, layouts, UI
components, the query builder's SQL, the audit log payloads, documents (with a fake model), the grid's request
parsing and SQL building, and the generator's pure parts. Models in unit tests come from the LafShell fixtures
([below](#the-lafshell-fixtures)).

## Running the unit suite

```bash
php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Unit
```

```text
PHPUnit 9.6.38 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: D:\www\laf\phpunit.xml
...
OK (777 tests, 3278 assertions)
```

Run it under `E_ALL`: the code base must stay free of warnings and deprecations on the newest PHP (8.5) while its
syntax stays within the Composer floor (PHP 8.0). One file or one test:

```bash
vendor/bin/phpunit tests/LocaleTest.php
vendor/bin/phpunit --filter numbers_are_formatted_with_the_default_separators tests/LocaleTest.php
```

## Running the integration suite

`tests/bootstrap.php` configures Settings from `LAF_DB_*` environment variables (defaults in brackets) and sets
`project.package_name` to `LafShell`:

| Variable | Default |
|---|---|
| `LAF_DB_ENGINE` | `mysql` (or `postgres`) |
| `LAF_DB_HOST` | `127.0.0.1` |
| `LAF_DB_PORT` | `3306`, or `5432` for postgres |
| `LAF_DB_NAME` | `laf_test` |
| `LAF_DB_USER` | `root` |
| `LAF_DB_PASS` | empty |

```bash
# MariaDB / MySQL
LAF_DB_ENGINE=mysql LAF_DB_HOST=127.0.0.1 LAF_DB_PORT=3306 LAF_DB_NAME=laf_test LAF_DB_USER=root LAF_DB_PASS= \
    php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Integration

# PostgreSQL (credentials of your server; the Compose service below uses root/root on port 5433)
LAF_DB_ENGINE=postgres LAF_DB_PORT=5432 LAF_DB_USER=postgres LAF_DB_PASS=postgres \
    php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Integration
```

On Windows PowerShell set the variables first (`$env:LAF_DB_ENGINE = 'mysql'`).

> **Note:** the integration tests **drop and create their own tables** (`dummy_table`, `code_table`, `link_table`,
> `status_table`, `audit_log`, `document`, `m2m_person`, `m2m_group`, `m2m_person_group`) and empty them between
> tests. Point them at a dedicated database such as `laf_test`, never at one that holds data you care about. When
> no connection can be made, the integration tests skip themselves instead of failing.

## Databases with Docker Compose

`docker-compose.yml` provides both engines and a PHP 8.5 CLI container (`Dockerfile`: `php:8.5-cli` with
`pdo_mysql`, `pdo_pgsql`, `gd`, `mbstring`, `zip` and Composer):

| Service | Image | Host port | Credentials / database |
|---|---|---|---|
| `mariadb` | `mariadb:10.11` | 3307 | `root` / `root`, `laf_test` |
| `postgres` | `postgres:16` | 5433 | `root` / `root`, `laf_test` |
| `php` | built from `Dockerfile` | | the repository mounted at `/app` |

From the host:

```bash
docker-compose up -d mariadb postgres

LAF_DB_ENGINE=mysql LAF_DB_PORT=3307 LAF_DB_USER=root LAF_DB_PASS=root \
    php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Integration
LAF_DB_ENGINE=postgres LAF_DB_PORT=5433 LAF_DB_USER=root LAF_DB_PASS=root \
    php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Integration
```

Inside the PHP container the hosts are the service names and the ports are the internal ones:

```bash
docker-compose run --rm -e LAF_DB_HOST=mariadb -e LAF_DB_PASS=root php \
    php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Integration
```

## Composer scripts

| Script | Runs |
|---|---|
| `composer test` | `phpunit --testsuite Unit` |
| `composer test:integration` | `phpunit --testsuite Integration` (uses the `LAF_DB_*` environment) |
| `composer stan` | `phpstan analyse` |
| `composer rector` | `rector process --dry-run` |
| `composer check` | `test` then `stan` |

The scripts do not add `-d error_reporting=E_ALL`; use the full commands above when you want the strict run that CI
does.

## Testing your application

Nothing in LAF requires a web server to run: models, forms and pages are plain objects, so your application can be
tested with PHPUnit the same way LAF is.

### Bootstrap

Use the application's own bootstrap (settings, autoloader), pointed at a **test database** with the same schema:

```php
<?php
// tests/bootstrap.php of your application
putenv('LAF_DB_NAME=myapp_test');          // or load a settings file for tests
require __DIR__ . '/../app/bootstrap.php';

\Laf\Database\BaseObject::setActiveUserId(1);    // created_by / updated_by / audit user
```

```xml
<!-- phpunit.xml of your application -->
<phpunit bootstrap="tests/bootstrap.php" colors="true">
    <testsuites>
        <testsuite name="App">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

Keep tests independent: create the rows a test needs, and delete them in `tearDown()` (or wrap each test in a
transaction you roll back, as long as the code under test does not commit). Disable the audit log in setup code
that only prepares data (`AuditLog::disable()`), see [14-audit-log.md](14-audit-log.md).

### Models

Model tests are ordinary PHPUnit tests: build an object, store it, read it back.

```php
use Laf\Exception\UniqueFieldDuplicateValueException;
use MyApp\Employee;
use PHPUnit\Framework\TestCase;

final class EmployeeModelTest extends TestCase
{
    public function test_e_mail_addresses_are_unique(): void
    {
        $first = new Employee();
        $first->setFieldValues(['first_name' => 'A', 'last_name' => 'B', 'email' => 'dup@test.example']);
        $first->store();

        $second = new Employee();
        $second->setFieldValues(['first_name' => 'C', 'last_name' => 'D', 'email' => 'dup@test.example']);
        try {
            $this->expectException(UniqueFieldDuplicateValueException::class);
            $second->store();
        } finally {
            $first->hardDelete();
        }
    }
}
```

### Forms without a browser

`Form::buildRequest($values, $drawMode)` produces exactly what the browser would post for the given column values:
the submission markers, the draw mode, the session's CSRF token and the signed field list, with the values under
their rot13 names. `setRequest()` makes the form read that array instead of `$_POST`. Together they run the real
`processForm()` path (CSRF check, conversion, validation, store) from a test, an import script or a JSON API.

This test ran against the example database (3 tests, 5 assertions, OK):

```php
<?php

use Laf\Database\Db;
use Laf\Exception\CsrfTokenException;
use Laf\Exception\FormValidationException;
use Laf\UI\Form\DrawMode;
use MyApp\Employee;
use PHPUnit\Framework\TestCase;

final class EmployeeFormTest extends TestCase
{
    private array $created = [];

    protected function setUp(): void
    {
        $_SESSION = [];   // the CSRF token and the field-list key live in the session
    }

    protected function tearDown(): void
    {
        if ($this->created !== []) {
            Db::getInstance()->delete('employee', ['id' => $this->created]);
        }
    }

    public function test_a_valid_submission_inserts_the_employee(): void
    {
        $form = (new Employee())->getForm(DrawMode::INSERT);
        $id = $form->setRequest($form->buildRequest([
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => 'grace@ch18.test',
            'salary' => '5100.00',
        ], DrawMode::INSERT))->processForm();
        $this->created[] = (int)$id;

        $saved = Employee::loadOrFail($id);
        $this->assertSame('Hopper', $saved->getLastNameVal());
        $this->assertSame('5100.00', (string)$saved->getSalaryVal());
    }

    public function test_a_missing_required_value_is_a_form_error(): void
    {
        $form = (new Employee())->getForm(DrawMode::INSERT);
        $form->setRequest($form->buildRequest(['first_name' => 'Nobody'], DrawMode::INSERT));
        try {
            $form->processForm();
            $this->fail('expected a FormValidationException');
        } catch (FormValidationException $ex) {
            $this->assertArrayHasKey('last_name', $ex->getErrors());
            $this->assertArrayHasKey('email', $ex->getErrors());
        }
    }

    public function test_a_request_without_the_csrf_token_is_refused(): void
    {
        $form = (new Employee())->getForm(DrawMode::INSERT);
        // third argument false: no CSRF token in the request
        $form->setRequest($form->buildRequest(['first_name' => 'X', 'last_name' => 'Y', 'email' => 'x@ch18.test'], DrawMode::INSERT, false));
        $this->expectException(CsrfTokenException::class);
        $form->processForm();
    }
}
```

```bash
vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/EmployeeFormTest.php
```

Things to know:

- In the CLI there is no real session; LAF works with the `$_SESSION` array, so resetting it in `setUp()` gives each
  test a fresh token and signing key. Tests that need `session_start()` or `header()` should run in a separate
  process (`@runTestsInSeparateProcesses`, as LAF's `FormTest` does).
- Many-to-many pickers take the linker table name and a list of keys:
  `buildRequest([..., 'employee_project' => [3, 7]])` ([08-relations.md](08-relations.md)).
- To test the HTML, draw the form (`$form->draw()`) and assert on fragments (`assertStringContainsString`), or load
  it into `DOMDocument`.
- `setRequest($data, $files)` takes a `$_FILES`-shaped second array for file inputs.

### Documents and redirects

Code that ends the request has a "return instead" switch for tests:

- `Document::handleRequest($action, false)` returns after sending; subclass `Document` and override the protected
  `sendHeader()` / `sendBody()` to capture the response (LAF's `DocumentTest` does exactly this).
- `UrlParser::redirectToViewPage($id, false)` / `redirectToListPage(false)` return instead of exiting
  ([12-routing-and-generated-pages.md](12-routing-and-generated-pages.md)).
- `UrlParser::reset()` re-reads the request after you change `$_GET` / `$_SERVER` in a test.
- `PhpGrid::bootstrap(false)` sends the grid's response and returns instead of exiting ([10-grids.md](10-grids.md)).
- `Document::upload($field, $file)` and `uploadMany($field, $files)` take a `$_FILES`-shaped array instead of reading
  `$_FILES` ([15-files-and-documents.md](15-files-and-documents.md)).

## The LafShell fixtures

LAF's unit tests need models without a database. `tests/Fixtures/LafShell/` (namespace `LafShell`, autoloaded
through `autoload-dev` in `composer.json`) holds them: `DummyTable`, `CodeTable`, `LinkTable`, `Document`,
`M2mPerson`, `M2mGroup` and `M2mPersonGroup`.

- The `Base/` classes are real `ClassGenerator` output for the integration tables. After a generator change, run
  `tests/Fixtures/regenerate.php` against a database that has those tables (the integration suite creates them);
  it writes to `tests/Fixtures/generated/<engine>/` so you can diff before copying anything over:

  ```bash
  LAF_DB_ENGINE=mysql LAF_DB_NAME=laf_test LAF_DB_USER=root LAF_DB_PASS= php tests/Fixtures/regenerate.php
  ```

- The leaf classes are kept by hand.
- `link_table` and `m2m_person_group` are linker tables, so `DummyTable` / `CodeTable` and `M2mPerson` / `M2mGroup`
  carry many-to-many definitions. Their forms draw pickers that read the database; in a unit test give the picker
  static options or exclude it from the layout.

## PHPStan

```bash
vendor/bin/phpstan analyse
```

`phpstan.neon`: level 5 on `src/`, `phpVersion: 80000` (the Composer floor), only the deprecated
`src/Laf/UI/Grid/SimpleTable.php` excluded, `treatPhpDocTypesAsCertain: false`, cache in `var/phpstan`. The rule is
**zero errors**; a change that adds one is fixed, not baselined. Your application can run PHPStan on its own code
with LAF in `vendor/` the usual way.

## Rector

```bash
vendor/bin/rector process --dry-run
```

`rector.php` enables the PHP 8.0 set plus the dead-code and code-quality sets on `src/` and `tests/` (fixtures and
`SimpleTable` skipped, cache in `var/rector`). It is **advisory**: the suggestions are read and applied by hand, file
by file, never with a bulk `rector process`. `declare(strict_types=1)` is only added to new files, because existing
files keep coercive typing for the applications' callers.

## Continuous integration

`.github/workflows/ci.yml` runs on every push and pull request:

| Job | PHP | What |
|---|---|---|
| Unit Tests | 8.1, 8.2, 8.3, 8.4, 8.5 | `php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Unit` |
| PHPStan (level 5) | 8.5 | `vendor/bin/phpstan analyse --no-progress` |
| Integration (MariaDB) | 8.1, 8.5 | MariaDB 10.11 service, `--testsuite Integration` |
| Integration (PostgreSQL) | 8.1, 8.5 | PostgreSQL 16 service, `--testsuite Integration` |

The PHPStan and integration jobs start after the unit tests pass. The service containers use `root` / `root` and
the database `laf_test`, passed in through the `LAF_DB_*` variables.

## See also

- [06-forms.md](06-forms.md): `setRequest()`, `buildRequest()`, `processForm()`
- [13-database.md](13-database.md): `Db::setInstance()` for a test connection
- [14-audit-log.md](14-audit-log.md): switching the audit log off during setup
- [02-code-generation.md](02-code-generation.md): the generator the fixtures come from
- [17-security.md](17-security.md): what the CSRF and field-list tests protect
