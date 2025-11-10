<?php

declare(strict_types=1);

namespace Laf\Generator;

use Laf\Schema\Metadata\TableMetadata;

/**
 * Repository Generator
 * 
 * Generates repository classes for data access
 */
class RepositoryGenerator
{
    public function __construct(
        private readonly string $namespace = 'App\\Repositories',
        private readonly string $modelNamespace = 'App\\Models',
    ) {}

    /**
     * Generate repository class code
     *
     * @param TableMetadata $table
     * @return string
     */
    public function generate(TableMetadata $table): string
    {
        $className = $table->getClassName();
        $repositoryName = "{$className}Repository";
        $modelClass = "{$this->modelNamespace}\\{$className}";
        $pk = $table->getSinglePrimaryKey();
        $pkProperty = $pk?->getPropertyName() ?? 'id';
        $pkType = $pk?->getPhpType() ?? 'int';

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespace};

use {$modelClass};
use Laf\Repository\AbstractRepository;
use Laf\Core\Database\ConnectionInterface;

/**
 * {$repositoryName}
 * 
 * Repository for {$className} model
 * Generated from table: {$table->name}
 */
class {$repositoryName} extends AbstractRepository
{
    public function __construct(ConnectionInterface \$connection)
    {
        parent::__construct(\$connection, {$className}::class, '{$table->name}');
    }

    /**
     * Find a {$className} by ID
     *
     * @param {$pkType} \$id
     * @return {$className}|null
     */
    public function findById({$pkType} \$id): ?{$className}
    {
        return \$this->findOne(['{$pk?->name ?? 'id'}' => \$id]);
    }

    /**
     * Get all {$className} records
     *
     * @return array<{$className}>
     */
    public function findAll(): array
    {
        return \$this->find();
    }

    /**
     * Save a {$className}
     *
     * @param {$className} \$model
     * @return {$className}
     */
    public function save({$className} \$model): {$className}
    {
        if (\$model->{$pkProperty} === null) {
            return \$this->insert(\$model);
        }
        
        return \$this->update(\$model);
    }

    /**
     * Insert a new {$className}
     *
     * @param {$className} \$model
     * @return {$className}
     */
    private function insert({$className} \$model): {$className}
    {
        \$data = \$model->toArray();
        unset(\$data['{$pk?->name ?? 'id'}']); // Remove auto-increment field
        
        \$sql = \$this->buildInsertQuery(\$data);
        \$this->connection->execute(\$sql, array_values(\$data));
        
        \$model->{$pkProperty} = ({$pkType})\$this->connection->lastInsertId();
        
        return \$model;
    }

    /**
     * Update an existing {$className}
     *
     * @param {$className} \$model
     * @return {$className}
     */
    private function update({$className} \$model): {$className}
    {
        \$data = \$model->toArray();
        \$id = \$data['{$pk?->name ?? 'id'}'];
        unset(\$data['{$pk?->name ?? 'id'}']);
        
        \$sql = \$this->buildUpdateQuery(\$data, ['{$pk?->name ?? 'id'}' => \$id]);
        \$this->connection->execute(\$sql, [...array_values(\$data), \$id]);
        
        return \$model;
    }

    /**
     * Delete a {$className}
     *
     * @param {$pkType} \$id
     * @return bool
     */
    public function delete({$pkType} \$id): bool
    {
        \$sql = "DELETE FROM {\$this->tableName} WHERE {$pk?->name ?? 'id'} = ?";
        \$affected = \$this->connection->execute(\$sql, [\$id]);
        
        return \$affected > 0;
    }

    /**
     * Count all records
     *
     * @return int
     */
    public function count(): int
    {
        \$result = \$this->connection->fetchColumn(
            "SELECT COUNT(*) FROM {\$this->tableName}"
        );
        
        return (int)\$result;
    }

{$this->generateCustomMethods($table)}
}

PHP;
    }

    /**
     * Generate custom finder methods based on unique indexes
     *
     * @param TableMetadata $table
     * @return string
     */
    private function generateCustomMethods(TableMetadata $table): string
    {
        $methods = [];
        $className = $table->getClassName();

        // Generate finders for unique columns
        foreach ($table->getUniqueColumns() as $column) {
            $methodName = 'findBy' . ucfirst($column->getPropertyName());
            $paramType = $column->getPhpType();
            $propertyName = $column->getPropertyName();

            $methods[] = <<<PHP
    /**
     * Find {$className} by {$column->name}
     *
     * @param {$paramType} \${$propertyName}
     * @return {$className}|null
     */
    public function {$methodName}({$paramType} \${$propertyName}): ?{$className}
    {
        return \$this->findOne(['{$column->name}' => \${$propertyName}]);
    }
PHP;
        }

        // Generate finders for foreign keys
        foreach ($table->foreignKeys as $fk) {
            $column = $table->getColumn($fk->column);
            if ($column === null) continue;

            $methodName = 'findBy' . ucfirst($column->getPropertyName());
            $paramType = $column->getPhpType();
            $propertyName = $column->getPropertyName();

            // Skip if already generated for unique column
            $alreadyExists = false;
            foreach ($table->getUniqueColumns() as $uniqueCol) {
                if ($uniqueCol->name === $column->name) {
                    $alreadyExists = true;
                    break;
                }
            }

            if (!$alreadyExists) {
                $methods[] = <<<PHP
    /**
     * Find {$className} records by {$column->name}
     *
     * @param {$paramType} \${$propertyName}
     * @return array<{$className}>
     */
    public function {$methodName}({$paramType} \${$propertyName}): array
    {
        return \$this->find(['{$column->name}' => \${$propertyName}]);
    }
PHP;
            }
        }

        return !empty($methods) ? "\n" . implode("\n\n", $methods) : '';
    }
}
