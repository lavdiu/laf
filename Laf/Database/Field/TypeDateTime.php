<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\DateTime;


class TypeDateTime implements FieldType
{
    /**
     * @param $value
     * @return bool
     */
    public function isValid($value)
    {
        if (is_null($value))
            return true;
        $f = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        $valid = \DateTime::getLastErrors();
        return ($valid['warning_count'] == 0 and $valid['error_count'] == 0);
    }

    /**
     * @param $value
     * @return null|string
     */
    public function getValueDbSanitized($value)
    {
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        if ($dt === false) return null;
        return $dt->format('Y-m-d H:i:s');
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
     * @return DateTime
     */
    public function getFormElement(Field $field)
    {
        $formElement = new DateTime();
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
        $dt = \DateTime::createFromFormat('Y-m-d H:i', $value);
        if ($dt === false) return null;
        return $dt->format('Y-m-d H:i:s');
    }
}
