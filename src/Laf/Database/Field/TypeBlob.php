<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Text;

class TypeBlob implements FieldType
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
        return \PDO::PARAM_LOB;
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
     * @param string $value
     * @return null|string
     */
    public function formatForDb(?string $value)
    {
        return $value;
    }

}
