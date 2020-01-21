<?php

namespace Laf\Database\Field;

use Laf\UI\Form\TextArea;

class TypeJson implements FieldType
{
    /**
     * @param $value
     * @return bool
     */
    public function isValid($value)
    {
        if(is_null($value))
            return true;
        json_decode($value);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * @param $value
     * @return mixed
     */
    public function getValueDbSanitized($value)
    {
        return $value;
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
     * @return TextArea
     */
    public function getFormElement(Field $field)
    {
        $formElement = new TextArea();
        $formElement->setField($field);
        return $formElement;
    }

    /**
     * Return the field value formatted for the db store
     * @param string $value
     * @return null|string
     */
    public function formatForDb(?string $value)
    {
        if(is_null($value))
            return null;
        return $value;
    }
}
