<?php

namespace Laf\Exception;

/**
 * A foreign-key column received a value that does not exist in the referenced table
 * (BaseObject::setFieldValue(), ForeignKey::isValidValue()). The name predates the
 * "Exception" suffix convention and stays because generated code and the apps catch it.
 */
class InvalidForeignKeyValue extends \Exception
{
}
