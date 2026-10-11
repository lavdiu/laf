<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\TextArea;

/**
 * JSON / JSONB. Canonical value is the JSON text itself (not decoded), so what
 * is stored is byte-for-byte what was validated. Arrays and plain objects are
 * encoded on the way in as a convenience for application code.
 */
class TypeJson extends AbstractFieldType
{
    public function getTypeName(): string
    {
        return 'json';
    }

    protected function convert(mixed $value): mixed
    {
        if (is_array($value) || ($value instanceof \JsonSerializable) || ($value instanceof \stdClass)) {
            try {
                return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } catch (\JsonException $ex) {
                throw $this->invalid($value, $ex->getMessage());
            }
        }
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return json_encode($value);
        }
        $s = trim($this->toStringOrFail($value));
        try {
            json_decode($s, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $ex) {
            throw $this->invalid($value, 'not valid JSON: ' . $ex->getMessage());
        }
        return $s;
    }

    protected function compareCanonical(mixed $a, mixed $b): bool
    {
        if ($a === $b) {
            return true;
        }
        try {
            // == on arrays ignores key order, which is what JSON object equality means
            return json_decode($a, true, 512, JSON_THROW_ON_ERROR) == json_decode($b, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $ex) {
            return false;
        }
    }

    /** Decoded view of a canonical value, for application code. */
    public function decode(?string $json, bool $assoc = true): mixed
    {
        if ($json === null) {
            return null;
        }
        return json_decode($json, $assoc, 512, JSON_THROW_ON_ERROR);
    }

    public function getPdoType()
    {
        return \PDO::PARAM_STR;
    }

    protected function createFormElement(Field $field)
    {
        $formElement = new TextArea();
        $formElement->setField($field);
        return $formElement;
    }
}
