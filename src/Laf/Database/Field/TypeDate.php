<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\Date;
use Laf\Util\Locale;

/**
 * DATE. Canonical value is a 'Y-m-d' string.
 *
 * Accepted input: 'Y-m-d', a datetime string (time part dropped), any
 * DateTimeInterface, and the locale display format (Settings 'locale.date.format').
 */
class TypeDate extends AbstractFieldType
{
    public function getTypeName(): string
    {
        return 'date';
    }

    /** @return string[] */
    protected function acceptedFormats(): array
    {
        return array_unique([
            Locale::ISO_DATE,
            'Y-m-d H:i:s',
            'Y-m-d H:i:s.u',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:sP',
            'Y-m-d\TH:i:s.uP',
            'Y-m-d H:i:sP',
            'Y-m-d H:i',
            'Ymd',
            Locale::dateFormat(),
        ]);
    }

    protected function convert(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(Locale::ISO_DATE);
        }
        $dt = Locale::parseDate($this->toStringOrFail($value), $this->acceptedFormats());
        if ($dt === null) {
            throw $this->invalid($value, 'not a date');
        }
        return $dt->format(Locale::ISO_DATE);
    }

    public function getPdoType()
    {
        return \PDO::PARAM_STR;
    }

    protected function createFormElement(Field $field)
    {
        $formElement = new Date();
        $formElement->setField($field);
        return $formElement;
    }
}
