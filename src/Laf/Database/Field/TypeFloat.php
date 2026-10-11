<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\Text;
use Laf\Util\Locale;

/**
 * FLOAT / DOUBLE / REAL / DOUBLE PRECISION — approximate numbers (measurements, ratios).
 * For money and anything that must round-trip exactly use TypeDecimal.
 */
class TypeFloat extends AbstractFieldType
{
    public function getTypeName(): string
    {
        return 'float';
    }

    protected function convert(mixed $value): mixed
    {
        if (is_float($value)) {
            if (!is_finite($value)) {
                throw $this->invalid($value, 'not a finite number');
            }
            return $value;
        }
        if (is_int($value)) {
            return (float)$value;
        }
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }
        $canonical = Locale::parseNumber($this->toStringOrFail($value));
        if ($canonical === null) {
            throw $this->invalid($value, 'not a number');
        }
        return (float)$canonical;
    }

    protected function compareCanonical(mixed $a, mixed $b): bool
    {
        $a = (float)$a;
        $b = (float)$b;
        if ($a === $b) {
            return true;
        }
        $scale = max(abs($a), abs($b), 1.0);
        return abs($a - $b) <= 1e-9 * $scale;
    }

    public function getPdoType()
    {
        return \PDO::PARAM_STR;
    }

    public function toDb(mixed $value): array
    {
        if ($value === null) {
            return [null, \PDO::PARAM_NULL];
        }
        // bind as a locale-independent string so neither PHP's precision ini nor
        // the driver decides how many digits travel to the server
        return [rtrim(rtrim(sprintf('%.15G', (float)$value), '0'), '.'), \PDO::PARAM_STR];
    }

    public function validate(mixed $value, ?Field $field = null): array
    {
        if ($value === null || $field === null) {
            return [];
        }
        $label = $field->getLabel() ?: $field->getName();
        $messages = [];
        if ($field->getMinValue() !== null && (float)$value < (float)$field->getMinValue()) {
            $messages[] = sprintf('%s must be at least %s', $label, $field->getMinValue());
        }
        if ($field->getMaxValue() !== null && (float)$value > (float)$field->getMaxValue()) {
            $messages[] = sprintf('%s must be at most %s', $label, $field->getMaxValue());
        }
        return $messages;
    }

    public function getInputAttributes(?Field $field = null): array
    {
        return ['inputmode' => 'decimal', 'step' => 'any'];
    }

    protected function createFormElement(Field $field)
    {
        $formElement = new Text();
        $formElement->setField($field);
        return $formElement;
    }
}
