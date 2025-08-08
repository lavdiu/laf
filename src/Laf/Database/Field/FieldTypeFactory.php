<?php

namespace Laf\Database\Field;

class FieldTypeFactory
{
    /**
     * @param string $type
     * @return FieldType
     */
    public static function getClass(string $type)
    {
        switch ($type) {
            case FieldType::TYPE_DATE:
                return new TypeDate();
                break;
            case FieldType::TYPE_BLOB:
                return new TypeBlob();
                break;
            case FieldType::TYPE_TIME:
            case FieldType::TYPE_DATETIME:
                return new TypeTime();
                break;
            case FieldType::TYPE_INTEGER:
            case FieldType::TYPE_BIG_INTEGER:
            case FieldType::TYPE_SMALL_INTEGER:
            case FieldType::TYPE_TINY_INTEGER:
                return new TypeInteger();
                break;
            case FieldType::TYPE_JSON:
                return new TypeJson();
                break;
            case FieldType::TYPE_VARCHAR:
                return new TypeVarchar();
                break;
            case FieldType::TYPE_NUMERIC:
            case FieldType::TYPE_DOUBLE:
            case FieldType::TYPE_FLOAT:
            case FieldType::TYPE_REAL:
                return new TypeFloat();
                break;
            case FieldType::TYPE_TEXT:
            default:
                return new TypeText();
                break;

        }
    }

    /**
     * @param string $type
     * @return string
     */
    public static function getClassLiteral(string $type)
    {
        switch ($type) {
            case FieldType::TYPE_DATE:
                return "new Database\Field\TypeDate()";
                break;
            case FieldType::TYPE_BLOB:
                return "new Database\Field\TypeBlob()";
                break;
            case FieldType::TYPE_DATETIME:
            case FieldType::TYPE_DATETIME2:
            case FieldType::TYPE_DATETIME3:
                return "new Database\Field\TypeDateTime()";
                break;
            case FieldType::TYPE_TIME:
            case FieldType::TYPE_TIME2:
            case FieldType::TYPE_TIME3:
                return "new Database\Field\TypeTime()";
                break;
            case FieldType::TYPE_TINY_INTEGER:
            case FieldType::TYPE_BOOL:
                return "new Database\Field\TypeBool()";
                break;
            case FieldType::TYPE_INTEGER:
            case FieldType::TYPE_BIG_INTEGER:
            case FieldType::TYPE_SMALL_INTEGER:
                return "new Database\Field\TypeInteger()";
                break;
            case FieldType::TYPE_JSON:
                return "new Database\Field\TypeJson()";
                break;
            case FieldType::TYPE_VARCHAR:
            case FieldType::TYPE_VARCHAR2:
                return "new Database\Field\TypeVarchar()";
                break;
            case FieldType::TYPE_NUMERIC:
            case FieldType::TYPE_DOUBLE:
            case FieldType::TYPE_FLOAT:
            case FieldType::TYPE_REAL:
                return "new Database\Field\TypeFloat()";
                break;
            case FieldType::TYPE_TEXT:
            default:
                return "new Database\Field\TypeText()";
                break;
        }
    }

    public static function sanitize(int $fieldType, $value)
    {
        return '';
    }
}
