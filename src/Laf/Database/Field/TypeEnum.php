<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\Select;
use Laf\UI\Form\Input\Text;

/**
 * MySQL ENUM / SET and PostgreSQL enum types.
 *
 * Canonical value is the option label exactly as declared in the schema; a
 * case-insensitive match is corrected to the declared spelling. For SET
 * columns ($multiple = true) the canonical value is the comma-joined list in
 * declaration order with duplicates removed, which is how MySQL stores it.
 */
class TypeEnum extends AbstractFieldType
{
    /** @var string[] */
    private array $options;
    private bool $multiple;

    /**
     * @param string[] $options allowed labels, in declaration order
     * @param bool $multiple true for MySQL SET columns
     */
    public function __construct(array $options = [], bool $multiple = false)
    {
        $this->options = array_values(array_map('strval', $options));
        $this->multiple = $multiple;
    }

    public function getTypeName(): string
    {
        return $this->multiple ? 'set' : 'enum';
    }

    /** @return string[] */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    protected function convert(mixed $value): mixed
    {
        if (is_array($value)) {
            if (!$this->multiple) {
                throw $this->invalid($value, 'single-valued enum');
            }
            $parts = $value;
        } else {
            $s = trim($this->toStringOrFail($value));
            $parts = $this->multiple ? explode(',', $s) : [$s];
        }

        $matched = [];
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part === '' && $this->multiple) {
                continue;
            }
            $label = $this->matchOption($part);
            if ($label === null) {
                throw $this->invalid($part, 'not one of: ' . implode(', ', $this->options));
            }
            $matched[$label] = true;
        }

        if (!$this->multiple) {
            return array_key_first($matched);
        }
        // declaration order, like MySQL SET
        $ordered = array_values(array_filter($this->options, fn($o) => isset($matched[$o])));
        return implode(',', $ordered);
    }

    private function matchOption(string $candidate): ?string
    {
        if (in_array($candidate, $this->options, true)) {
            return $candidate;
        }
        foreach ($this->options as $option) {
            if (mb_strtolower($option) === mb_strtolower($candidate)) {
                return $option;
            }
        }
        return null;
    }

    public function getPdoType()
    {
        return \PDO::PARAM_STR;
    }

    public function getInputAttributes(?Field $field = null): array
    {
        return $this->multiple ? ['placeholder' => implode(',', $this->options)] : [];
    }

    protected function createFormElement(Field $field)
    {
        if ($this->multiple) {
            $formElement = new Text();
            $formElement->setField($field);
            return $formElement;
        }
        $formElement = new Select();
        $formElement->setOptions(array_combine($this->options, $this->options));
        $formElement->setField($field);
        return $formElement;
    }
}
