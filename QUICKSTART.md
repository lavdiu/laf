# LAF Framework - Quick Start Guide

Get up and running with the modernized LAF framework in 5 minutes.

## Prerequisites

- PHP 8.4 or higher
- Composer
- MySQL or PostgreSQL database

## Installation

```bash
composer require lavdiu/laf
```

## Step 1: Configure Database Connection

Create a bootstrap file:

```php
<?php
// bootstrap.php

require __DIR__ . '/vendor/autoload.php';

use Laf\Core\Container\Container;
use Laf\Core\Database\Connection;
use Laf\Core\Database\ConnectionInterface;
use Laf\Core\Database\DatabaseConfig;
use Laf\Core\Database\DatabaseDriver;

$container = new Container();

// Configure database
$container->singleton(ConnectionInterface::class, function () {
    $config = new DatabaseConfig(
        driver: DatabaseDriver::MYSQL,
        host: 'localhost',
        port: 3306,
        database: 'your_database',
        username: 'your_username',
        password: 'your_password',
        charset: 'utf8mb4'
    );
    
    return new Connection($config);
});

return $container;
```

## Step 2: Generate Models and Repositories

Create a generation script:

```php
<?php
// generate.php

$container = require __DIR__ . '/bootstrap.php';

use Laf\Core\Database\ConnectionInterface;
use Laf\Schema\Inspector\MySQLSchemaInspector;
use Laf\Schema\Inspector\FileSchemaCache;
use Laf\Schema\Relationship\RelationshipDetector;
use Laf\Generator\ModelGenerator;
use Laf\Generator\RepositoryGenerator;

// Get connection
$connection = $container->get(ConnectionInterface::class);

// Setup schema inspector
$cache = new FileSchemaCache(__DIR__ . '/cache/schema');
$inspector = new MySQLSchemaInspector($connection, $cache);
$inspector->setRelationshipDetector(new RelationshipDetector());

// Setup generators
$modelGenerator = new ModelGenerator(
    namespace: 'App\\Models',
    usePropertyHooks: true,
    useAttributes: true
);

$repositoryGenerator = new RepositoryGenerator(
    namespace: 'App\\Repositories',
    modelNamespace: 'App\\Models'
);

// Create directories
@mkdir(__DIR__ . '/app/Models', 0755, true);
@mkdir(__DIR__ . '/app/Repositories', 0755, true);

// Generate for all tables
foreach ($inspector->getTables() as $tableName) {
    echo "Generating {$tableName}...\n";
    
    $metadata = $inspector->inspectTable($tableName);
    $className = $metadata->getClassName();
    
    // Generate model
    $modelCode = $modelGenerator->generate($metadata);
    file_put_contents(
        __DIR__ . "/app/Models/{$className}.php",
        $modelCode
    );
    
    // Generate repository
    $repoCode = $repositoryGenerator->generate($metadata);
    file_put_contents(
        __DIR__ . "/app/Repositories/{$className}Repository.php",
        $repoCode
    );
    
    echo "✓ Generated {$className}\n";
}

echo "\nDone! Check the app/ directory.\n";
```

Run the generator:

```bash
php generate.php
```

## Step 3: Update composer.json

Add autoloading for your generated code:

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    }
}
```

Then run:

```bash
composer dump-autoload
```

## Step 4: Use Your Models

```php
<?php
// index.php

require __DIR__ . '/vendor/autoload.php';

$container = require __DIR__ . '/bootstrap.php';

use App\Models\User;
use App\Repositories\UserRepository;
use Laf\Core\Database\ConnectionInterface;

// Register repository
$container->singleton(UserRepository::class, function ($c) {
    return new UserRepository($c->get(ConnectionInterface::class));
});

// Get repository
$userRepo = $container->get(UserRepository::class);

// CREATE
$user = new User(
    name: 'John Doe',
    email: 'john@example.com',
    password: password_hash('secret', PASSWORD_DEFAULT),
    createdAt: new DateTimeImmutable(),
    updatedAt: new DateTimeImmutable()
);

$savedUser = $userRepo->save($user);
echo "Created user ID: {$savedUser->id}\n";

// READ
$foundUser = $userRepo->findById($savedUser->id);
echo "Found: {$foundUser->name}\n";

// Find by unique column (auto-generated method)
$userByEmail = $userRepo->findByEmail('john@example.com');
echo "Found by email: {$userByEmail->name}\n";

// UPDATE
$foundUser->name = 'Jane Doe';
$userRepo->save($foundUser);
echo "Updated user\n";

// LIST ALL
$allUsers = $userRepo->findAll();
echo "Total users: " . count($allUsers) . "\n";

// DELETE
$userRepo->delete($foundUser->id);
echo "Deleted user\n";
```

## Example: User CRUD Application

```php
<?php
// user_crud.php

$container = require __DIR__ . '/bootstrap.php';

use App\Models\User;
use App\Repositories\UserRepository;

$container->singleton(UserRepository::class, function ($c) {
    return new UserRepository($c->get(ConnectionInterface::class));
});

