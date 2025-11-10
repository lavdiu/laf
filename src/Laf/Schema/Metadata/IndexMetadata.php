<?php

declare(strict_types=1);

namespace Laf\Schema\Metadata;

/**
 * Index Metadata
 * 
 * Represents metadata for a database index
 */
readonly class IndexMetadata
{
    /**
     * @param string $name Index name
     * @param array<string> $columns Column names in the index
     * @param bool $unique Whether index is unique
     * @param bool $primary Whether index is primary key
     * @param string|null $type Index type (BTREE, HASH, etc.)
     * @param string|null $comment Index comment
     */
    public function __construct(
        public string $name,
        public array $columns,
        public bool $unique = false,
        public bool $primary = false,
        public ?string $type = null,
        public ?string $comment = null,
    ) {}

    /**
     * Check if this is a composite index
     *
     * @return bool
     */
    public function isComposite(): bool
    {
        return count($this->columns) > 1;
    }

    /**
     * Get the first column name
     *
     * @return string|null
     */
    public function getFirstColumn(): ?string
    {
        return $this->columns[0] ?? null;
    }

    /**
     * Check if index contains a specific column
     *
     * @param string $column
     * @return bool
     */
    public function hasColumn(string $column): bool
    {
        return in_array($column, $this->columns, true);
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
            'columns' => $this->columns,
            'unique' => $this->unique,
            'primary' => $this->primary,
            'type' => $this->type,
            'comment' => $this->comment,
            'is_composite' => $this->isComposite(),
        ];
    }
}
