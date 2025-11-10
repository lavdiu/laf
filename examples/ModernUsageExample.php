<?php

declare(strict_types=1);

/**
 * Modern LAF Framework Usage Example
 * 
 * This example demonstrates the modernized PHP 8.4 framework with:
 * - Dependency Injection
 * - Schema Inspection
 * - Code Generation
 * - Repository Pattern
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laf\Core\Container\Container;
use Laf\Core\Database\Connection;
use Laf\Core\Database\ConnectionInterface;
use Laf\Core\Database\DatabaseConfig;
use Laf\Core\Database\DatabaseDriver;
use Laf\Schema\Inspector\MySQLSchemaInspector;
use Laf\Schema\Inspector\SchemaInspectorInterface;
use Laf\Schema\Inspector\FileSchemaCache;
use Laf\Schema\Relationship\RelationshipDetector;
use Laf\Generator\ModelGenerator;
use Laf\Generator\RepositoryGenerator;

// ============================================================================
// 1. SETUP DEPENDENCY INJECTION CONTAINER
// ============================================================================

$container = new Container();

// Register database configuration
$container->instance(DatabaseConfig::class, DatabaseConfig::fromArray([
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'your_database',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
]));

// Register database connection
$container->singleton(ConnectionInterface::class, function ($container) {
    $config = $container->get(DatabaseConfig::class);
    return new Connection($config);
});

// Register schema cache
$container->singleton(FileSchemaCache::class, function () {
    return new FileSchemaCache(__DIR__ . '/../cache/schema');
});

// Register schema inspector
$container->singleton(SchemaInspectorInterface::class, function ($container) {
    $connection = $container->get(ConnectionInterface::class);
    $cache = $container->get(FileSchemaCache::class);
    
    $inspector = new MySQLSchemaInspector($connection, $cache);
    $inspector->setRelationshipDetector(new RelationshipDetector());
    
    return $inspector;
});

// ============================================================================
// 2. INSPECT DATABASE SCHEMA
// ============================================================================

echo "=== Database Schema Inspection ===\n\n";

/** @var SchemaInspectorInterface $inspector */
$inspector = $container->get(SchemaInspectorInterface::class);

// Get all tables
$tables = $inspector->getTables();
echo "Found " . count($tables) . " tables:\n";
foreach ($tables as $table) {
    echo "  - {$table}\n";
}
echo "\n";

// Inspect a specific table (example: 'users')
if (in_array('users', $tables)) {
    echo "=== Inspecting 'users' table ===\n\n";
    
    $userTable = $inspector->inspectTable('users');
    
    echo "Table: {$userTable->name}\n";
    echo "Class: {$userTable->getClassName()}\n";
    echo "Columns: " . count($userTable->columns) . "\n";
    echo "Indexes: " . count($userTable->indexes) . "\n";
    echo "Foreign Keys: " . count($userTable->foreignKeys) . "\n";
    echo "Relationships: " . count($userTable->relationships) . "\n";
    echo "Has Timestamps: " . ($userTable->hasTimestamps() ? 'Yes' : 'No') . "\n";
    echo "Has Soft Delete: " . ($userTable->hasSoftDelete() ? 'Yes' : 'No') . "\n";
    echo "\n";
    
    // Show columns
    echo "Columns:\n";
    foreach ($userTable->columns as $column) {
        $nullable = $column->nullable ? 'NULL' : 'NOT NULL';
        $ai = $column->autoIncrement ? ' AUTO_INCREMENT' : '';
        echo "  - {$column->name} ({$column->type}) {$nullable}{$ai}\n";
        echo "    PHP Type: {$column->getPhpType()}\n";
        echo "    Property: {$column->getPropertyName()}\n";
    }
    echo "\n";
    
    // Show relationships
    if (!empty($userTable->relationships)) {
        echo "Relationships:\n";
        foreach ($userTable->relationships as $rel) {
            echo "  - {$rel->type->value}: {$rel->foreignTable}\n";
            echo "    Method: {$rel->getMethodName()}()\n";
        }
        echo "\n";
    }
}

// ============================================================================
// 3. GENERATE CODE
// ============================================================================

echo "=== Code Generation ===\n\n";

$modelGenerator = new ModelGenerator(
    namespace: 'App\\Models',
    usePropertyHooks: true,
    useAttributes: true
);

$repositoryGenerator = new RepositoryGenerator(
    namespace: 'App\\Repositories',
    modelNamespace: 'App\\Models'
);

