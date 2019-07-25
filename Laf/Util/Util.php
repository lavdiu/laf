<?php

namespace Laf\Util;

class Util
{
    public static function isJSON($string)
    {
        return (is_string($string) && is_array(json_decode($string, true)) && (json_last_error() === JSON_ERROR_NONE)) ? true : false;
    }

    /**
     * Convert table name to class name
     * Example: school_instructor -> SchoolInstructor
     * @param string $name
     * @return string
     */
    public static function tableNameToClassName($name)
    {
        return str_replace('_', '', ucwords($name, '_'));
    }

    /**
     * Convert table field name to label
     * Example: instructor_name -> Instructor Name
     * @param string $name
     * @return string
     */
    public static function tableFieldNameToLabel($name)
    {
        $name = str_replace('_id', '', $name);
        return str_replace('_', ' ', ucwords($name, '_'));
    }

    /**
     * Convert table field name to label
     * Example: instructor_name -> InstructorName
     * @param string $name
     * @return string
     */
    public static function tableFieldNameToMethodName($name)
    {
        return str_replace('_', '', ucwords($name, '_'));
    }

    /**
     * Formats date from F d, Y to Y-m-d
     * @param $date
     * @return string
     */
    public static function formatDateForDb($date)
    {
        $dt = \DateTime::createFromFormat('F d, Y', $date);
        if ($dt !== null) {
            return $dt->format('Y-m-d');
        } else {
            return null;
        }
    }

    public static function uuid(){
        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}