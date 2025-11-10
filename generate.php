<?php

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

