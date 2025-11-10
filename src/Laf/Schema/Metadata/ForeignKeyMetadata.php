<?php

declare(strict_types=1);

namespace Laf\Schema\Metadata;

/**
 * Foreign Key Metadata
 * 
 * Represents metadata for a foreign key constraint
 */
readonly class ForeignKeyMetadata
{
    /**
     * @param string $name Constraint name
     * @param string $column Local column name
     * @param string $referencedTable Referenced table name
     * @param string $referencedColumn Referenced column name
     * @param string $onUpdate ON UPDATE action (CASCADE, SET NULL, RESTRICT, NO ACTION)
     * @param string $onDelete ON DELETE action (CASCADE, SET NULL, RESTRICT, NO ACTION)
     */
    public function __construct(
        public string $name,
        public string $column,
        public string $referencedTable,
        public string $referencedColumn,
        public string $onUpdate = 'RESTRICT',
        public string $onDelete = 'RESTRICT',
    ) {}

    /**
     * Check if foreign key cascades on update
     *
     * @return bool
     */
    public function cascadesOnUpdate(): bool
    {
        return strtoupper($this->onUpdate) === 'CASCADE';
    }

    /**
     * Check if foreign key cascades on delete
     *
     * @return bool
     */
    public function cascadesOnDelete(): bool
    {
        return strtoupper($this->onDelete) === 'CASCADE';
    }

    /**
     * Check if foreign key sets null on update
     *
     * @return bool
     */
    public function setsNullOnUpdate(): bool
    {
        return strtoupper($this->onUpdate) === 'SET NULL';
    }

    /**
     * Check if foreign key sets null on delete
     *
     * @return bool
     */
    public function setsNullOnDelete(): bool
    {
        return strtoupper($this->onDelete) === 'SET NULL';
    }

    /**
     * Get the relationship method name for the referenced table
     *
     * @return string
     */
    public function getRelationshipMethodName(): string
    {
        // Convert table name to camelCase (e.g., user_roles -> userRole)
        $name = preg_replace('/_id$/', '', $this->column);
        return lcfirst(str_replace('_', '', ucwords($name, '_')));
    }

    /**
     * Get the class name for the referenced table
     *
     * @return string
     */
    public function getReferencedClassName(): string
    {
        // Convert table name to PascalCase (e.g., user_roles -> UserRole)
        return str_replace('_', '', ucwords($this->referencedTable, '_'));
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
            'column' => $this->column,
            'referenced_table' => $this->referencedTable,
            'referenced_column' => $this->referencedColumn,
            'on_update' => $this->onUpdate,
            'on_delete' => $this->onDelete,
            'cascades_on_update' => $this->cascadesOnUpdate(),
            'cascades_on_delete' => $this->cascadesOnDelete(),
            'relationship_method' => $this->getRelationshipMethodName(),
            'referenced_class' => $this->getReferencedClassName(),
        ];
    }
}
