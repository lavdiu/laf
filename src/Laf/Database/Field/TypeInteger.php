<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\Integer;

/**
 * Whole numbers: tinyint, smallint, mediumint, int, bigint, year, serial.
 *
 * Canonical value is a PHP int. A value outside PHP_INT range (bigint unsigned)
 * stays a digit string so it is never silently truncated.
 * Range checks (per size and signedness) are reported by validate(), not
 * enforced in normalize(), so a wrong-size literal still loads from the DB.
 */
class TypeInteger extends AbstractFieldType
{
    /** @var array<string, array{0: string, 1: string, 2: string}> size => [signed min, signed max, unsigned max] */
    private const RANGES = [
        'tinyint' => ['-128', '127', '255'],
        'smallint' => ['-32768', '32767', '65535'],
        'mediumint' => ['-8388608', '8388607', '16777215'],
        'int' => ['-2147483648', '2147483647', '4294967295'],
        'bigint' => ['-9223372036854775808', '9223372036854775807', '18446744073709551615'],
        'year' => ['1901', '2155', '2155'],
    ];

    private string $size;
    private bool $unsigned;

    public function __construct(string $size = 'int', bool $unsigned = false)
    {
        $size = strtolower($size);
        $this->size = array_key_exists($size, self::RANGES) ? $size : 'int';
        $this->unsigned = $unsigned;
    }

    public function getTypeName(): string
    {
        return 'integer';
    }

    public function getSize(): string
    {
        return $this->size;
    }

    public function isUnsigned(): bool
    {
        return $this->unsigned;
    }

    public function getMinValue(): string
    {
        return $this->unsigned ? '0' : self::RANGES[$this->size][0];
    }

    public function getMaxValue(): string
    {
        return $this->unsigned ? self::RANGES[$this->size][2] : self::RANGES[$this->size][1];
    }

    protected function convert(mixed $value): mixed
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_float($value)) {
            if (!is_finite($value) || floor($value) !== $value) {
                throw $this->invalid($value, 'not a whole number');
            }
            return abs($value) < PHP_INT_MAX ? (int)$value : sprintf('%.0F', $value);
        }
        $s = trim($this->toStringOrFail($value));
        if (!preg_match('/^[+-]?\d+$/', $s)) {
            throw $this->invalid($value, 'not a whole number');
        }
        $s = ltrim($s, '+');
        $negative = str_starts_with($s, '-');
        $digits = ltrim(ltrim($s, '-'), '0');
        if ($digits === '') {
            return 0;
        }
        $canonical = ($negative ? '-' : '') . $digits;
        // keep as string when it does not fit a PHP int (bigint unsigned on 64-bit, anything on 32-bit)
        if (filter_var($canonical, FILTER_VALIDATE_INT) !== false) {
            return (int)$canonical;
        }
        return $canonical;
    }

    protected function compareCanonical(mixed $a, mixed $b): bool
    {
        return (string)$a === (string)$b;
    }

    public function getPdoType()
    {
        return \PDO::PARAM_INT;
    }

    public function toDb(mixed $value): array
    {
        if ($value === null) {
            return [null, \PDO::PARAM_NULL];
        }
        $value = $this->normalize($value);
        if ($value === null) {
            return [null, \PDO::PARAM_NULL];
        }
        // out-of-range digit strings must travel as strings; PARAM_INT would wrap them
        if (is_string($value)) {
            return [$value, \PDO::PARAM_STR];
        }
        return [$value, \PDO::PARAM_INT];
    }

    public function validate(mixed $value, ?Field $field = null): array
    {
        if ($value === null) {
            return [];
        }
        $v = (string)$value;
        $label = $field ? ($field->getLabel() ?: $field->getName()) : 'Value';
        $messages = [];
        if ($this->compareNumeric($v, $this->getMinValue()) < 0) {
            $messages[] = sprintf('%s must be at least %s', $label, $this->getMinValue());
        }
        if ($this->compareNumeric($v, $this->getMaxValue()) > 0) {
            $messages[] = sprintf('%s must be at most %s', $label, $this->getMaxValue());
        }
        if ($field !== null) {
            if ($field->getMinValue() !== null && $this->compareNumeric($v, (string)$field->getMinValue()) < 0) {
                $messages[] = sprintf('%s must be at least %s', $label, $field->getMinValue());
            }
            if ($field->getMaxValue() !== null && $this->compareNumeric($v, (string)$field->getMaxValue()) > 0) {
                $messages[] = sprintf('%s must be at most %s', $label, $field->getMaxValue());
            }
        }
        return $messages;
    }

    public function getInputAttributes(?Field $field = null): array
    {
        $attributes = ['step' => '1', 'inputmode' => 'numeric'];
        if ($this->size !== 'bigint' || $this->unsigned) {
            $attributes['min'] = $this->getMinValue();
            $attributes['max'] = $this->getMaxValue();
        }
        return $attributes;
    }

    protected function createFormElement(Field $field)
    {
        $formElement = new Integer();
        $formElement->setField($field);
        return $formElement;
    }

    /** Compare two integer digit strings of any length: -1, 0, 1 */
    private function compareNumeric(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, 0);
        }
        $na = str_starts_with($a, '-');
        $nb = str_starts_with($b, '-');
        if ($na !== $nb) {
            return $na ? -1 : 1;
        }
        $da = ltrim(ltrim($a, '-+'), '0') ?: '0';
        $db = ltrim(ltrim($b, '-+'), '0') ?: '0';
        $cmp = strlen($da) <=> strlen($db) ?: strcmp($da, $db);
        return $na ? -$cmp : $cmp;
    }
}
