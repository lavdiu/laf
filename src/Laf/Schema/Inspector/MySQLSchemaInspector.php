<?php

declare(strict_types=1);

namespace Laf\Schema\Inspector;

use Laf\Schema\Metadata\ColumnMetadata;
use Laf\Schema\Metadata\ForeignKeyMetadata;
use Laf\Schema\Metadata\IndexMetadata;
use Laf\Schema\Metadata\TableMetadata;

/**
 * MySQL Schema Inspector
 * 
 * Inspects MySQL database schema
 */
class MySQLSchemaInspector extends AbstractSchemaInspector
{
    /**
     * {@inheritdoc}
     */
    public function getTables(): array
    {
        $database = $this->connection->getDatabaseName();
        
        $query = "
            SELECT TABLE_NAME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ?
            AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME
        ";
        
        $result = $this->connection->fetchAll($query, [$database]);
        
        return array_column($result, 'TABLE_NAME');
    }

    /**
     * {@inheritdoc}
     */
    public function getViews(): array
    {
        $database = $this->connection->getDatabaseName();
        
        $query = "
            SELECT TABLE_NAME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ?
            AND TABLE_TYPE = 'VIEW'
            ORDER BY TABLE_NAME
        ";
        
        $result = $this->connection->fetchAll($query, [$database]);
        
        return array_column($result, 'TABLE_NAME');
    }

    /**
     * {@inheritdoc}
     */
    public function getReferencingTables(string $table): array
    {
        $database = $this->connection->getDatabaseName();
        
        $query = "
            SELECT DISTINCT TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_SCHEMA = ?
            AND REFERENCED_TABLE_NAME = ?
            AND REFERENCED_COLUMN_NAME IS NOT NULL
            ORDER BY TABLE_NAME
        ";
        
        $result = $this->connection->fetchAll($query, [$database, $table]);
        
        return array_column($result, 'TABLE_NAME');
    }

    /**
     * {@inheritdoc}
     */
    public function getReferencedTables(string $table): array
    {
        $database = $this->connection->getDatabaseName();
        
        $query = "
            SELECT DISTINCT REFERENCED_TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY REFERENCED_TABLE_NAME
        ";
        
        $result = $this->connection->fetchAll($query, [$database, $table]);
        
        return array_column($result, 'REFERENCED_TABLE_NAME');
    }

    /**
     * {@inheritdoc}
     */
    protected function doInspectTable(string $table): TableMetadata
    {
        $database = $this->connection->getDatabaseName();
        
        // Get table information
        $tableInfo = $this->getTableInfo($table, $database);
        
        // Get columns
        $columns = $this->getColumns($table, $database);
        
        // Get indexes
        $indexes = $this->getIndexes($table, $database);
        
        // Get foreign keys
        $foreignKeys = $this->getForeignKeys($table, $database);
        
        return new TableMetadata(
            name: $table,
            columns: $columns,
            indexes: $indexes,
            foreignKeys: $foreignKeys,
            comment: $tableInfo['comment'] ?? null,
            engine: $tableInfo['engine'] ?? null,
            collation: $tableInfo['collation'] ?? null,
            charset: $tableInfo['charset'] ?? null,
        );
    }

    /**
     * Get table information
     *
     * @param string $table
     * @param string $database
     * @return array<string, mixed>
     */
    private function getTableInfo(string $table, string $database): array
    {
        $query = "
            SELECT 
                ENGINE as engine,
                TABLE_COLLATION as collation,
                TABLE_COMMENT as comment
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
        ";
        
        $result = $this->connection->fetchOne($query, [$database, $table]);
        
        if ($result === null) {
            return [];
        }
        
        // Extract charset from collation
        $charset = null;
        if (isset($result['collation'])) {
            $charset = explode('_', $result['collation'])[0];
        }
        
        return [
            'engine' => $result['engine'] ?? null,
            'collation' => $result['collation'] ?? null,
            'charset' => $charset,
            'comment' => $result['comment'] ?? null,
        ];
    }

