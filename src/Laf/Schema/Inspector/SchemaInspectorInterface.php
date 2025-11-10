<?php

declare(strict_types=1);

namespace Laf\Schema\Inspector;

use Laf\Schema\Metadata\TableMetadata;

/**
 * Schema Inspector Interface
 * 
 * Interface for inspecting database schema
 */
interface SchemaInspectorInterface
{
    /**
     * Get all table names in the database
     *
     * @return array<string>
     */
    public function getTables(): array;

    /**
     * Get all view names in the database
     *
     * @return array<string>
     */
    public function getViews(): array;

    /**
     * Check if a table exists
     *
     * @param string $table
     * @return bool
     */
    public function tableExists(string $table): bool;

    /**
     * Inspect a table and return its metadata
     *
     * @param string $table
     * @return TableMetadata
     */
    public function inspectTable(string $table): TableMetadata;

    /**
     * Inspect multiple tables
     *
     * @param array<string> $tables
     * @return array<string, TableMetadata>
     */
    public function inspectTables(array $tables): array;

    /**
     * Inspect all tables in the database
     *
     * @return array<string, TableMetadata>
     */
    public function inspectAllTables(): array;

    /**
     * Get tables that reference the given table (reverse foreign keys)
     *
     * @param string $table
     * @return array<string>
     */
    public function getReferencingTables(string $table): array;

    /**
     * Get tables referenced by the given table (foreign keys)
     *
     * @param string $table
     * @return array<string>
     */
    public function getReferencedTables(string $table): array;

    /**
     * Detect pivot tables (many-to-many junction tables)
     *
     * @return array<string>
     */
    public function detectPivotTables(): array;

    /**
     * Check if a table is a pivot table
     *
     * @param string $table
     * @return bool
     */
    public function isPivotTable(string $table): bool;
}
