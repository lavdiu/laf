<?php

namespace Laf\Database\Field;

interface FieldType
{
    const TYPE_NUMERIC = 'decimal';
    const TYPE_FLOAT = 'float';
    const TYPE_DOUBLE = 'double';
    const TYPE_REAL = 'real';
    const TYPE_INTEGER = 'int';
    const TYPE_INTEGER2 = 'integer';
    const TYPE_BIG_INTEGER = 'bigint';
    const TYPE_SMALL_INTEGER = 'smallint';
    const TYPE_TINY_INTEGER = 'tinyint';
    const TYPE_VARCHAR = 'varchar';
    const TYPE_VARCHAR2 = 'character varying';
    const TYPE_CHAR = 'char';
    const TYPE_TEXT = 'text';
    const TYPE_JSON = 'json';
    const TYPE_DATE = 'date';
    const TYPE_DATETIME = 'datetime';
    const TYPE_DATETIME2 = 'timestamp with time zone';
    const TYPE_DATETIME3 = 'timestamp without time zone';
    const TYPE_TIME = 'time';
    const TYPE_TIME2 = 'time without time zone';
    const TYPE_TIME3 = 'time with time zone';
    const TYPE_BLOB = 'blob';
    const TYPE_BOOL = 'bool';

    public function getValueDbSanitized($value);

    public function isValid($value);

    public function getPdoType();

    public function getFormElement(Field $field);

    public function formatForDb(?string $value);
}