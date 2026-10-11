<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\Text;

/**
 * CHAR(n) / CHARACTER(n). Same rules as varchar; the DB pads on its side.
 */
class TypeChar extends TypeVarchar
{
    public function getTypeName(): string
    {
        return 'char';
    }

    protected function createFormElement(Field $field)
    {
        $formElement = new Text();
        $formElement->setField($field);
        return $formElement;
    }
}
