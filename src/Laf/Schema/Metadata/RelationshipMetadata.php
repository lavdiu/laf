<?php

declare(strict_types=1);

namespace Laf\Schema\Metadata;

use Laf\Schema\Relationship\RelationshipType;

/**
 * Relationship Metadata
 * 
 * Represents a relationship between two tables
 */
readonly class RelationshipMetadata
{
    /**
     * @param RelationshipType $type Relationship type
     * @param string $localTable Local table name
     * @param string $localColumn Local column name
     * @param string $foreignTable Foreign table name
     * @param string $foreignColumn Foreign column name
     * @param string|null $pivotTable Pivot table name (for many-to-many)
     * @param string|null $pivotLocalColumn Pivot local column (for many-to-many)
     * @param string|null $pivotForeignColumn Pivot foreign column (for many-to-many)
     * @param ForeignKeyMetadata|null $foreignKey Associated foreign key metadata
     */
    public function __construct(
        public RelationshipType $type,
        public string $localTable,
        public string $localColumn,
        public string $foreignTable,
        public string $foreignColumn,
        public ?string $pivotTable = null,
        public ?string $pivotLocalColumn = null,
        public ?string $pivotForeignColumn = null,
        public ?ForeignKeyMetadata $foreignKey = null,
    ) {}

    /**
     * Check if this is a many-to-many relationship
     *
     * @return bool
     */
    public function isManyToMany(): bool
    {
        return $this->type === RelationshipType::MANY_TO_MANY;
    }

    /**
     * Check if this is a one-to-many relationship
     *
     * @return bool
     */
    public function isOneToMany(): bool
    {
        return $this->type === RelationshipType::ONE_TO_MANY;
    }

    /**
     * Check if this is a many-to-one relationship
     *
     * @return bool
     */
    public function isManyToOne(): bool
    {
        return $this->type === RelationshipType::MANY_TO_ONE;
    }

    /**
     * Check if this is a one-to-one relationship
     *
     * @return bool
     */
    public function isOneToOne(): bool
    {
        return $this->type === RelationshipType::ONE_TO_ONE;
    }

    /**
     * Get the method name for this relationship
     *
     * @return string
     */
    public function getMethodName(): string
    {
        $tableName = $this->type->isPlural() 
            ? $this->pluralize($this->foreignTable)
            : $this->foreignTable;

        return lcfirst(str_replace('_', '', ucwords($tableName, '_')));
    }

    /**
     * Get the class name for the foreign table
     *
     * @return string
     */
    public function getForeignClassName(): string
    {
        return str_replace('_', '', ucwords($this->foreignTable, '_'));
    }

    /**
     * Get the class name for the local table
     *
     * @return string
     */
    public function getLocalClassName(): string
    {
        return str_replace('_', '', ucwords($this->localTable, '_'));
    }

    /**
     * Get the inverse relationship
     *
     * @return self
     */
    public function inverse(): self
    {
        if ($this->isManyToMany()) {
            return new self(
                type: RelationshipType::MANY_TO_MANY,
                localTable: $this->foreignTable,
                localColumn: $this->foreignColumn,
                foreignTable: $this->localTable,
                foreignColumn: $this->localColumn,
                pivotTable: $this->pivotTable,
                pivotLocalColumn: $this->pivotForeignColumn,
                pivotForeignColumn: $this->pivotLocalColumn,
            );
        }

        return new self(
            type: $this->type->inverse(),
            localTable: $this->foreignTable,
            localColumn: $this->foreignColumn,
            foreignTable: $this->localTable,
            foreignColumn: $this->localColumn,
        );
    }

    /**
     * Simple pluralization (can be enhanced with a proper library)
     *
     * @param string $word
     * @return string
     */
    private function pluralize(string $word): string
    {
        // Simple rules - in production, use a proper inflector library
        if (str_ends_with($word, 'y')) {
            return substr($word, 0, -1) . 'ies';
        }
        if (str_ends_with($word, 's') || str_ends_with($word, 'x') || str_ends_with($word, 'ch') || str_ends_with($word, 'sh')) {
            return $word . 'es';
        }
        return $word . 's';
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'local_table' => $this->localTable,
            'local_column' => $this->localColumn,
            'foreign_table' => $this->foreignTable,
            'foreign_column' => $this->foreignColumn,
            'pivot_table' => $this->pivotTable,
            'pivot_local_column' => $this->pivotLocalColumn,
            'pivot_foreign_column' => $this->pivotForeignColumn,
            'method_name' => $this->getMethodName(),
            'foreign_class' => $this->getForeignClassName(),
            'local_class' => $this->getLocalClassName(),
        ];
    }
}
