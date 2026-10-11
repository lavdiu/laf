<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\Text;
use Laf\Util\Locale;

/**
 * DECIMAL(p,s) / NUMERIC(p,s) / MONEY — exact numbers (prices, VAT, quantities).
 *
 * The canonical value is a numeric *string* ("1234.50"), never a PHP float, so
 * no binary rounding happens between the form, the object and the database.
 * When the scale is known the value is rounded half-up to that many decimals,
 * which is what the database would do on INSERT anyway, but done here so the
 * object holds exactly what will be stored.
 */
class TypeDecimal extends AbstractFieldType
{
    private ?int $precision;
    private ?int $scale;

    public function __construct(?int $precision = null, ?int $scale = null)
    {
        $this->precision = $precision;
        $this->scale = $scale;
    }

    public function getTypeName(): string
    {
        return 'decimal';
    }

    public function getPrecision(): ?int
    {
        return $this->precision;
    }

    public function getScale(): ?int
    {
        return $this->scale;
    }

    protected function convert(mixed $value): mixed
    {
        if (is_int($value)) {
            $canonical = (string)$value;
        } else if (is_float($value)) {
            if (!is_finite($value)) {
                throw $this->invalid($value, 'not a finite number');
            }
            $canonical = rtrim(rtrim(sprintf('%.' . ($this->scale ?? 14) . 'F', $value), '0'), '.');
            if ($canonical === '' || $canonical === '-') {
                $canonical = '0';
            }
        } else {
            $canonical = Locale::parseNumber($this->toStringOrFail($value));
            if ($canonical === null) {
                throw $this->invalid($value, 'not a number');
            }
        }
        return $this->scale === null ? $canonical : self::round($canonical, $this->scale);
    }

    /** Round half away from zero to $scale decimals using string arithmetic. */
    public static function round(string $number, int $scale): string
    {
        if (function_exists('bcadd')) {
            $half = ($number[0] === '-' ? '-' : '') . '0.' . str_repeat('0', $scale) . '5';
            return bcadd($number, $half, $scale);
        }
        return number_format((float)$number, $scale, '.', '');
    }

    protected function compareCanonical(mixed $a, mixed $b): bool
    {
        if (function_exists('bccomp')) {
            return bccomp((string)$a, (string)$b, max($this->scale ?? 14, 14)) === 0;
        }
        return abs((float)$a - (float)$b) < 1e-9;
    }

    public function getPdoType()
    {
        return \PDO::PARAM_STR;
    }

    public function validate(mixed $value, ?Field $field = null): array
    {
        if ($value === null) {
            return [];
        }
        $label = $field ? ($field->getLabel() ?: $field->getName()) : 'Value';
        $messages = [];
        if ($this->precision !== null) {
            $integerDigits = strlen(ltrim(explode('.', ltrim((string)$value, '-'))[0], '0'));
            $allowed = $this->precision - ($this->scale ?? 0);
            if ($integerDigits > $allowed) {
                $messages[] = sprintf('%s must have at most %d digits before the decimal point', $label, $allowed);
            }
        }
        if ($field !== null) {
            if ($field->getMinValue() !== null && (float)$value < (float)$field->getMinValue()) {
                $messages[] = sprintf('%s must be at least %s', $label, $field->getMinValue());
            }
            if ($field->getMaxValue() !== null && (float)$value > (float)$field->getMaxValue()) {
                $messages[] = sprintf('%s must be at most %s', $label, $field->getMaxValue());
            }
        }
        return $messages;
    }

    public function getInputAttributes(?Field $field = null): array
    {
        $attributes = ['inputmode' => 'decimal'];
        $attributes['step'] = $this->scale === null ? 'any' : ($this->scale === 0 ? '1' : '0.' . str_repeat('0', $this->scale - 1) . '1');
        return $attributes;
    }

    protected function createFormElement(Field $field)
    {
        $formElement = new Text();
        $formElement->setField($field);
        return $formElement;
    }
}
