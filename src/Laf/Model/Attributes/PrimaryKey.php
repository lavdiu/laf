<?php

declare(strict_types=1);

namespace Laf\Model\Attributes;

use Attribute;

/**
 * Primary Key Attribute
 * 
 * Marks the primary key column(s) for a model
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
readonly class PrimaryKey
{
    /**
     * @param string|array<string> $columns Primary key column name(s)
     * @param bool $autoIncrement Whether the primary key is auto-incrementing
     */
    public function __construct(
        public string|array $columns,
        public bool $autoIncrement = true,
    ) {}
}
