<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\Text;
use Laf\Util\Util;

/**
 * PostgreSQL uuid, or a MySQL char(36) column opted in through the column comment
 * {"fields":{"setType":"uuid"}} (the generator then emits TypeUuid instead of TypeChar).
 *
 * Canonical value is the lower-case hyphenated form. When used as a primary key
 * a v4 UUID is generated on insert.
 */
class TypeUuid extends AbstractFieldType
{
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public function getTypeName(): string
    {
        return 'uuid';
    }

    protected function convert(mixed $value): mixed
    {
        $s = strtolower(trim($this->toStringOrFail($value)));
        $s = trim($s, '{}');
        if (strlen($s) === 32 && ctype_xdigit($s)) {
            $s = sprintf('%s-%s-%s-%s-%s', substr($s, 0, 8), substr($s, 8, 4), substr($s, 12, 4), substr($s, 16, 4), substr($s, 20));
        }
        if (!preg_match(self::PATTERN, $s)) {
            throw $this->invalid($value, 'not a UUID');
        }
        return $s;
    }

    public function getPdoType()
    {
        return \PDO::PARAM_STR;
    }

    public function generatePrimaryKeyValue(): ?string
    {
        return Util::uuid();
    }

    public function getInputAttributes(?Field $field = null): array
    {
        return [
            'maxlength' => '36',
            'pattern' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
        ];
    }

    protected function createFormElement(Field $field)
    {
        $formElement = new Text();
        $formElement->setField($field);
        return $formElement;
    }
}
