<?php

declare(strict_types=1);

namespace Laf\Model\Attributes;

use Attribute;

/**
 * Column Attribute
 * 
 * Maps a property to a database column
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Column
{
    public function __construct(
        public string $name,
        public string $type,
        public ?int $length = null,
        public bool $nullable = true,
        public mixed $default = null,
    ) {}
}
