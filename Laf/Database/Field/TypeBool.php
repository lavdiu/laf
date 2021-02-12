<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\Checkbox;
use Laf\UI\Form\Input\Text;

class TypeBool implements FieldType
{
    /**
     * @param $value
     * @return bool
     */
    public function isValid($value)
    {
        if (is_bool($value))
            return true;
        return filter_var($value, FILTER_VALIDATE_BOOL) !== false;
    }

    /**
     * @param $value
     * @return float
     */
    public function getValueDbSanitized($value)
    {
        if (is_null($value))
            return null;
        return (bool)$value;
    }

    /**
     * @return int
     */
    public function getPdoType()
    {
        return \PDO::PARAM_BOOL;
    }

    /**
     * @param Field $field
     * @return Text
     */
    public function getFormElement(Field $field)
    {
        $formElement = new Checkbox();
        $formElement->setField($field);
        return $formElement;
    }

    /**
     * Return the field value formatted for the db store
     * @param bool $value
     * @return null|string
     */
    public function formatForDb(?string $value)
    {
        if (is_null($value))
            return null;
        return (bool)$value;
    }
}
