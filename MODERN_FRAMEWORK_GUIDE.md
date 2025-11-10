# LAF Framework - Modern PHP 8.4 Implementation

## Overview

This is a complete modernization of the LAF framework using PHP 8.4 features, dependency injection, and modern architectural patterns. The framework eliminates tight coupling, removes singletons, and implements best practices for maintainable, testable code.

## Key Improvements

### 1. Dependency Injection First

**Before (Old Framework):**
```php
$db = Db::getInstance(); // Singleton pattern
$result = $db->query($sql);
```

**After (Modern Framework):**
```php
class UserService {
    public function __construct(
        private readonly ConnectionInterface $connection
    ) {}
    
    public function getUsers(): array {
        return $this->connection->fetchAll('SELECT * FROM users');
    }
}

// Usage with DI container
$container = new Container();
$container->singleton(ConnectionInterface::class, Connection::class);
$service = $container->make(UserService::class); // Auto-wired!
```

### 2. Modern PHP 8.4 Features

#### Property Hooks
```php
class User {
    public string $email {
        set {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid email');
            }
            $this->email = $value;
        }
    }
}
```

#### Attributes for Metadata
```php
#[Table(name: 'users')]
#[PrimaryKey('id')]
class User {
    #[Column(name: 'email', type: 'varchar', length: 255)]
    #[Unique]
    public string $email;
    
    #[ForeignKey(table: 'roles', column: 'id')]
    public int $roleId;
}
```

#### Enums
```php
enum DatabaseDriver: string {
    case MYSQL = 'mysql';
    case POSTGRESQL = 'pgsql';
    
    public function getDefaultPort(): int {
        return match($this) {
            self::MYSQL => 3306,
            self::POSTGRESQL => 5432,
        };
    }
}
```

### 3. Repository Pattern

**Before (Old Framework):**
```php
$user = new UserController();
$user->select(1);
$user->setNameVal("John");
$user->insert();
```

**After (Modern Framework):**
```php
class UserRepository extends AbstractRepository {
    public function findById(int $id): ?User {
        return $this->findOne(['id' => $id]);
    }
    
    public function save(User $user): User {
        if ($user->id === null) {
            return $this->insert($user);
        }
        return $this->update($user);
    }
}

// Usage
$user = new User(
    name: "John",
    email: "john@example.com"
);
$repository->save($user);
```

### 4. Schema Inspection with Relationship Detection

```php
$inspector = new MySQLSchemaInspector($connection, $cache);
$inspector->setRelationshipDetector(new RelationshipDetector());

$tableMetadata = $inspector->inspectTable('users');

// Access metadata
echo $tableMetadata->name; // 'users'
echo $tableMetadata->getClassName(); // 'User'

// Columns
foreach ($tableMetadata->columns as $column) {
    echo "{$column->name}: {$column->getPhpType()}\n";
}

// Relationships
foreach ($tableMetadata->relationships as $rel) {
    echo "{$rel->type->value}: {$rel->foreignTable}\n";
}
```

## Architecture

### Core Layer

```
Core/
├── Container/          # Dependency Injection
│   ├── ContainerInterface.php
│   ├── Container.php
│   └── ServiceProviderInterface.php
├── Database/          # Database Abstraction
│   ├── ConnectionInterface.php
│   ├── Connection.php
│   ├── DatabaseConfig.php
│   ├── DatabaseDriver.php (enum)
│   └── ConnectionStats.php
```

### Schema Layer

```
Schema/
├── Inspector/         # Schema Inspection
│   ├── SchemaInspectorInterface.php
│   ├── AbstractSchemaInspector.php
│   ├── MySQLSchemaInspector.php
│   └── FileSchemaCache.php
├── Metadata/         # Metadata Objects
│   ├── TableMetadata.php
│   ├── ColumnMetadata.php
│   ├── IndexMetadata.php
│   ├── ForeignKeyMetadata.php
│   └── RelationshipMetadata.php
└── Relationship/     # Relationship Detection
    ├── RelationshipType.php (enum)
    └── RelationshipDetector.php
```

### Generator Layer

```
Generator/
├── ModelGenerator.php
└── RepositoryGenerator.php
```

### Repository Layer

```
Repository/
└── AbstractRepository.php
```

### Model Layer

```
Model/
└── Attributes/       # PHP 8 Attributes
    ├── Table.php
    ├── Column.php
    ├── PrimaryKey.php
    ├── ForeignKey.php
    └── Unique.php
```

## Getting Started

### 1. Installation

```bash
composer require lavdiu/laf
```

### 2. Configuration

Create a configuration file:

```php
// config/database.php
return [
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'secret',
    'charset' => 'utf8mb4',
];
```

