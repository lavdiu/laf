<?php

declare(strict_types=1);

namespace Laf\Schema\Inspector;

use Laf\Core\Database\ConnectionInterface;
use Laf\Schema\Metadata\TableMetadata;
use Laf\Schema\Relationship\RelationshipDetector;

/**
 * Abstract Schema Inspector
 * 
 * Base class for database-specific schema inspectors
 */
abstract class AbstractSchemaInspector implements SchemaInspectorInterface
{
    protected ?RelationshipDetector $relationshipDetector = null;

    public function __construct(
        protected readonly ConnectionInterface $connection,
        protected readonly ?SchemaCacheInterface $cache = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function tableExists(string $table): bool
    {
        return in_array($table, $this->getTables(), true);
    }

    /**
     * {@inheritdoc}
     */
    public function inspectTable(string $table): TableMetadata
    {
        // Try to get from cache first
        if ($this->cache !== null) {
            $cached = $this->cache->get("table.{$table}");
            if ($cached instanceof TableMetadata) {
                return $cached;
            }
        }

        $metadata = $this->doInspectTable($table);

        // Detect relationships
        if ($this->relationshipDetector !== null) {
            $metadata = $this->relationshipDetector->detectRelationships($metadata, $this);
        }

        // Cache the result
        if ($this->cache !== null) {
            $this->cache->set("table.{$table}", $metadata);
        }

        return $metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function inspectTables(array $tables): array
    {
        $result = [];
        
        foreach ($tables as $table) {
            $result[$table] = $this->inspectTable($table);
        }
        
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function inspectAllTables(): array
    {
        return $this->inspectTables($this->getTables());
    }

    /**
     * {@inheritdoc}
     */
    public function isPivotTable(string $table): bool
    {
        $metadata = $this->inspectTable($table);
        
        // A pivot table typically has:
        // 1. Exactly 2 foreign keys
        // 2. Composite primary key from those 2 foreign keys
        // 3. No or very few additional columns
        
        if (count($metadata->foreignKeys) !== 2) {
            return false;
        }

        $pkColumns = $metadata->getPrimaryKeyColumns();
        if (count($pkColumns) !== 2) {
            return false;
        }

        // Check if PK columns are the FK columns
        $fkColumns = array_map(fn($fk) => $fk->column, $metadata->foreignKeys);
        $pkColumnNames = array_map(fn($col) => $col->name, $pkColumns);
        
        sort($fkColumns);
        sort($pkColumnNames);
        
        return $fkColumns === $pkColumnNames;
    }

    /**
     * {@inheritdoc}
     */
    public function detectPivotTables(): array
    {
        return array_filter(
            $this->getTables(),
            fn($table) => $this->isPivotTable($table)
        );
    }

    /**
     * Set the relationship detector
     *
     * @param RelationshipDetector $detector
     * @return void
     */
    public function setRelationshipDetector(RelationshipDetector $detector): void
    {
        $this->relationshipDetector = $detector;
    }

    /**
     * Perform the actual table inspection (driver-specific)
     *
     * @param string $table
     * @return TableMetadata
     */
    abstract protected function doInspectTable(string $table): TableMetadata;
}
