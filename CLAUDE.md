# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

LAF (Lulzim Application Framework) is a PHP 8+ rapid application development framework using a **Database-First Active Record Pattern with Code Generation**. The framework automatically generates model classes and CRUD interfaces from database schemas, enabling rapid development of database-driven web applications.

## Development Commands

### Testing
```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test
./vendor/bin/phpunit tests/BaseObjectTest.php

# Tests require app/config/config.php for database configuration
```

### Code Quality
```bash
# Static analysis
./vendor/bin/phpstan analyze

# Code refactoring/modernization
./vendor/bin/rector process
```

### Code Generation
```bash
# Generate model classes from database schema
php app/util/generate_classes.php

# This creates/updates:
# - Base/Base[TableName].php (auto-generated, overwritten on regeneration)
# - [TableName].php (user customization layer, created once)
# - CRUD page files (.page extension)
```

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
- `Field`: Column definitions with validation rules and type information
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
- **Containers**: Div, TabContainer, GenericContainer for layout
- **Page Components**: GenericPage, AdminPage for full page rendering
- **Draw Modes**: `DrawMode::VIEW`, `DrawMode::INSERT`, `DrawMode::UPDATE`

All components support fluent interfaces and CSS/attribute management.

#### 3. Code Generators (`src/Laf/Generator/`)

- **ClassGenerator**: Generates Base and main class files from database schema
- **PageGenerator**: Creates complete CRUD pages with action-based routing
- **DatabaseGenerator**: Orchestrates generation for entire database
- **TableInspector** / **PostgresTableInspector**: Queries `INFORMATION_SCHEMA` for table metadata

#### 4. Routing System (`src/Laf/Util/UrlParser.php`)

URL structure: `/module/submodule/action/id`

- `module`: Main functional area
- `submodule`: Specific entity/table (maps to .page file)
- `action`: CRUD operation (`list`, `view`, `new`, `update`, `delete`)
- `id`: Record identifier

Generated pages use switch statements to route actions.

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
$form = $customer->getForm();

if ($form->isSubmitted()) {
    try {
        $id = $form->processForm(); // reads $_POST, validates, inserts or updates
        UrlParser::redirectToViewPage($id);
    } catch (\Laf\Exception\FormValidationException $ex) {
        $errors = $ex->getErrors(); // field => messages; the redrawn form shows them (is-invalid + invalid-feedback)
    }
}

$form->setDrawMode(DrawMode::UPDATE);
$page->addComponent($form);
```
Forms: every field element comes from `Field::getFormElement()` (`FormElementFactory::create()`); inputs render Bootstrap 5
markup and never change their own state when drawn; `processForm()` = `detectSubmission()` + CSRF (one token per session
and form, `CsrfTokenException`) + `applySubmittedValues()` + `validate()` (opt-out `setValidateOnSubmit(false)`); a
checkbox posts a hidden `0` so unchecked is distinguishable from "not on the form"; `Select`/`Radio`/`CheckboxCollection`
take `setOptions()` or the FK table; Date/DateTime/Time inputs show the locale format (`locale.*.format`) with a picker
class, or native inputs with `setNativeInput(true)` / Settings `form.native_date_inputs`.

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
  `database.init_commands` (SQL run after connecting), `database.empty_string_as_null` (default true). `port` and `engine`
  default to 3306/mysql; engine accepts mysql, mariadb, postgres, postgresql, pgsql. A second connection reads
  `database.<name>.*` and is reached with `Db::getInstance('<name>')`.
- `project.package_name`: Root namespace for generated classes
- `settings.use_pretty_url`: Enable/disable pretty URLs (default: true)
- `settings.label.translations`: UI label translations

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
the typed prepare+execute every helper uses; `$db->transaction(fn)` is nest-aware. Statement failures are the driver's
`PDOException` (recorded in `hasError()/getErrorMessage()` and written to the `SqlErrorLoggerInterface` table when one is
set); a failed connection is `DbConnectException` (a `PDOException`). `setLogger()` takes a PSR-3 logger (statements at
debug, failures at error); `enableQueryLog()` keeps every statement with its time.

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

See `1-example/` directory for a complete ERP application demonstrating:
- Full application bootstrap in `public/index.php`
- Code generation script in `app/util/generate_classes.php`
- Generated models with customizations
- CRUD pages with complex business logic
- Multi-module structure

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

- Tests use PHPUnit 9.5
- Require database connection via `app/config/config.php`
- `BaseObjectTest` creates temporary test tables
- Tests cover CRUD operations, field types, validation, relationships
