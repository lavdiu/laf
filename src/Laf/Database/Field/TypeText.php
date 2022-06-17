<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\TextArea;

class TypeText implements FieldType
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
        if (mb_strlen($value) == 0)
            return null;
        return (string)trim($value??'');
    }
}
