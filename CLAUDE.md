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
- Query building: `find()`, `findOne()`, `bOfind()`, `bOfindOne()`, `getQueryBuilder()`
- Automatic audit logging of all data changes
- Automatic timestamp management (`created_on`, `updated_on`, etc.)
- Foreign key validation and object navigation
- Change tracking via `hasChanged()` on fields

**Key Classes**:
- `Table`: Metadata representation of database tables
- `Field`: Column definitions with validation rules and type information
- `FieldType`: Strong typing system (TypeInteger, TypeVarchar, TypeDate, TypeBool, etc.)
- `ForeignKey`: Manages relationships between tables
- `QueryBuilder`: Fluent interface for complex queries
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

**Complex Queries**:
```php
$results = Customer::getQueryBuilder()
    ->where('city', '=', 'New York')
    ->orWhere('city', '=', 'Boston')
    ->orderBy('name', 'ASC')
    ->with('address') // Eager load relationships
    ->limit(10)
    ->get();
```

**Form Handling**:
```php
$customer = new Customer(UrlParser::getId());
$form = $customer->getForm();

if ($form->isSubmitted()) {
    $id = $form->processForm(); // Automatically saves data
    UrlParser::redirectToViewPage($id);
}

$form->draw(DrawMode::UPDATE);
```

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
4. **Builder**: `QueryBuilder` for SQL construction
5. **Template Method**: `BaseObject` defines CRUD skeleton, subclasses customize
6. **Strategy**: `FieldType` classes for different data types

## Configuration via Settings Class

The `Settings` singleton stores all configuration. Common keys:

- `database.*`: Connection parameters (hostname, database_name, username, password, port, engine)
- `project.package_name`: Root namespace for generated classes
- `settings.use_pretty_url`: Enable/disable pretty URLs (default: true)
- `settings.label.translations`: UI label translations

## Audit Logging

LAF includes automatic audit logging for all data changes:

1. Set active user once: `BaseObject::setActiveUserId($userId)`
2. All INSERT/UPDATE/DELETE operations are automatically logged
3. Only changed fields are recorded for updates
4. Changes stored as JSON with old/new values
5. Disable per-object: `$object->auditLogDisable()`

Requires `audit_log` table (see `migrations/` or `examples/audit_logging_example.php`).

## Database Support

- **MySQL/MariaDB**: Primary support
- **PostgreSQL**: Full support via `PostgresTableInspector`

Connection managed through `Db` singleton using PDO with UTF-8 charset.

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
