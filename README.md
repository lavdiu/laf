# LAF Framework

Modern PHP 8.4 Framework for Rapid Application Development

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

## Overview

LAF is a modern, fully-typed PHP 8.4 framework that automatically generates models and repositories from your database schema. It features dependency injection, schema inspection, relationship detection, and code generation with the latest PHP features.

## Key Features

- 🚀 **Automatic Code Generation** - Generate type-safe models and repositories from database schema
- 💉 **Dependency Injection** - PSR-11 compliant container with auto-wiring
- 🔍 **Schema Inspection** - Automatic detection of tables, columns, indexes, and relationships
- 🎯 **Type Safety** - Full PHP 8.4 type coverage with property hooks
- 🏗️ **Modern Architecture** - SOLID principles, design patterns, no singletons
- 📊 **Relationship Detection** - Automatic detection of 1:1, 1:M, M:M relationships
- 🎨 **Property Hooks** - Built-in validation using PHP 8.4 property hooks
- 🏷️ **Attributes** - Metadata using PHP 8 attributes
- 🔄 **Repository Pattern** - Clean data access layer
- 📦 **Multiple Databases** - MySQL, PostgreSQL support

## Quick Start

### Installation

```bash
composer require lavdiu/laf
```

### Generate Code

```php
<?php
require 'vendor/autoload.php';

use Laf\Core\Container\Container;
use Laf\Core\Database\Connection;
use Laf\Core\Database\DatabaseConfig;
use Laf\Core\Database\DatabaseDriver;
use Laf\Schema\Inspector\MySQLSchemaInspector;
use Laf\Generator\ModelGenerator;
use Laf\Generator\RepositoryGenerator;

// Setup
$container = new Container();
$config = new DatabaseConfig(
    driver: DatabaseDriver::MYSQL,
    host: 'localhost',
    database: 'mydb',
    username: 'user',
    password: 'pass'
);
$connection = new Connection($config);

// Inspect and Generate
$inspector = new MySQLSchemaInspector($connection);
$modelGen = new ModelGenerator('App\\Models');
$repoGen = new RepositoryGenerator('App\\Repositories');

foreach ($inspector->getTables() as $table) {
    $metadata = $inspector->inspectTable($table);
    
    // Generate model and repository
    file_put_contents("Models/{$metadata->getClassName()}.php", 
        $modelGen->generate($metadata));
    file_put_contents("Repositories/{$metadata->getClassName()}Repository.php",
        $repoGen->generate($metadata));
}
```

### Use Generated Code

```php
<?php
// Create
$user = new User(
    name: 'John Doe',
    email: 'john@example.com'
);
$userRepo->save($user);

// Read
$user = $userRepo->findById(1);
$user = $userRepo->findByEmail('john@example.com'); // Auto-generated!

// Update
$user->name = 'Jane Doe';
$userRepo->save($user);

// Delete
$userRepo->delete($user->id);

// List
$users = $userRepo->findAll();
```

## Generated Code Example

### Model with PHP 8.4 Features

```php
#[Table(name: 'users')]
#[PrimaryKey('id')]
class User
{
    #[Column(name: 'email', type: 'varchar', length: 255)]
    #[Unique]
    public string $email {
        set {
            if (strlen($value) > 255) {
                throw new \InvalidArgumentException('Email too long');
            }
            $this->email = $value;
        }
    };
    
    #[ForeignKey(table: 'roles', column: 'id')]
    public int $roleId;
    
    public function __construct(
        string $name,
        string $email,
        int $roleId,
    ) {
        $this->name = $name;
        $this->email = $email;
        $this->roleId = $roleId;
    }
    
    public function role(): ?Role { /* ... */ }
}
```

### Repository with Custom Finders

```php
class UserRepository extends AbstractRepository
{
    public function findById(int $id): ?User { /* ... */ }
    public function save(User $model): User { /* ... */ }
    public function delete(int $id): bool { /* ... */ }
    
    // Auto-generated for unique columns
    public function findByEmail(string $email): ?User { /* ... */ }
    
    // Auto-generated for foreign keys
    public function findByRoleId(int $roleId): array { /* ... */ }
}
```

