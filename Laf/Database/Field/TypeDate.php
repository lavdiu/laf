<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\Date;

class TypeDate implements FieldType
{
    public function isValid($value)
    {
        if (is_null($value))
            return true;
        $f = \DateTime::createFromFormat('Y-m-d', $value);
        $valid = \DateTime::getLastErrors();
        return ($valid['warning_count'] == 0 and $valid['error_count'] == 0);
    }

    public function getValueDbSanitized($value)
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        if ($dt === false) return null;
        return $dt->format('Y-m-d');
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
     * @return Date
     */
    public function getFormElement(Field $field)
    {
        $formElement = new Date();
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
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        if ($dt === false) return null;
        return $dt->format('Y-m-d');
    }
}
