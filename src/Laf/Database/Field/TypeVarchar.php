<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\Text;
use Laf\Util\Util;

/**
 * VARCHAR / CHARACTER VARYING and short text-like columns (inet, interval, …).
 *
 * Values are stored exactly as typed (trimmed). HTML escaping happens at
 * output time in the UI layer, never here: encoding at storage time corrupts
 * data for every non-HTML consumer (exports, API, LIKE searches).
 */
class TypeVarchar extends AbstractFieldType
{
    public function getTypeName(): string
    {
        return 'varchar';
    }

    protected function convert(mixed $value): mixed
    {
        return trim($this->toStringOrFail($value));
    }

    public function getPdoType()
    {
        return \PDO::PARAM_STR;
    }

    protected function createFormElement(Field $field)
    {
        $formElement = new Text();
        $formElement->setField($field);
        return $formElement;
    }

    public function validate(mixed $value, ?Field $field = null): array
    {
        if ($value === null || $field === null) {
            return [];
        }
        $max = $field->getMaxLength();
        if (is_int($max) && $max > 0 && mb_strlen((string)$value) > $max) {
            return [sprintf('%s must be at most %d characters', $field->getLabel() ?: $field->getName(), $max)];
        }
        return [];
    }

    public function generatePrimaryKeyValue(): ?string
    {
        return Util::uuid();
    }
}
