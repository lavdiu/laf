<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\Text;

class TypeVarchar implements FieldType
{
    /**
     * @param $value
     * @return bool
     */
    public function isValid($value)
    {
        return true;
    }

    /**
     * @param $value
     * @return string
     */
    public function getValueDbSanitized($value)
    {
        $value = strip_tags($value);
        $value = strip_tags($value);
        return htmlspecialchars($value, ENT_COMPAT);
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
        return (string)$value;
    }
}
