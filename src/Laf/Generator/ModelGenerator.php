<?php

declare(strict_types=1);

namespace Laf\Generator;

use Laf\Schema\Metadata\ColumnMetadata;
use Laf\Schema\Metadata\TableMetadata;
use Laf\Schema\Relationship\RelationshipType;

/**
 * Model Generator
 * 
 * Generates PHP 8.4 model classes from table metadata
 */
class ModelGenerator
{
    public function __construct(
        private readonly string $namespace = 'App\\Models',
        private readonly bool $usePropertyHooks = true,
        private readonly bool $useAttributes = true,
    ) {}

    /**
     * Generate model class code
     *
     * @param TableMetadata $table
     * @return string
     */
    public function generate(TableMetadata $table): string
    {
        $className = $table->getClassName();
        $properties = $this->generateProperties($table);
        $constructor = $this->generateConstructor($table);
        $methods = $this->generateMethods($table);
        $relationships = $this->generateRelationshipMethods($table);
        $attributes = $this->useAttributes ? $this->generateClassAttributes($table) : '';

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespace};

use DateTimeInterface;
use DateTimeImmutable;

/**
 * {$className} Model
 * 
 * Generated from table: {$table->name}
 * {$this->generateTableComment($table)}
 */
{$attributes}class {$className}
{
{$properties}

{$constructor}

{$methods}

{$relationships}
}

PHP;
    }

    /**
     * Generate class attributes
     *
     * @param TableMetadata $table
     * @return string
     */
    private function generateClassAttributes(TableMetadata $table): string
    {
        $pk = $table->getSinglePrimaryKey();
        $pkName = $pk?->name ?? 'id';
        
        return <<<PHP
#[\Laf\Model\Attributes\Table(name: '{$table->name}')]
#[\Laf\Model\Attributes\PrimaryKey('{$pkName}')]

PHP;
    }

    /**
     * Generate table comment
     *
     * @param TableMetadata $table
     * @return string
     */
    private function generateTableComment(TableMetadata $table): string
    {
        $lines = [];
        
        if ($table->comment) {
            $lines[] = $table->comment;
        }
        
        if ($table->hasTimestamps()) {
            $lines[] = 'Has timestamps: created_at, updated_at';
        }
        
        if ($table->hasSoftDelete()) {
            $lines[] = 'Supports soft deletes';
        }
        
        return !empty($lines) ? "\n * " . implode("\n * ", $lines) : '';
    }

    /**
     * Generate properties with PHP 8.4 property hooks
     *
     * @param TableMetadata $table
     * @return string
     */
    private function generateProperties(TableMetadata $table): string
    {
        $properties = [];
        
        foreach ($table->columns as $column) {
            $properties[] = $this->generateProperty($column, $table);
        }
        
        return implode("\n\n", $properties);
    }

    /**
     * Generate a single property
     *
     * @param ColumnMetadata $column
     * @param TableMetadata $table
     * @return string
     */
    private function generateProperty(ColumnMetadata $column, TableMetadata $table): string
    {
        $type = $column->getPhpTypeWithNull();
        $propertyName = $column->getPropertyName();
        $attributes = $this->generatePropertyAttributes($column, $table);
        $comment = $this->generatePropertyComment($column);
        
        if ($this->usePropertyHooks && !$column->autoIncrement) {
            // Use property hooks for validation
            $hooks = $this->generatePropertyHooks($column);
            
            return <<<PHP
    /**
     * {$comment}
     */
    {$attributes}public {$type} \${$propertyName}{$hooks};
PHP;
        } else {
            // Simple property
            return <<<PHP
    /**
     * {$comment}
     */
    {$attributes}public {$type} \${$propertyName};
PHP;
        }
    }

    /**
     * Generate property attributes
     *
     * @param ColumnMetadata $column
     * @param TableMetadata $table
     * @return string
     */
    private function generatePropertyAttributes(ColumnMetadata $column, TableMetadata $table): string
    {
        if (!$this->useAttributes) {
            return '';
        }
        
        $attributes = [];
        
        // Column attribute
        $columnAttrs = ["name: '{$column->name}'", "type: '{$column->type}'"];
        
        if ($column->length !== null) {
            $columnAttrs[] = "length: {$column->length}";
        }
        
        if (!$column->nullable) {
            $columnAttrs[] = "nullable: false";
        }
        
        $attributes[] = "#[\\Laf\\Model\\Attributes\\Column(" . implode(', ', $columnAttrs) . ")]";
        
        // Foreign key attribute
        if ($fk = $table->getForeignKeyForColumn($column->name)) {
            $attributes[] = "#[\\Laf\\Model\\Attributes\\ForeignKey(table: '{$fk->referencedTable}', column: '{$fk->referencedColumn}')]";
        }
        
        // Unique attribute
        foreach ($table->getUniqueIndexes() as $index) {
            if (in_array($column->name, $index->columns) && count($index->columns) === 1) {
                $attributes[] = "#[\\Laf\\Model\\Attributes\\Unique]";
                break;
            }
        }
        
        return !empty($attributes) ? implode("\n    ", $attributes) . "\n    " : '';
    }

    /**
     * Generate property comment
     *
     * @param ColumnMetadata $column
     * @return string
     */
    private function generatePropertyComment(ColumnMetadata $column): string
    {
        $parts = [$column->name];
        
        if ($column->comment) {
            $parts[] = "- {$column->comment}";
        }
        
        if ($column->autoIncrement) {
            $parts[] = '(auto-increment)';
        }
        
        return implode(' ', $parts);
    }

    /**
     * Generate property hooks for validation
     *
     * @param ColumnMetadata $column
     * @return string
     */
    private function generatePropertyHooks(ColumnMetadata $column): string
    {
        if ($column->nullable || $column->autoIncrement) {
            return '';
        }
        
        $validations = [];
        
        // String length validation
        if ($column->isString() && $column->length !== null) {
            $validations[] = "if (strlen(\$value) > {$column->length}) throw new \\InvalidArgumentException('{$column->name} exceeds maximum length of {$column->length}');";
        }
        
        // Numeric range validation
        if ($column->isNumeric() && $column->unsigned) {
            $validations[] = "if (\$value < 0) throw new \\InvalidArgumentException('{$column->name} must be unsigned');";
        }
        
        if (empty($validations)) {
            return '';
        }
        
        $validationCode = implode(' ', $validations);
        
        return " {\n        set {\n            {$validationCode}\n            \$this->{$column->getPropertyName()} = \$value;\n        }\n    }";
    }

    /**
     * Generate constructor
     *
     * @param TableMetadata $table
     * @return string
     */
    private function generateConstructor(TableMetadata $table): string
    {
        $params = [];
        $assignments = [];
        
        foreach ($table->columns as $column) {
            // Skip auto-increment columns in constructor
            if ($column->autoIncrement) {
                continue;
            }
            
            $type = $column->getPhpTypeWithNull();
            $propertyName = $column->getPropertyName();
            $default = $column->nullable ? ' = null' : '';
            
            $params[] = "        {$type} \${$propertyName}{$default},";
            $assignments[] = "        \$this->{$propertyName} = \${$propertyName};";
        }
        
        if (empty($params)) {
            return "    public function __construct() {}";
        }
        
        $paramsStr = implode("\n", $params);
        $assignmentsStr = implode("\n", $assignments);
        
        return <<<PHP
    public function __construct(
{$paramsStr}
    ) {
{$assignmentsStr}
    }
PHP;
    }

    /**
     * Generate utility methods
     *
     * @param TableMetadata $table
     * @return string
     */
    private function generateMethods(TableMetadata $table): string
    {
        $methods = [];
        
        // toArray method
        $methods[] = $this->generateToArrayMethod($table);
        
        // fromArray method
        $methods[] = $this->generateFromArrayMethod($table);
        
        return implode("\n\n", $methods);
    }

    /**
     * Generate toArray method
     *
     * @param TableMetadata $table
     * @return string
     */
    private function generateToArrayMethod(TableMetadata $table): string
    {
        $properties = [];
        
        foreach ($table->columns as $column) {
            $propertyName = $column->getPropertyName();
            $columnName = $column->name;
            
            if ($column->isDateTime()) {
                $properties[] = "            '{$columnName}' => \$this->{$propertyName}?->format('Y-m-d H:i:s'),";
            } else {
                $properties[] = "            '{$columnName}' => \$this->{$propertyName},";
            }
        }
        
        $propertiesStr = implode("\n", $properties);
        
        return <<<PHP
    /**
     * Convert model to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
{$propertiesStr}
        ];
    }
PHP;
    }

    /**
     * Generate fromArray method
     *
     * @param TableMetadata $table
     * @return string
     */
    private function generateFromArrayMethod(TableMetadata $table): string
    {
        $className = $table->getClassName();
        $properties = [];
        
        foreach ($table->columns as $column) {
            if ($column->autoIncrement) {
                continue;
            }
            
            $propertyName = $column->getPropertyName();
            $columnName = $column->name;
            
            if ($column->isDateTime()) {
                $properties[] = "            {$propertyName}: isset(\$data['{$columnName}']) ? new DateTimeImmutable(\$data['{$columnName}']) : null,";
            } else {
                $default = $column->nullable ? 'null' : $this->getDefaultValue($column);
                $properties[] = "            {$propertyName}: \$data['{$columnName}'] ?? {$default},";
            }
        }
        
        $propertiesStr = implode("\n", $properties);
        
        return <<<PHP
    /**
     * Create model from array
     *
     * @param array<string, mixed> \$data
     * @return self
     */
    public static function fromArray(array \$data): self
    {
        return new self(
{$propertiesStr}
        );
    }
PHP;
    }

    /**
     * Get default value for a column type
     *
     * @param ColumnMetadata $column
     * @return string
     */
    private function getDefaultValue(ColumnMetadata $column): string
    {
        if ($column->isString()) {
            return "''";
        }
        
        if ($column->isNumeric()) {
            return '0';
        }
        
        if ($column->isBoolean()) {
            return 'false';
        }
        
        return 'null';
    }

    /**
     * Generate relationship methods
     *
     * @param TableMetadata $table
     * @return string
     */
    private function generateRelationshipMethods(TableMetadata $table): string
    {
        $methods = [];
        
        foreach ($table->relationships as $relationship) {
            $methods[] = $this->generateRelationshipMethod($relationship);
        }
        
        return !empty($methods) ? implode("\n\n", $methods) : '';
    }

    /**
     * Generate a single relationship method
     *
     * @param \Laf\Schema\Metadata\RelationshipMetadata $relationship
     * @return string
     */
    private function generateRelationshipMethod(\Laf\Schema\Metadata\RelationshipMetadata $relationship): string
    {
        $methodName = $relationship->getMethodName();
        $foreignClass = $relationship->getForeignClassName();
        $returnType = $relationship->type->getReturnType($foreignClass);
        $phpDocReturn = $relationship->type->getPhpDocReturnType($foreignClass);
        
        $comment = match($relationship->type) {
            RelationshipType::ONE_TO_ONE => "Get related {$foreignClass} (one-to-one)",
            RelationshipType::ONE_TO_MANY => "Get related {$foreignClass} records (one-to-many)",
            RelationshipType::MANY_TO_ONE => "Get related {$foreignClass} (many-to-one)",
            RelationshipType::MANY_TO_MANY => "Get related {$foreignClass} records (many-to-many)",
        };
        
        return <<<PHP
    /**
     * {$comment}
     *
     * @return {$phpDocReturn}
     */
    public function {$methodName}(): {$returnType}
    {
        // TODO: Implement relationship loading via repository
        throw new \\RuntimeException('Relationship loading not yet implemented');
    }
PHP;
    }
}
