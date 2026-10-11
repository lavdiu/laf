<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\TextArea;

/**
 * TEXT / TINYTEXT / MEDIUMTEXT / LONGTEXT. Same rules as varchar, rendered as a textarea.
 */
class TypeText extends TypeVarchar
{
    public function getTypeName(): string
    {
        return 'text';
    }

    protected function createFormElement(Field $field)
    {
        $formElement = new TextArea();
        $formElement->setField($field);
        return $formElement;
    }
}
