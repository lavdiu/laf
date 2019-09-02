<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\Text;

class TypeFloat implements FieldType
{
    /**
     * @param $value
     * @return bool
     */
    public function isValid($value)
    {
        if(is_null($value))
            return true;
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }

    /**
     * @param $value
     * @return float
     */
    public function getValueDbSanitized($value)
    {
        if(is_null($value))
            return null;
        return (float)$value;
    }

    /**
     * @return int
     */
    public function getPdoType()
    {
        return \PDO::PARAM_STR;
    }

    /**
     * @param Field $field
     * @return Text
     */
    public function getFormElement(Field $field)
    {
        $formElement = new Text();
        $formElement->setField($field);
        return $formElement;
    }

    /**
     * Return the field value formatted for the db store
     * @param $value
     * @return null|string
     */
    public function formatForDb($value)
    {
        if(is_null($value))
            return null;
        return floatval($value);
    }
}
