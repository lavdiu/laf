<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\Date;
use Laf\Util\Settings;

class TypeDate implements FieldType
{
    public function isValid($value)
    {
        if (is_null($value))
            return true;

        $format = 'Y-m-d';
        $f = \DateTime::createFromFormat($format, $value);
        $valid = \DateTime::getLastErrors();
        return ($valid['warning_count'] == 0 and $valid['error_count'] == 0);
    }

    public function getValueDbSanitized($value)
    {
        $format = 'Y-m-d';
        $dt = \DateTime::createFromFormat($format, $value);
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
     * @param string $value
     * @return null|string
     */
    public function formatForDb(?string $value)
    {
        $format = 'Y-m-d';
        try {
            $format = Settings::get('locale.date.format');
        } catch (\Exception $ex) {
        }
        $dt = \DateTime::createFromFormat($format, $value);
        if ($dt === false) return null;
        return $dt->format('Y-m-d');
    }
}
