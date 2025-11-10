<?php

declare(strict_types=1);

namespace Laf\Schema\Inspector;

use Laf\Schema\Metadata\TableMetadata;

/**
 * File-based Schema Cache
 * 
 * Caches schema metadata to files
 */
class FileSchemaCache implements SchemaCacheInterface
{
    private const CACHE_VERSION = 1;

    public function __construct(
        private readonly string $cacheDirectory,
        private readonly int $defaultTtl = 3600,
    ) {
        if (!is_dir($this->cacheDirectory)) {
            mkdir($this->cacheDirectory, 0755, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): ?TableMetadata
    {
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = unserialize(file_get_contents($file));
        
        if (!is_array($data) || !isset($data['version'], $data['expires'], $data['metadata'])) {
            return null;
        }
        
        // Check version
        if ($data['version'] !== self::CACHE_VERSION) {
            $this->delete($key);
            return null;
        }
        
        // Check expiration
        if ($data['expires'] !== null && $data['expires'] < time()) {
            $this->delete($key);
            return null;
        }
        
        return $data['metadata'];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, TableMetadata $metadata, ?int $ttl = null): void
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $expires = $ttl > 0 ? time() + $ttl : null;
        
        $data = [
            'version' => self::CACHE_VERSION,
            'expires' => $expires,
            'metadata' => $metadata,
        ];
        
        $file = $this->getCacheFile($key);
        file_put_contents($file, serialize($data), LOCK_EX);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): void
    {
        $file = $this->getCacheFile($key);
        
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $files = glob($this->cacheDirectory . '/*.cache');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearTable(string $table): void
    {
        $this->delete("table.{$table}");
    }

    /**
     * Get the cache file path for a key
     *
     * @param string $key
     * @return string
     */
    private function getCacheFile(string $key): string
    {
        $hash = md5($key);
        return $this->cacheDirectory . '/' . $hash . '.cache';
    }

    /**
     * Clean up expired cache files
     *
     * @return int Number of files deleted
     */
    public function cleanup(): int
    {
        $files = glob($this->cacheDirectory . '/*.cache');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            
            $data = unserialize(file_get_contents($file));
            
            if (!is_array($data) || !isset($data['expires'])) {
                unlink($file);
                $deleted++;
                continue;
            }
            
            if ($data['expires'] !== null && $data['expires'] < time()) {
                unlink($file);
                $deleted++;
            }
        }
        
        return $deleted;
    }
}
