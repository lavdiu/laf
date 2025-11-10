<?php

declare(strict_types=1);

namespace Laf\Schema\Metadata;

/**
 * Column Metadata
 * 
 * Represents metadata for a database column
 */
readonly class ColumnMetadata
{
    public function __construct(
        public string $name,
        public string $type,
        public ?int $length = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public bool $nullable = true,
        public mixed $default = null,
        public bool $autoIncrement = false,
        public bool $unsigned = false,
        public ?string $comment = null,
        public ?string $collation = null,
        public ?string $charset = null,
        public array $enumValues = [],
    ) {}

    /**
     * Check if column is numeric type
     *
     * @return bool
     */
    public function isNumeric(): bool
    {
        return in_array(strtolower($this->type), [
            'int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint',
            'decimal', 'numeric', 'float', 'double', 'real'
        ]);
    }

    /**
     * Check if column is integer type
     *
     * @return bool
     */
    public function isInteger(): bool
    {
        return in_array(strtolower($this->type), [
            'int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint'
        ]);
    }

    /**
     * Check if column is string type
     *
     * @return bool
     */
    public function isString(): bool
    {
        return in_array(strtolower($this->type), [
            'char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext',
            'string', 'character varying'
        ]);
    }

    /**
     * Check if column is date/time type
     *
     * @return bool
     */
    public function isDateTime(): bool
    {
        return in_array(strtolower($this->type), [
            'date', 'datetime', 'timestamp', 'time', 'year'
        ]);
    }

    /**
     * Check if column is date type
     *
     * @return bool
     */
    public function isDate(): bool
    {
        return strtolower($this->type) === 'date';
    }

    /**
     * Check if column is time type
     *
     * @return bool
     */
    public function isTime(): bool
    {
        return strtolower($this->type) === 'time';
    }

    /**
     * Check if column is boolean type
     *
     * @return bool
     */
    public function isBoolean(): bool
    {
        return in_array(strtolower($this->type), ['bool', 'boolean', 'tinyint']) 
            && $this->length === 1;
    }

    /**
     * Check if column is binary type
     *
     * @return bool
     */
    public function isBinary(): bool
    {
        return in_array(strtolower($this->type), [
            'blob', 'tinyblob', 'mediumblob', 'longblob', 'binary', 'varbinary', 'bytea'
        ]);
    }

    /**
     * Check if column is JSON type
     *
     * @return bool
     */
    public function isJson(): bool
    {
        return in_array(strtolower($this->type), ['json', 'jsonb']);
    }

    /**
     * Check if column is enum type
     *
     * @return bool
     */
    public function isEnum(): bool
    {
        return strtolower($this->type) === 'enum' && !empty($this->enumValues);
    }

    /**
     * Get PHP type hint for this column
     *
     * @return string
     */
    public function getPhpType(): string
    {
        if ($this->isBoolean()) {
            return 'bool';
        }

        if ($this->isInteger()) {
            return 'int';
        }

        if ($this->isNumeric()) {
            return 'float';
        }

        if ($this->isDateTime()) {
            return '\DateTimeInterface';
        }

        if ($this->isJson()) {
            return 'array';
        }

        return 'string';
    }

    /**
     * Get PHP type hint with nullability
     *
     * @return string
     */
    public function getPhpTypeWithNull(): string
    {
        $type = $this->getPhpType();
        return $this->nullable ? "?{$type}" : $type;
    }

    /**
     * Get property name (camelCase)
     *
     * @return string
     */
    public function getPropertyName(): string
    {
        return lcfirst(str_replace('_', '', ucwords($this->name, '_')));
    }

    /**
     * Get getter method name
     *
     * @return string
     */
    public function getGetterName(): string
    {
        $property = ucfirst($this->getPropertyName());
        return ($this->isBoolean() ? 'is' : 'get') . $property;
    }

    /**
     * Get setter method name
     *
     * @return string
     */
    public function getSetterName(): string
    {
        return 'set' . ucfirst($this->getPropertyName());
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
            'type' => $this->type,
            'length' => $this->length,
            'precision' => $this->precision,
            'scale' => $this->scale,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'auto_increment' => $this->autoIncrement,
            'unsigned' => $this->unsigned,
            'comment' => $this->comment,
            'collation' => $this->collation,
            'charset' => $this->charset,
            'enum_values' => $this->enumValues,
            'php_type' => $this->getPhpType(),
            'property_name' => $this->getPropertyName(),
        ];
    }
}
