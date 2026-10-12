<?php

namespace Laf\Exception;

/**
 * A value cannot be represented by a field's type (FieldType::normalize(), Field::setValue()):
 * "abc" for an integer column, "2024-02-30" for a date, an unknown option for an enum.
 */
class InvalidValueException extends \Exception
{
}
