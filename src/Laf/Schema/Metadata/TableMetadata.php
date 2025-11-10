<?php

declare(strict_types=1);

namespace Laf\Schema\Metadata;

/**
 * Table Metadata
 * 
 * Represents complete metadata for a database table
 */
readonly class TableMetadata
{
    /**
     * @param string $name Table name
     * @param array<string, ColumnMetadata> $columns Table columns indexed by name
     * @param array<string, IndexMetadata> $indexes Table indexes indexed by name
     * @param array<string, ForeignKeyMetadata> $foreignKeys Foreign keys indexed by name
     * @param array<RelationshipMetadata> $relationships Detected relationships
     * @param string|null $comment Table comment
     * @param string|null $engine Storage engine (MySQL)
     * @param string|null $collation Table collation
     * @param string|null $charset Table charset
     */
    public function __construct(
        public string $name,
        public array $columns = [],
        public array $indexes = [],
        public array $foreignKeys = [],
        public array $relationships = [],
        public ?string $comment = null,
        public ?string $engine = null,
        public ?string $collation = null,
        public ?string $charset = null,
    ) {}

    /**
     * Get a column by name
     *
     * @param string $name
     * @return ColumnMetadata|null
     */
    public function getColumn(string $name): ?ColumnMetadata
    {
        return $this->columns[$name] ?? null;
    }

    /**
     * Check if table has a column
     *
     * @param string $name
     * @return bool
     */
    public function hasColumn(string $name): bool
    {
        return isset($this->columns[$name]);
    }

    /**
     * Get primary key columns
     *
     * @return array<ColumnMetadata>
     */
    public function getPrimaryKeyColumns(): array
    {
        $primaryIndex = $this->getPrimaryKeyIndex();
        
        if ($primaryIndex === null) {
            return [];
        }

        return array_filter(
            array_map(
                fn($columnName) => $this->getColumn($columnName),
                $primaryIndex->columns
            )
        );
    }

    /**
     * Get the primary key index
     *
     * @return IndexMetadata|null
     */
    public function getPrimaryKeyIndex(): ?IndexMetadata
    {
        foreach ($this->indexes as $index) {
            if ($index->primary) {
                return $index;
            }
        }
        return null;
    }

    /**
     * Check if table has a composite primary key
     *
     * @return bool
     */
    public function hasCompositePrimaryKey(): bool
    {
        return count($this->getPrimaryKeyColumns()) > 1;
    }

    /**
     * Get the single primary key column (if not composite)
     *
     * @return ColumnMetadata|null
     */
    public function getSinglePrimaryKey(): ?ColumnMetadata
    {
        $pkColumns = $this->getPrimaryKeyColumns();
        return count($pkColumns) === 1 ? reset($pkColumns) : null;
    }

    /**
     * Get unique indexes (excluding primary key)
     *
     * @return array<IndexMetadata>
     */
    public function getUniqueIndexes(): array
    {
        return array_filter(
            $this->indexes,
            fn($index) => $index->unique && !$index->primary
        );
    }

    /**
     * Get columns that are part of unique indexes
     *
     * @return array<ColumnMetadata>
     */
    public function getUniqueColumns(): array
    {
        $uniqueColumns = [];
        
        foreach ($this->getUniqueIndexes() as $index) {
            foreach ($index->columns as $columnName) {
                if ($column = $this->getColumn($columnName)) {
                    $uniqueColumns[$columnName] = $column;
                }
            }
        }
        
        return array_values($uniqueColumns);
    }

    /**
     * Get foreign key for a specific column
     *
     * @param string $columnName
     * @return ForeignKeyMetadata|null
     */
    public function getForeignKeyForColumn(string $columnName): ?ForeignKeyMetadata
    {
        foreach ($this->foreignKeys as $fk) {
            if ($fk->column === $columnName) {
                return $fk;
            }
        }
        return null;
    }

    /**
     * Check if column is a foreign key
     *
     * @param string $columnName
     * @return bool
     */
    public function isForeignKey(string $columnName): bool
    {
        return $this->getForeignKeyForColumn($columnName) !== null;
    }

    /**
     * Get all nullable columns
     *
     * @return array<ColumnMetadata>
     */
    public function getNullableColumns(): array
    {
        return array_filter(
            $this->columns,
            fn($column) => $column->nullable
        );
    }

    /**
     * Get all required (non-nullable) columns
     *
     * @return array<ColumnMetadata>
     */
    public function getRequiredColumns(): array
    {
        return array_filter(
            $this->columns,
            fn($column) => !$column->nullable
        );
    }

    /**
     * Get auto-increment column
     *
     * @return ColumnMetadata|null
     */
    public function getAutoIncrementColumn(): ?ColumnMetadata
    {
        foreach ($this->columns as $column) {
            if ($column->autoIncrement) {
                return $column;
            }
        }
        return null;
    }

    /**
     * Get timestamp columns (created_at, updated_at, etc.)
     *
     * @return array<ColumnMetadata>
     */
    public function getTimestampColumns(): array
    {
        return array_filter(
            $this->columns,
            fn($column) => in_array($column->name, ['created_at', 'updated_at', 'deleted_at'])
        );
    }

    /**
     * Check if table has soft delete column
     *
     * @return bool
     */
    public function hasSoftDelete(): bool
    {
        return $this->hasColumn('deleted_at');
    }

    /**
     * Check if table has timestamps
     *
     * @return bool
     */
    public function hasTimestamps(): bool
    {
        return $this->hasColumn('created_at') && $this->hasColumn('updated_at');
    }

    /**
     * Get the class name for this table
     *
     * @return string
     */
    public function getClassName(): string
    {
        return str_replace('_', '', ucwords($this->name, '_'));
    }

    /**
     * Get relationships by type
     *
     * @param \Laf\Schema\Relationship\RelationshipType $type
     * @return array<RelationshipMetadata>
     */
    public function getRelationshipsByType(\Laf\Schema\Relationship\RelationshipType $type): array
    {
        return array_filter(
            $this->relationships,
            fn($rel) => $rel->type === $type
        );
    }

    /**
     * Get one-to-many relationships
     *
     * @return array<RelationshipMetadata>
     */
    public function getOneToManyRelationships(): array
    {
        return $this->getRelationshipsByType(\Laf\Schema\Relationship\RelationshipType::ONE_TO_MANY);
    }

    /**
     * Get many-to-one relationships
     *
     * @return array<RelationshipMetadata>
     */
    public function getManyToOneRelationships(): array
    {
        return $this->getRelationshipsByType(\Laf\Schema\Relationship\RelationshipType::MANY_TO_ONE);
    }

    /**
     * Get many-to-many relationships
     *
     * @return array<RelationshipMetadata>
     */
    public function getManyToManyRelationships(): array
    {
        return $this->getRelationshipsByType(\Laf\Schema\Relationship\RelationshipType::MANY_TO_MANY);
    }

    /**
     * Get one-to-one relationships
     *
     * @return array<RelationshipMetadata>
     */
    public function getOneToOneRelationships(): array
    {
        return $this->getRelationshipsByType(\Laf\Schema\Relationship\RelationshipType::ONE_TO_ONE);
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'class_name' => $this->getClassName(),
            'columns' => array_map(fn($col) => $col->toArray(), $this->columns),
            'indexes' => array_map(fn($idx) => $idx->toArray(), $this->indexes),
            'foreign_keys' => array_map(fn($fk) => $fk->toArray(), $this->foreignKeys),
            'relationships' => array_map(fn($rel) => $rel->toArray(), $this->relationships),
            'comment' => $this->comment,
            'engine' => $this->engine,
            'collation' => $this->collation,
            'charset' => $this->charset,
            'has_timestamps' => $this->hasTimestamps(),
            'has_soft_delete' => $this->hasSoftDelete(),
            'has_composite_pk' => $this->hasCompositePrimaryKey(),
        ];
    }
}
