<?php

declare(strict_types=1);

namespace Laf\Schema\Relationship;

use Laf\Schema\Inspector\SchemaInspectorInterface;
use Laf\Schema\Metadata\RelationshipMetadata;
use Laf\Schema\Metadata\TableMetadata;

/**
 * Relationship Detector
 * 
 * Detects relationships between database tables
 */
class RelationshipDetector
{
    /**
     * Detect all relationships for a table
     *
     * @param TableMetadata $table
     * @param SchemaInspectorInterface $inspector
     * @return TableMetadata
     */
    public function detectRelationships(TableMetadata $table, SchemaInspectorInterface $inspector): TableMetadata
    {
        $relationships = [];
        
        // Detect many-to-one relationships (from foreign keys)
        foreach ($table->foreignKeys as $foreignKey) {
            $relationships[] = new RelationshipMetadata(
                type: RelationshipType::MANY_TO_ONE,
                localTable: $table->name,
                localColumn: $foreignKey->column,
                foreignTable: $foreignKey->referencedTable,
                foreignColumn: $foreignKey->referencedColumn,
                foreignKey: $foreignKey,
            );
        }
        
        // Detect one-to-many relationships (reverse foreign keys)
        $referencingTables = $inspector->getReferencingTables($table->name);
        
        foreach ($referencingTables as $referencingTable) {
            // Skip if it's a pivot table
            if ($inspector->isPivotTable($referencingTable)) {
                continue;
            }
            
            $referencingMetadata = $inspector->inspectTable($referencingTable);
            
            foreach ($referencingMetadata->foreignKeys as $foreignKey) {
                if ($foreignKey->referencedTable === $table->name) {
                    // Check if this is one-to-one or one-to-many
                    $isOneToOne = $this->isOneToOneRelationship($referencingMetadata, $foreignKey->column);
                    
                    $relationships[] = new RelationshipMetadata(
                        type: $isOneToOne ? RelationshipType::ONE_TO_ONE : RelationshipType::ONE_TO_MANY,
                        localTable: $table->name,
                        localColumn: $foreignKey->referencedColumn,
                        foreignTable: $referencingTable,
                        foreignColumn: $foreignKey->column,
                    );
                }
            }
        }
        
        // Detect many-to-many relationships (through pivot tables)
        $manyToManyRelationships = $this->detectManyToManyRelationships($table, $inspector);
        $relationships = array_merge($relationships, $manyToManyRelationships);
        
        return new TableMetadata(
            name: $table->name,
            columns: $table->columns,
            indexes: $table->indexes,
            foreignKeys: $table->foreignKeys,
            relationships: $relationships,
            comment: $table->comment,
            engine: $table->engine,
            collation: $table->collation,
            charset: $table->charset,
        );
    }

    /**
     * Detect many-to-many relationships through pivot tables
     *
     * @param TableMetadata $table
     * @param SchemaInspectorInterface $inspector
     * @return array<RelationshipMetadata>
     */
    private function detectManyToManyRelationships(TableMetadata $table, SchemaInspectorInterface $inspector): array
    {
        $relationships = [];
        $referencingTables = $inspector->getReferencingTables($table->name);
        
        foreach ($referencingTables as $potentialPivot) {
            if (!$inspector->isPivotTable($potentialPivot)) {
                continue;
            }
            
            $pivotMetadata = $inspector->inspectTable($potentialPivot);
            
            // Find the other table in the many-to-many relationship
            foreach ($pivotMetadata->foreignKeys as $foreignKey) {
                if ($foreignKey->referencedTable !== $table->name) {
                    // Found the other side of the relationship
                    $localFk = null;
                    $foreignFk = $foreignKey;
                    
                    // Find the FK that points to our table
                    foreach ($pivotMetadata->foreignKeys as $fk) {
                        if ($fk->referencedTable === $table->name) {
                            $localFk = $fk;
                            break;
                        }
                    }
                    
                    if ($localFk !== null) {
                        $relationships[] = new RelationshipMetadata(
                            type: RelationshipType::MANY_TO_MANY,
                            localTable: $table->name,
                            localColumn: $localFk->referencedColumn,
                            foreignTable: $foreignFk->referencedTable,
                            foreignColumn: $foreignFk->referencedColumn,
                            pivotTable: $potentialPivot,
                            pivotLocalColumn: $localFk->column,
                            pivotForeignColumn: $foreignFk->column,
                        );
                    }
                }
            }
        }
        
        return $relationships;
    }

    /**
     * Determine if a foreign key represents a one-to-one relationship
     * 
     * A relationship is one-to-one if the foreign key column is unique
     *
     * @param TableMetadata $table
     * @param string $columnName
     * @return bool
     */
    private function isOneToOneRelationship(TableMetadata $table, string $columnName): bool
    {
        // Check if the column is part of a unique index
        foreach ($table->indexes as $index) {
            if ($index->unique && !$index->primary) {
                if (in_array($columnName, $index->columns, true)) {
                    // If it's a single-column unique index, it's one-to-one
                    return count($index->columns) === 1;
                }
            }
        }
        
        // Check if it's the primary key (also makes it one-to-one)
        $pkIndex = $table->getPrimaryKeyIndex();
        if ($pkIndex !== null && count($pkIndex->columns) === 1 && $pkIndex->columns[0] === $columnName) {
            return true;
        }
        
        return false;
    }
}
