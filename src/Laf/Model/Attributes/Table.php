<?php

declare(strict_types=1);

namespace Laf\Model\Attributes;

use Attribute;

/**
 * Table Attribute
 * 
 * Marks a class as a database table model
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Table
{
    public function __construct(
        public string $name,
        public ?string $connection = null,
    ) {}
}
