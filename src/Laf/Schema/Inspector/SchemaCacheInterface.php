<?php

declare(strict_types=1);

namespace Laf\Schema\Inspector;

use Laf\Schema\Metadata\TableMetadata;

/**
 * Schema Cache Interface
 * 
 * Interface for caching schema metadata
 */
interface SchemaCacheInterface
{
    /**
     * Get cached metadata
     *
     * @param string $key
     * @return TableMetadata|null
     */
    public function get(string $key): ?TableMetadata;

    /**
     * Store metadata in cache
     *
     * @param string $key
     * @param TableMetadata $metadata
     * @param int|null $ttl Time to live in seconds
     * @return void
     */
    public function set(string $key, TableMetadata $metadata, ?int $ttl = null): void;

    /**
     * Check if key exists in cache
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Remove item from cache
     *
     * @param string $key
     * @return void
     */
    public function delete(string $key): void;

    /**
     * Clear all cached metadata
     *
     * @return void
     */
    public function clear(): void;

    /**
     * Clear cache for a specific table
     *
     * @param string $table
     * @return void
     */
    public function clearTable(string $table): void;
}
