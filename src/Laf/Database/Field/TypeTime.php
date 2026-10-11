<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\Time;
use Laf\Util\Locale;

/**
 * TIME. Canonical value is 'H:i:s' (plus '.u' when microseconds are present).
 *
 * Accepted input: 'H:i:s', 'H:i', fractional seconds, 12-hour forms with
 * AM/PM, any DateTimeInterface, and the locale display format
 * (Settings 'locale.time.format').
 */
class TypeTime extends AbstractFieldType
{
    public function getTypeName(): string
    {
        return 'time';
    }

    /** @return string[] */
    protected function acceptedFormats(): array
    {
        // most specific first: PHP's parser lets 'g:i A' swallow ":07" from "9:05:07 am"
        return array_unique([
            Locale::ISO_TIME,
            'H:i:s.u',
            'H:i:sP',
            'g:i:s A',
            'g:i:s a',
            'H:i:s A',
            'H:i:s a',
            'H:i',
            'g:i A',
            'g:i a',
            'H:i A',
            'H:i a',
            Locale::timeFormat(),
            Locale::formatWithSeconds(Locale::timeFormat()),
        ]);
    }

    protected function convert(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $this->format($value);
        }
        $dt = Locale::parseDate($this->toStringOrFail($value), $this->acceptedFormats());
        if ($dt === null) {
            throw $this->invalid($value, 'not a time');
        }
        return $this->format($dt);
    }

    private function format(\DateTimeInterface $dt): string
    {
        $out = $dt->format(Locale::ISO_TIME);
        if ((int)$dt->format('u') !== 0) {
            $out .= '.' . rtrim($dt->format('u'), '0');
        }
        return $out;
    }

    public function getPdoType()
    {
        return \PDO::PARAM_STR;
    }

    protected function createFormElement(Field $field)
    {
        $formElement = new Time();
        $formElement->setField($field);
        return $formElement;
    }
}
