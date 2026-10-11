<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\Checkbox;

/**
 * BOOLEAN / tinyint(1) / bit(1).
 *
 * Canonical value is a PHP bool. Accepted spellings: true/false, 1/0, "1"/"0",
 * "true"/"false", "yes"/"no", "on"/"off", "y"/"n", "t"/"f" (PostgreSQL text form).
 *
 * The empty string means "unchecked" (an HTML checkbox that is not ticked sends
 * nothing), so '' normalises to false; only null stays null.
 *
 * Bound as PDO::PARAM_INT 0/1: MySQL stores it in tinyint directly and
 * PostgreSQL casts the text form "0"/"1" to boolean, smallint or integer, so the
 * same code works whichever column type the schema uses.
 */
class TypeBool extends AbstractFieldType
{
    private const TRUE_WORDS = ['1', 'true', 'yes', 'on', 'y', 't'];
    private const FALSE_WORDS = ['0', 'false', 'no', 'off', 'n', 'f', ''];

    public function getTypeName(): string
    {
        return 'bool';
    }

    public function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        return $this->convert($value);
    }

    protected function convert(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        $s = strtolower(trim($this->toStringOrFail($value)));
        if (in_array($s, self::TRUE_WORDS, true)) {
            return true;
        }
        if (in_array($s, self::FALSE_WORDS, true)) {
            return false;
        }
        throw $this->invalid($value, 'not a boolean');
    }

    public function getPdoType()
    {
        return \PDO::PARAM_BOOL;
    }

    public function toDb(mixed $value): array
    {
        if ($value === null) {
            return [null, \PDO::PARAM_NULL];
        }
        return [$this->convert($value) ? 1 : 0, \PDO::PARAM_INT];
    }

    protected function createFormElement(Field $field)
    {
        $formElement = new Checkbox();
        $formElement->setField($field);
        return $formElement;
    }
}
