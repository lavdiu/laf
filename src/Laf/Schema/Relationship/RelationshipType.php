<?php

declare(strict_types=1);

namespace Laf\Schema\Relationship;

/**
 * Relationship Type Enum
 * 
 * Defines the types of relationships between tables
 */
enum RelationshipType: string
{
    case ONE_TO_ONE = 'one_to_one';
    case ONE_TO_MANY = 'one_to_many';
    case MANY_TO_ONE = 'many_to_one';
    case MANY_TO_MANY = 'many_to_many';

    /**
     * Get the inverse relationship type
     *
     * @return self
     */
    public function inverse(): self
    {
        return match($this) {
            self::ONE_TO_ONE => self::ONE_TO_ONE,
            self::ONE_TO_MANY => self::MANY_TO_ONE,
            self::MANY_TO_ONE => self::ONE_TO_MANY,
            self::MANY_TO_MANY => self::MANY_TO_MANY,
        };
    }

    /**
     * Check if relationship is singular (returns one record)
     *
     * @return bool
     */
    public function isSingular(): bool
    {
        return match($this) {
            self::ONE_TO_ONE, self::MANY_TO_ONE => true,
            self::ONE_TO_MANY, self::MANY_TO_MANY => false,
        };
    }

    /**
     * Check if relationship is plural (returns multiple records)
     *
     * @return bool
     */
    public function isPlural(): bool
    {
        return !$this->isSingular();
    }

    /**
     * Get the method name prefix for this relationship type
     *
     * @return string
     */
    public function getMethodPrefix(): string
    {
        return match($this) {
            self::ONE_TO_ONE, self::MANY_TO_ONE => 'get',
            self::ONE_TO_MANY, self::MANY_TO_MANY => 'get',
        };
    }

    /**
     * Get the return type hint for this relationship
     *
     * @param string $className
     * @return string
     */
    public function getReturnType(string $className): string
    {
        return match($this) {
            self::ONE_TO_ONE, self::MANY_TO_ONE => "?{$className}",
            self::ONE_TO_MANY, self::MANY_TO_MANY => "array",
        };
    }

    /**
     * Get the PHPDoc return type for this relationship
     *
     * @param string $className
     * @return string
     */
    public function getPhpDocReturnType(string $className): string
    {
        return match($this) {
            self::ONE_TO_ONE, self::MANY_TO_ONE => "{$className}|null",
            self::ONE_TO_MANY, self::MANY_TO_MANY => "{$className}[]",
        };
    }
}
