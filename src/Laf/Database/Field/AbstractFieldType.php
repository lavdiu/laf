<?php

namespace Laf\Database\Field;

use Laf\Exception\InvalidValueException;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\Input\File;
use Laf\UI\Form\Input\Hidden;
use Laf\UI\Form\Input\Select;

/**
 * Shared behaviour for all column types.
 *
 * Subclasses implement convert() for a single non-empty value and get the whole
 * contract (typed and legacy) for free. Override normalize(), fromDb(), toDb()
 * or equals() only when the type really behaves differently (bool, blob, json).
 */
abstract class AbstractFieldType implements FieldType
{
    /**
     * Convert one non-empty value to the canonical PHP representation.
     * @throws InvalidValueException
     */
    abstract protected function convert(mixed $value): mixed;

    abstract public function getTypeName(): string;

    abstract public function getPdoType();

    /** The input this type naturally renders as (Text, TextArea, Checkbox, …). */
    abstract protected function createFormElement(Field $field);

    /**
     * Input component for a field of this type.
     *
     * The column's role wins over its type: a foreign key is a Select (or a
     * File upload when it points at the document table) whatever its type, and
     * an auto-increment primary key is Hidden. Everything else gets the type's
     * natural input from createFormElement().
     */
    public function getFormElement(Field $field)
    {
        $hasTable = $field->hasTable();
        if ($hasTable && $field->isForeignKey()) {
            $element = $field->isDocumentField() ? new File() : new Select();
        } else if ($hasTable && $field->getTable()->getPrimaryKey() !== null && $field->isPrimaryKey() && $field->isAutoIncrement()) {
            $element = new Hidden();
        } else {
            $element = $this->createFormElement($field);
        }
        if ($element instanceof FormElementInterface && $element->getField() === null) {
            $element->setField($field);
        }
        return $element;
    }

    /* ───────────── typed contract ───────────── */

    public function normalize(mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return null;
        }
        return $this->convert($value);
    }

    public function fromInput(?string $input): mixed
    {
        return $this->normalize($input === null ? null : trim($input));
    }

    public function fromDb(mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }
        try {
            return $this->convert($raw);
        } catch (InvalidValueException $ex) {
            return $raw;
        }
    }

    public function toDb(mixed $value): array
    {
        if ($value === null) {
            return [null, \PDO::PARAM_NULL];
        }
        return [$value, $this->getPdoType()];
    }

    public function equals(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }
        try {
            $a = $this->normalize($a);
            $b = $this->normalize($b);
        } catch (InvalidValueException $ex) {
            return $a == $b;
        }
        if ($a === null || $b === null) {
            return $a === $b;
        }
        return $this->compareCanonical($a, $b);
    }

    /** Compare two already-canonical, non-null values. Default: strict. */
    protected function compareCanonical(mixed $a, mixed $b): bool
    {
        return $a === $b;
    }

    public function validate(mixed $value, ?Field $field = null): array
    {
        return [];
    }

    public function getInputAttributes(?Field $field = null): array
    {
        return [];
    }

    public function generatePrimaryKeyValue(): ?string
    {
        return null;
    }

    /* ───────────── legacy contract ───────────── */

    public function isValid($value)
    {
        try {
            $this->normalize($value);
            return true;
        } catch (InvalidValueException $ex) {
            return false;
        }
    }

    public function getValueDbSanitized($value)
    {
        try {
            return $this->normalize($value);
        } catch (InvalidValueException $ex) {
            return null;
        }
    }

    public function formatForDb(?string $value)
    {
        try {
            return $this->fromInput($value);
        } catch (InvalidValueException $ex) {
            return null;
        }
    }

    /* ───────────── helpers ───────────── */

    protected function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        return false;
    }

    /**
     * Build the exception for an unconvertible value; callers `throw $this->invalid(...)`.
     * (A `never` return type would be cleaner but needs PHP 8.1; composer allows 8.0.)
     */
    protected function invalid(mixed $value, ?string $reason = null): InvalidValueException
    {
        if (is_scalar($value)) {
            $shown = (string)$value;
            if (mb_strlen($shown) > 60) {
                $shown = mb_substr($shown, 0, 57) . '...';
            }
        } else {
            $shown = gettype($value);
        }
        return new InvalidValueException(sprintf(
            "Invalid value '%s' for type %s%s",
            $shown,
            $this->getTypeName(),
            $reason ? ' (' . $reason . ')' : ''
        ));
    }

    /** Scalars and Stringable objects become strings; anything else is rejected. */
    protected function toStringOrFail(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if ($value instanceof \Stringable) {
            return (string)$value;
        }
        throw $this->invalid($value);
    }
}