$userRepo = $container->get(UserRepository::class);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $user = new User(
                name: $_POST['name'],
                email: $_POST['email'],
                password: password_hash($_POST['password'], PASSWORD_DEFAULT),
                createdAt: new DateTimeImmutable(),
                updatedAt: new DateTimeImmutable()
            );
            $userRepo->save($user);
            header('Location: user_crud.php');
            exit;
            
        case 'update':
            $user = $userRepo->findById((int)$_POST['id']);
            if ($user) {
                $user->name = $_POST['name'];
                $user->email = $_POST['email'];
                $user->updatedAt = new DateTimeImmutable();
                $userRepo->save($user);
            }
            header('Location: user_crud.php');
            exit;
            
        case 'delete':
            $userRepo->delete((int)$_POST['id']);
            header('Location: user_crud.php');
            exit;
    }
}

// Get all users
$users = $userRepo->findAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Management</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        form { margin: 20px 0; padding: 20px; border: 1px solid #ddd; }
        input { margin: 5px 0; padding: 5px; }
    </style>
</head>
<body>
    <h1>User Management</h1>
    
    <!-- Create Form -->
    <form method="POST">
        <h2>Create User</h2>
        <input type="hidden" name="action" value="create">
        <input type="text" name="name" placeholder="Name" required><br>
        <input type="email" name="email" placeholder="Email" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <button type="submit">Create User</button>
    </form>
    
    <!-- User List -->
    <h2>Users</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($users as $user): ?>
        <tr>
            <td><?= htmlspecialchars($user->id) ?></td>
            <td><?= htmlspecialchars($user->name) ?></td>
            <td><?= htmlspecialchars($user->email) ?></td>
            <td><?= $user->createdAt?->format('Y-m-d H:i') ?></td>
            <td>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $user->id ?>">
                    <button type="submit" onclick="return confirm('Delete?')">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
```

## Common Patterns

### Transaction Example

```php
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

### Custom Repository Methods

Add custom methods to your generated repositories:

```php
// app/Repositories/UserRepository.php

class UserRepository extends AbstractRepository
{
    // ... generated methods ...
    
    // Add your custom methods:
    
    public function findActiveUsers(): array
    {
        return $this->query(
            "SELECT * FROM users WHERE status = ? AND deleted_at IS NULL",
            ['active']
        );
    }
    
    public function findByRole(string $roleName): array
    {
        return $this->query(
            "SELECT u.* FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE r.name = ?",
            [$roleName]
        );
    }
    
    public function countByStatus(string $status): int
    {
        $result = $this->connection->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE status = ?",
            [$status]
        );
        return (int)$result;
    }
}
```

### Working with Relationships

```php
// Assuming User has a role_id foreign key

$user = $userRepo->findById(1);

// The generated model has relationship methods:
// $role = $user->role(); // Returns Role|null

// You'll need to implement the relationship loading:
// For now, you can load manually:

$roleRepo = $container->get(RoleRepository::class);
$role = $roleRepo->findById($user->roleId);
```

## Configuration Options

### Database Configuration

```php
// MySQL with Unix Socket
$config = new DatabaseConfig(
    driver: DatabaseDriver::MYSQL,
    host: 'localhost',
    database: 'mydb',
    username: 'user',
    password: 'pass',
    unixSocket: '/var/run/mysqld/mysqld.sock'
);

// PostgreSQL with Schema
$config = new DatabaseConfig(
    driver: DatabaseDriver::POSTGRESQL,
    host: 'localhost',
    port: 5432,
    database: 'mydb',
    username: 'user',
    password: 'pass',
    schema: 'public'
);

// SQLite
$config = new DatabaseConfig(
    driver: DatabaseDriver::SQLITE,
    database: __DIR__ . '/database.sqlite'
);
```

### Generator Options

```php
// Disable property hooks
$modelGenerator = new ModelGenerator(
    namespace: 'App\\Models',
    usePropertyHooks: false,  // Disable validation hooks
    useAttributes: true
);

// Different namespace
$modelGenerator = new ModelGenerator(
    namespace: 'MyApp\\Domain\\Models',
    usePropertyHooks: true,
    useAttributes: true
);
```

### Cache Configuration

```php
// Custom cache directory and TTL
$cache = new FileSchemaCache(
    cacheDirectory: __DIR__ . '/var/cache/schema',
    defaultTtl: 7200  // 2 hours
);

// Clear cache
$cache->clear();
$cache->clearTable('users');
```

## Troubleshooting

### "Class not found" errors
```bash
composer dump-autoload
```

### "Connection failed" errors
- Check database credentials
- Verify database server is running
- Check firewall settings

### "Table not found" errors
- Regenerate models after schema changes
- Clear schema cache

### Property validation errors
If you don't want validation, disable property hooks:
```php
$modelGenerator = new ModelGenerator(
    usePropertyHooks: false
);
```

## Next Steps

1. **Read the full guide**: `MODERN_FRAMEWORK_GUIDE.md`
2. **Check examples**: `examples/ModernUsageExample.php`
3. **Review architecture**: `MODERNIZATION_ROADMAP.md`
4. **See all changes**: `MODERNIZATION_SUMMARY.md`

## Support

For issues and questions, please refer to the documentation files or create an issue on GitHub.

## License

MIT License - See LICENSE file for details.
