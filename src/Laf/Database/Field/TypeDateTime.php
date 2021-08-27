<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\DateTime;
use Laf\Util\Settings;


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
     * @param string $value
     * @return null|string
     */
    public function formatForDb(?string $value)
    {
        $format = 'Y-m-d H:i:s';
        try {
            $format = Settings::get('locale.time.format');
        } catch (\Exception $ex) {
        }
        $dt = \DateTime::createFromFormat($format, $value);
        if ($dt === false) return null;
        return $dt->format('Y-m-d H:i:s');
    }
}
