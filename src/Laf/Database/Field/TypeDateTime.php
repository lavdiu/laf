<?php

namespace Laf\Database\Field;

use Laf\UI\Form\Input\DateTime;
use Laf\Util\Locale;

/**
 * DATETIME / TIMESTAMP / TIMESTAMP [WITH|WITHOUT] TIME ZONE.
 *
 * Canonical value is 'Y-m-d H:i:s', extended with '.u' when the value carries
 * non-zero microseconds and with a 'P' offset when the column is time-zone
 * aware and the value carried one. Nothing is ever converted between zones:
 * what the DB gives is what the object holds and sends back.
 *
 * Accepted input: ISO with ' ' or 'T', with or without seconds, fractional
 * seconds, offset; a bare date (midnight); any DateTimeInterface; and the
 * locale display format (Settings 'locale.datetime.format').
 */
class TypeDateTime extends AbstractFieldType
{
    private bool $withTimezone;

    public function __construct(bool $withTimezone = false)
    {
        $this->withTimezone = $withTimezone;
    }

    public function getTypeName(): string
    {
        return 'datetime';
    }

    public function isWithTimezone(): bool
    {
        return $this->withTimezone;
    }

    /** @return string[] */
    protected function acceptedFormats(): array
    {
        return array_unique([
            Locale::ISO_DATETIME,
            'Y-m-d H:i:s.u',
            'Y-m-d H:i:sP',
            'Y-m-d H:i:s.uP',
            'Y-m-d H:i:sO',
            'Y-m-d H:i:s.uO',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:s.u',
            'Y-m-d\TH:i:sP',
            'Y-m-d\TH:i:s.uP',
            'Y-m-d\TH:i:sO',
            'Y-m-d H:i',
            'Y-m-d\TH:i',
            Locale::ISO_DATE,
            Locale::dateTimeFormat(),
        ]);
    }

    protected function convert(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $this->format($value, true);
        }
        $raw = $this->toStringOrFail($value);
        $dt = Locale::parseDate($raw, $this->acceptedFormats());
        if ($dt === null) {
            throw $this->invalid($value, 'not a date and time');
        }
        $hadOffset = (bool)preg_match('/(Z|[+-]\d{2}(:?\d{2})?)$/', trim($raw));
        return $this->format($dt, $hadOffset);
    }

    private function format(\DateTimeInterface $dt, bool $hadOffset): string
    {
        $out = $dt->format(Locale::ISO_DATETIME);
        if ((int)$dt->format('u') !== 0) {
            $out .= '.' . rtrim($dt->format('u'), '0');
        }
        if ($this->withTimezone && $hadOffset) {
            $out .= $dt->format('P');
        }
        return $out;
    }

    protected function compareCanonical(mixed $a, mixed $b): bool
    {
        return $a === $b || new \DateTimeImmutable($a) == new \DateTimeImmutable($b);
    }

    public function getPdoType()
    {
        return \PDO::PARAM_STR;
    }

    protected function createFormElement(Field $field)
    {
        $formElement = new DateTime();
        $formElement->setField($field);
        return $formElement;
    }
}
