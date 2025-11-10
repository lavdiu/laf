<?php

declare(strict_types=1);

namespace Laf\Model\Attributes;

use Attribute;

/**
 * Unique Attribute
 * 
 * Marks a property or combination of properties as unique
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
readonly class Unique
{
    /**
     * @param string|array<string>|null $columns Column name(s) for composite unique constraints
     * @param string|null $name Constraint name
     */
    public function __construct(
        public string|array|null $columns = null,
        public ?string $name = null,
    ) {}
}
