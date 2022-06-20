<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\File;
use Laf\UI\Form\Input\Hidden;
use Laf\UI\Form\Input\Integer;
use Laf\UI\Form\Input\Select;
use Laf\UI\Form\Input\Text;

class TypeInteger implements FieldType
{
    /**
     * @param $value
     * @return bool
     */
    public function isValid($value)
    {
        if (mb_strlen($value??'') == 0) {
            return true;
        }
        if (is_null($value))
            return true;
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * @param $value
     * @return int
     */
    public function getValueDbSanitized($value)
    {
        if (mb_strlen($value??'') == 0) {
            return null;
        }
        if (is_null($value))
            return null;
        return (int)$value;
    }

    /**
     * @return int
     */
    public function getPdoType()
    {
        return \PDO::PARAM_INT;
    }

    /**
     * @param Field $field
     * @return Text
     */
    public function getFormElement(Field $field)
    {
        if ($field->isForeignKey()) {
            if ($field->isDocumentField())
                $formElement = new File();
            else
                $formElement = new Select();
        } else if ($field->isPrimaryKey())
            if ($field->isAutoIncrement())
                $formElement = new Hidden();
            else
                $formElement = new Integer();
        else
            $formElement = new Integer();
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
        if (!is_numeric($value))
            return null;
        return (int)$value;
    }
}