### 3. Setup Container

```php
// bootstrap/app.php
use Laf\Core\Container\Container;
use Laf\Core\Database\Connection;
use Laf\Core\Database\ConnectionInterface;
use Laf\Core\Database\DatabaseConfig;

$container = new Container();

// Register database connection
$container->singleton(ConnectionInterface::class, function () {
    $config = DatabaseConfig::fromArray(require __DIR__ . '/../config/database.php');
    return new Connection($config);
});

return $container;
```

### 4. Generate Models and Repositories

```php
// generate.php
require __DIR__ . '/bootstrap/app.php';

use Laf\Schema\Inspector\MySQLSchemaInspector;
use Laf\Schema\Inspector\FileSchemaCache;
use Laf\Schema\Relationship\RelationshipDetector;
use Laf\Generator\ModelGenerator;
use Laf\Generator\RepositoryGenerator;

$connection = $container->get(ConnectionInterface::class);
$cache = new FileSchemaCache(__DIR__ . '/cache/schema');

$inspector = new MySQLSchemaInspector($connection, $cache);
$inspector->setRelationshipDetector(new RelationshipDetector());

$modelGenerator = new ModelGenerator('App\\Models');
$repositoryGenerator = new RepositoryGenerator('App\\Repositories');

foreach ($inspector->getTables() as $tableName) {
    $metadata = $inspector->inspectTable($tableName);
    
    // Generate model
    $modelCode = $modelGenerator->generate($metadata);
    file_put_contents(
        __DIR__ . "/app/Models/{$metadata->getClassName()}.php",
        $modelCode
    );
    
    // Generate repository
    $repoCode = $repositoryGenerator->generate($metadata);
    file_put_contents(
        __DIR__ . "/app/Repositories/{$metadata->getClassName()}Repository.php",
        $repoCode
    );
}
```

### 5. Use Generated Code

```php
// Example: User CRUD operations

$container = require __DIR__ . '/bootstrap/app.php';

// Register repository
$container->singleton(UserRepository::class, function ($c) {
    return new UserRepository($c->get(ConnectionInterface::class));
});

$userRepo = $container->get(UserRepository::class);

// Create
$user = new User(
    name: 'John Doe',
    email: 'john@example.com',
    password: password_hash('secret', PASSWORD_DEFAULT),
    createdAt: new DateTimeImmutable(),
    updatedAt: new DateTimeImmutable(),
);
$userRepo->save($user);

// Read
$user = $userRepo->findById(1);
$user = $userRepo->findByEmail('john@example.com'); // Generated method!

// Update
$user->name = 'Jane Doe';
$userRepo->save($user);

// Delete
$userRepo->delete($user->id);

// List all
$users = $userRepo->findAll();
```

## Generated Code Examples

### Generated Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeInterface;
use DateTimeImmutable;

#[\Laf\Model\Attributes\Table(name: 'users')]
#[\Laf\Model\Attributes\PrimaryKey('id')]
class User
{
    #[\Laf\Model\Attributes\Column(name: 'id', type: 'int', nullable: false)]
    public ?int $id;

    #[\Laf\Model\Attributes\Column(name: 'name', type: 'varchar', length: 255, nullable: false)]
    public string $name {
        set {
            if (strlen($value) > 255) {
                throw new \InvalidArgumentException('name exceeds maximum length of 255');
            }
            $this->name = $value;
        }
    };

    #[\Laf\Model\Attributes\Column(name: 'email', type: 'varchar', length: 255, nullable: false)]
    #[\Laf\Model\Attributes\Unique]
    public string $email {
        set {
            if (strlen($value) > 255) {
                throw new \InvalidArgumentException('email exceeds maximum length of 255');
            }
            $this->email = $value;
        }
    };

    #[\Laf\Model\Attributes\Column(name: 'role_id', type: 'int', nullable: false)]
    #[\Laf\Model\Attributes\ForeignKey(table: 'roles', column: 'id')]
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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role_id' => $this->roleId,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            email: $data['email'] ?? '',
            roleId: $data['role_id'] ?? 0,
        );
    }

    /**
     * Get related Role (many-to-one)
     *
     * @return Role|null
     */
    public function role(): ?Role
    {
        // TODO: Implement relationship loading via repository
        throw new \RuntimeException('Relationship loading not yet implemented');
    }
}
```

### Generated Repository

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use Laf\Repository\AbstractRepository;
use Laf\Core\Database\ConnectionInterface;

class UserRepository extends AbstractRepository
{
    public function __construct(ConnectionInterface $connection)
    {
        parent::__construct($connection, User::class, 'users');
    }

    public function findById(int $id): ?User
    {
        return $this->findOne(['id' => $id]);
    }

    public function findAll(): array
    {
        return $this->find();
    }

    public function save(User $model): User
    {
        if ($model->id === null) {
            return $this->insert($model);
        }
        return $this->update($model);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->tableName} WHERE id = ?";
        $affected = $this->connection->execute($sql, [$id]);
        return $affected > 0;
    }

    /**
     * Find User by email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->findOne(['email' => $email]);
    }

    /**
     * Find User records by roleId
     */
    public function findByRoleId(int $roleId): array
    {
        return $this->find(['role_id' => $roleId]);
    }
}
```

