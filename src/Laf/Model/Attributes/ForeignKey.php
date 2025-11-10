<?php

declare(strict_types=1);

namespace Laf\Model\Attributes;

use Attribute;

/**
 * Foreign Key Attribute
 * 
 * Marks a property as a foreign key reference
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class ForeignKey
{
    public function __construct(
        public string $table,
        public string $column,
        public string $onUpdate = 'RESTRICT',
        public string $onDelete = 'RESTRICT',
    ) {}
}
