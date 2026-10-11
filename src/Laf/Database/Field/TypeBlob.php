<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\Text;

/**
 * BLOB / BINARY / VARBINARY / BYTEA — opaque bytes.
 *
 * Nothing is trimmed or validated. PostgreSQL's pdo_pgsql returns bytea as a
 * stream resource; fromDb() reads it into a string so the object always holds bytes.
 * Bound as PDO::PARAM_LOB on both engines.
 */
class TypeBlob extends AbstractFieldType
{
    public function getTypeName(): string
    {
        return 'blob';
    }

    public function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        return $this->convert($value);
    }

    protected function convert(mixed $value): mixed
    {
        if (is_resource($value)) {
            $contents = stream_get_contents($value);
            if ($contents === false) {
                throw $this->invalid($value, 'unreadable stream');
            }
            return $contents;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string)$value;
        }
        throw $this->invalid($value);
    }

    public function getPdoType()
    {
        return \PDO::PARAM_LOB;
    }

    protected function createFormElement(Field $field)
    {
        $formElement = new Text();
        $formElement->setField($field);
        return $formElement;
    }
}