## What Makes LAF Different

### Before (Old Frameworks)
```php
// Singletons and magic methods
$db = Db::getInstance();
$user = new UserController();
$user->select(1);
$user->setNameVal("John"); // Magic method
$user->insert();
```

### After (LAF Framework)
```php
// Dependency injection and type safety
class UserService {
    public function __construct(
        private readonly UserRepository $userRepo
    ) {}
    
    public function updateUser(int $id, string $name): User {
        $user = $this->userRepo->findById($id);
        $user->name = $name; // Type-safe property
        return $this->userRepo->save($user);
    }
}
```

## PHP 8.4 Features

- ✅ **Property Hooks** - Validation on property assignment
- ✅ **Readonly Classes** - Immutable value objects
- ✅ **Enums** - Type-safe constants with methods
- ✅ **Attributes** - Metadata for code generation
- ✅ **Constructor Property Promotion** - Concise constructors
- ✅ **Named Arguments** - Clear object creation
- ✅ **Union Types** - Flexible type hints
- ✅ **Match Expressions** - Clean conditionals

## Architecture

```
LAF Framework
├── Core Layer          # DI Container, Database Connection
├── Schema Layer        # Schema Inspection, Metadata
├── Generator Layer     # Code Generation
├── Repository Layer    # Data Access Pattern
└── Model Layer         # Attributes & Base Classes
```

## Documentation

- 📖 [Quick Start Guide](QUICKSTART.md) - Get started in 5 minutes
- 📚 [Complete Guide](MODERN_FRAMEWORK_GUIDE.md) - Full documentation
- 🗺️ [Architecture](MODERNIZATION_ROADMAP.md) - Design decisions
- 📊 [Summary](MODERNIZATION_SUMMARY.md) - All changes and improvements
- 💡 [Examples](examples/ModernUsageExample.php) - Working examples

## Requirements

- PHP 8.4 or higher
- PDO extension
- MySQL or PostgreSQL database

## Features in Detail

### Dependency Injection
```php
$container = new Container();
$container->singleton(ConnectionInterface::class, Connection::class);
$service = $container->make(UserService::class); // Auto-wired!
```

### Schema Inspection
```php
$inspector = new MySQLSchemaInspector($connection);
$metadata = $inspector->inspectTable('users');

// Access metadata
echo $metadata->name;                    // 'users'
echo $metadata->getClassName();          // 'User'
$metadata->columns;                      // All columns
$metadata->relationships;                // Detected relationships
$metadata->hasTimestamps();              // true/false
```

### Relationship Detection
```php
// Automatically detects:
// - One-to-One (unique foreign key)
// - One-to-Many (foreign key)
// - Many-to-One (reverse foreign key)
// - Many-to-Many (through pivot tables)

foreach ($metadata->relationships as $rel) {
    echo "{$rel->type->value}: {$rel->foreignTable}\n";
}
```

### Property Validation
```php
class User {
    public string $email {
        set {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid email');
            }
            $this->email = $value;
        }
    };
}

$user->email = 'invalid'; // Throws InvalidArgumentException
```

### Transactions
```php
$repo->beginTransaction();
try {
    $repo->save($user);
    // ... other operations
    $repo->commit();
} catch (Exception $e) {
    $repo->rollback();
    throw $e;
}
```

## Testing

```php
class UserServiceTest extends TestCase
{
    public function testCreateUser(): void
    {
        $mockConnection = $this->createMock(ConnectionInterface::class);
        $repo = new UserRepository($mockConnection);
        
        // Easy to test with DI!
    }
}
```

## Performance

- **Schema Caching** - Avoid repeated schema queries
- **Connection Statistics** - Track query performance
- **Type Safety** - Better opcache optimization
- **Lazy Loading** - Load relationships on demand

## Contributing

Contributions welcome! Please follow PSR-12 coding standards.

## License

MIT License - See [LICENSE](LICENSE) file for details.

## Credits

Created by Lulzim Avdiu

## Support

- 📧 Email: l@orav.net
- 📝 Documentation: See docs folder
- 🐛 Issues: GitHub Issues