// Generate for 'users' table
if (in_array('users', $tables)) {
    $userTable = $inspector->inspectTable('users');
    
    // Generate model
    $modelCode = $modelGenerator->generate($userTable);
    $modelFile = __DIR__ . '/../generated/Models/User.php';
    
    if (!is_dir(dirname($modelFile))) {
        mkdir(dirname($modelFile), 0755, true);
    }
    
    file_put_contents($modelFile, $modelCode);
    echo "Generated Model: {$modelFile}\n";
    
    // Generate repository
    $repositoryCode = $repositoryGenerator->generate($userTable);
    $repositoryFile = __DIR__ . '/../generated/Repositories/UserRepository.php';
    
    if (!is_dir(dirname($repositoryFile))) {
        mkdir(dirname($repositoryFile), 0755, true);
    }
    
    file_put_contents($repositoryFile, $repositoryCode);
    echo "Generated Repository: {$repositoryFile}\n";
    echo "\n";
}

// ============================================================================
// 4. USAGE EXAMPLE (after code generation)
// ============================================================================

echo "=== Usage Example ===\n\n";

echo <<<'EXAMPLE'
After generating the code, you can use it like this:

```php
// 1. Setup (in your bootstrap/app.php)
$container = new Container();

// Register database connection
$container->singleton(ConnectionInterface::class, function () {
    $config = DatabaseConfig::fromArray([
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'mydb',
        'username' => 'user',
        'password' => 'pass',
    ]);
    return new Connection($config);
});

// Register repositories
$container->singleton(UserRepository::class, function ($container) {
    return new UserRepository($container->get(ConnectionInterface::class));
});

// 2. Use in your application
$userRepo = $container->get(UserRepository::class);

// Create a new user
$user = new User(
    name: 'John Doe',
    email: 'john@example.com',
    password: password_hash('secret', PASSWORD_DEFAULT),
    createdAt: new DateTimeImmutable(),
    updatedAt: new DateTimeImmutable(),
);

$savedUser = $userRepo->save($user);
echo "Created user with ID: {$savedUser->id}\n";

// Find user by ID
$foundUser = $userRepo->findById(1);
if ($foundUser) {
    echo "Found: {$foundUser->name} ({$foundUser->email})\n";
}

// Find user by email (generated method for unique column)
$userByEmail = $userRepo->findByEmail('john@example.com');

// Update user
$foundUser->name = 'Jane Doe';
$userRepo->save($foundUser);

// Get all users
$allUsers = $userRepo->findAll();
echo "Total users: " . count($allUsers) . "\n";

// Delete user
$userRepo->delete($foundUser->id);

// Use relationships (if defined)
$posts = $foundUser->posts(); // One-to-many relationship
$role = $foundUser->role(); // Many-to-one relationship
```

EXAMPLE;

echo "\n";

// ============================================================================
// 5. ADVANCED FEATURES
// ============================================================================

echo "=== Advanced Features ===\n\n";

echo <<<'ADVANCED'
The modernized framework includes:

1. **Dependency Injection**
   - PSR-11 compliant container
   - Auto-wiring support
   - Service providers

2. **Database Layer**
   - PDO injection (no singletons!)
   - Connection pooling ready
   - Event dispatching for logging
   - Statistics tracking

3. **Schema Inspection**
   - MySQL and PostgreSQL support
   - Relationship detection (1:1, 1:M, M:M)
   - Pivot table detection
   - Schema caching

4. **Code Generation**
   - PHP 8.4 property hooks
   - Attributes for metadata
   - Type-safe models
   - Repository pattern

5. **Modern PHP 8.4 Features**
   - Readonly properties
   - Property hooks with validation
   - Enums for constants
   - Constructor property promotion
   - Named arguments

6. **Best Practices**
   - SOLID principles
   - Interface-based programming
   - Immutable value objects
   - Separation of concerns

ADVANCED;

echo "\n";

// ============================================================================
// 6. GENERATE ALL TABLES
// ============================================================================

echo "=== Generating Code for All Tables ===\n\n";

foreach ($tables as $tableName) {
    try {
        $tableMetadata = $inspector->inspectTable($tableName);
        $className = $tableMetadata->getClassName();
        
        // Generate model
        $modelCode = $modelGenerator->generate($tableMetadata);
        $modelFile = __DIR__ . "/../generated/Models/{$className}.php";
        
        if (!is_dir(dirname($modelFile))) {
            mkdir(dirname($modelFile), 0755, true);
        }
        
        file_put_contents($modelFile, $modelCode);
        
        // Generate repository
        $repositoryCode = $repositoryGenerator->generate($tableMetadata);
        $repositoryFile = __DIR__ . "/../generated/Repositories/{$className}Repository.php";
        
        if (!is_dir(dirname($repositoryFile))) {
            mkdir(dirname($repositoryFile), 0755, true);
        }
        
        file_put_contents($repositoryFile, $repositoryCode);
        
        echo "✓ Generated {$className} (Model + Repository)\n";
    } catch (Exception $e) {
        echo "✗ Failed to generate {$tableName}: {$e->getMessage()}\n";
    }
}

echo "\n";
echo "=== Generation Complete ===\n";
echo "Check the 'generated' directory for all generated files.\n";