## Advanced Features

### Custom Queries

```php
class UserRepository extends AbstractRepository
{
    public function findActiveUsers(): array
    {
        return $this->query(
            "SELECT * FROM users WHERE status = ? AND deleted_at IS NULL",
            ['active']
        );
    }
    
    public function findUsersByRole(string $roleName): array
    {
        return $this->query(
            "SELECT u.* FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE r.name = ?",
            [$roleName]
        );
    }
}
```

### Transactions

```php
$userRepo = $container->get(UserRepository::class);

$userRepo->beginTransaction();

try {
    $user = new User(name: 'John', email: 'john@example.com');
    $userRepo->save($user);
    
    // Other operations...
    
    $userRepo->commit();
} catch (Exception $e) {
    $userRepo->rollback();
    throw $e;
}
```

### Connection Statistics

```php
$connection = $container->get(ConnectionInterface::class);
$stats = $connection->getStats();

echo "Queries executed: {$stats->queriesExecuted}\n";
echo "Average query time: {$stats->getAverageQueryTime()}ms\n";
echo "Queries per second: {$stats->getQueriesPerSecond()}\n";
```

### Schema Caching

```php
$cache = new FileSchemaCache(__DIR__ . '/cache/schema', ttl: 3600);

// Cache is automatic
$metadata = $inspector->inspectTable('users'); // Cached for 1 hour

// Clear cache
$cache->clearTable('users');
$cache->clear(); // Clear all
```

## Migration from Old Framework

### Step 1: Update Dependencies

```json
{
    "require": {
        "php": ">=8.4",
        "lavdiu/laf": "^2.0"
    }
}
```

### Step 2: Replace Singletons

**Old:**
```php
$db = Db::getInstance();
```

**New:**
```php
$connection = $container->get(ConnectionInterface::class);
```

### Step 3: Replace BaseObject with Models

**Old:**
```php
$user = new UserController();
$user->select(1);
$user->setNameVal("John");
$user->insert();
```

**New:**
```php
$user = $userRepo->findById(1);
$user->name = "John";
$userRepo->save($user);
```

### Step 4: Regenerate Code

Run the generator to create new models and repositories for all tables.

## Testing

```php
use PHPUnit\Framework\TestCase;

class UserRepositoryTest extends TestCase
{
    private Container $container;
    private UserRepository $repository;
    
    protected function setUp(): void
    {
        $this->container = new Container();
        
        // Use test database
        $this->container->singleton(ConnectionInterface::class, function () {
            $config = DatabaseConfig::fromArray([
                'driver' => 'sqlite',
                'database' => ':memory:',
            ]);
            return new Connection($config);
        });
        
        $this->repository = $this->container->make(UserRepository::class);
    }
    
    public function testCreateUser(): void
    {
        $user = new User(name: 'Test', email: 'test@example.com');
        $saved = $this->repository->save($user);
        
        $this->assertNotNull($saved->id);
        $this->assertEquals('Test', $saved->name);
    }
}
```

## Performance Considerations

1. **Schema Caching**: Always enable schema caching in production
2. **Connection Pooling**: Use persistent connections for high-traffic apps
3. **Lazy Loading**: Relationships are lazy-loaded by default
4. **Query Optimization**: Use indexes and analyze slow queries

## Best Practices

1. **Always use dependency injection** - Never use `new` for services
2. **Type everything** - Use strict types and return type hints
3. **Immutable where possible** - Use readonly properties
4. **Validate in models** - Use property hooks for validation
5. **Repository per entity** - One repository per model
6. **Test with interfaces** - Mock ConnectionInterface for testing

## Troubleshooting

### "Class not found" errors
- Run `composer dump-autoload`
- Check namespace declarations

### "Connection failed" errors
- Verify database credentials
- Check firewall/network settings
- Ensure PDO extension is installed

### "Table not found" errors
- Regenerate models after schema changes
- Clear schema cache

## Contributing

Contributions are welcome! Please follow PSR-12 coding standards and include tests.

## License

MIT License - See LICENSE file for details