    /**
     * Get table columns
     *
     * @param string $table
     * @param string $database
     * @return array<string, ColumnMetadata>
     */
    private function getColumns(string $table, string $database): array
    {
        $query = "
            SELECT 
                COLUMN_NAME as name,
                DATA_TYPE as type,
                CHARACTER_MAXIMUM_LENGTH as length,
                NUMERIC_PRECISION as precision,
                NUMERIC_SCALE as scale,
                IS_NULLABLE as nullable,
                COLUMN_DEFAULT as `default`,
                EXTRA as extra,
                COLUMN_COMMENT as comment,
                COLLATION_NAME as collation,
                COLUMN_TYPE as column_type
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ";
        
        $rows = $this->connection->fetchAll($query, [$database, $table]);
        
        $columns = [];
        
        foreach ($rows as $row) {
            $enumValues = [];
            
            // Extract enum values if applicable
            if ($row['type'] === 'enum' && preg_match("/^enum\('(.*)'\)$/", $row['column_type'], $matches)) {
                $enumValues = explode("','", $matches[1]);
            }
            
            // Extract charset from collation
            $charset = null;
            if ($row['collation'] !== null) {
                $charset = explode('_', $row['collation'])[0];
            }
            
            $columns[$row['name']] = new ColumnMetadata(
                name: $row['name'],
                type: $row['type'],
                length: $row['length'] !== null ? (int)$row['length'] : null,
                precision: $row['precision'] !== null ? (int)$row['precision'] : null,
                scale: $row['scale'] !== null ? (int)$row['scale'] : null,
                nullable: $row['nullable'] === 'YES',
                default: $row['default'],
                autoIncrement: str_contains($row['extra'] ?? '', 'auto_increment'),
                unsigned: str_contains($row['column_type'] ?? '', 'unsigned'),
                comment: $row['comment'] !== '' ? $row['comment'] : null,
                collation: $row['collation'],
                charset: $charset,
                enumValues: $enumValues,
            );
        }
        
        return $columns;
    }

    /**
     * Get table indexes
     *
     * @param string $table
     * @param string $database
     * @return array<string, IndexMetadata>
     */
    private function getIndexes(string $table, string $database): array
    {
        $query = "
            SELECT 
                INDEX_NAME as name,
                COLUMN_NAME as column_name,
                NON_UNIQUE as non_unique,
                INDEX_TYPE as type,
                SEQ_IN_INDEX as seq
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            ORDER BY INDEX_NAME, SEQ_IN_INDEX
        ";
        
        $rows = $this->connection->fetchAll($query, [$database, $table]);
        
        $indexes = [];
        
        foreach ($rows as $row) {
            $indexName = $row['name'];
            
            if (!isset($indexes[$indexName])) {
                $indexes[$indexName] = [
                    'name' => $indexName,
                    'columns' => [],
                    'unique' => $row['non_unique'] == 0,
                    'primary' => $indexName === 'PRIMARY',
                    'type' => $row['type'],
                ];
            }
            
            $indexes[$indexName]['columns'][] = $row['column_name'];
        }
        
        // Convert to IndexMetadata objects
        return array_map(
            fn($index) => new IndexMetadata(
                name: $index['name'],
                columns: $index['columns'],
                unique: $index['unique'],
                primary: $index['primary'],
                type: $index['type'],
            ),
            $indexes
        );
    }

    /**
     * Get table foreign keys
     *
     * @param string $table
     * @param string $database
     * @return array<string, ForeignKeyMetadata>
     */
    private function getForeignKeys(string $table, string $database): array
    {
        $query = "
            SELECT 
                kcu.CONSTRAINT_NAME as name,
                kcu.COLUMN_NAME as column_name,
                kcu.REFERENCED_TABLE_NAME as referenced_table,
                kcu.REFERENCED_COLUMN_NAME as referenced_column,
                rc.UPDATE_RULE as on_update,
                rc.DELETE_RULE as on_delete
            FROM information_schema.KEY_COLUMN_USAGE kcu
            INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
            WHERE kcu.TABLE_SCHEMA = ?
            AND kcu.TABLE_NAME = ?
            AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY kcu.CONSTRAINT_NAME
        ";
        
        $rows = $this->connection->fetchAll($query, [$database, $table]);
        
        $foreignKeys = [];
        
        foreach ($rows as $row) {
            $foreignKeys[$row['name']] = new ForeignKeyMetadata(
                name: $row['name'],
                column: $row['column_name'],
                referencedTable: $row['referenced_table'],
                referencedColumn: $row['referenced_column'],
                onUpdate: $row['on_update'],
                onDelete: $row['on_delete'],
            );
        }
        
        return $foreignKeys;
    }
}
