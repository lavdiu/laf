<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\Time;
use Laf\Util\Settings;

class TypeTime implements FieldType
{
    /**
     * @param $value
     * @return bool
     */
    public function isValid($value)
    {
        if (is_null($value))
            return true;

        $format = 'H:i:s';
        try {
            $format = Settings::get('locale.time.format');
        } catch (\Exception $ex) {
        }

        $f = \DateTime::createFromFormat($format, $value);
        $valid = \DateTime::getLastErrors();
        return ($valid['warning_count'] == 0 and $valid['error_count'] == 0);
    }

    /**
     * @param $value
     * @return null|string
     */
    public function getValueDbSanitized($value)
    {
        $format = 'H:i:s';
        $dt = \DateTime::createFromFormat($format, $value);
        if ($dt === false) return null;
        return $dt->format('H:i:s');
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
     * @return Time
     */
    public function getFormElement(Field $field)
    {
        $formElement = new Time();
        $formElement->setField($field);
        return $formElement;
    }

    /**
     * Return the field value formatted for the db store
     * @param null|string $value
     * @return null|string
     */
    public function formatForDb(?string $value)
    {
        $format = 'H:i:s';
        try {
            $format = Settings::get('locale.time.format');
        } catch (\Exception $ex) {
        }
        $dt = \DateTime::createFromFormat($format, $value);
        if ($dt === false) return null;
        return $dt->format('H:i:s');
    }
}
