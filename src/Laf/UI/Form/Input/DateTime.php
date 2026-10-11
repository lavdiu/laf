<?php

namespace Laf\UI\Form\Input;

use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;
use Laf\Util\Locale;

/**
 * Date and time input: text + "date-time-picker" class showing the locale
 * date-time format (seconds kept when not zero), or a native
 * <input type="datetime-local"> (see TemporalInput).
 */
class DateTime extends TemporalInput implements FormElementInterface
{
    protected function pickerClass(): string
    {
        return 'date-time-picker';
    }

    protected function nativeType(): string
    {
        return InputType::DatetimeLocal;
    }

    protected function acceptedFormats(): array
    {
        return [
            Locale::ISO_DATETIME, 'Y-m-d H:i:s.u', 'Y-m-d H:i:sP', 'Y-m-d H:i:s.uP', 'Y-m-d H:i:sO', 'Y-m-d H:i:s.uO',
            'Y-m-d\TH:i:s', 'Y-m-d\TH:i:s.u', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP', 'Y-m-d\TH:i:sO',
            'Y-m-d H:i', 'Y-m-d\TH:i', Locale::ISO_DATE, Locale::dateTimeFormat(),
        ];
    }

    protected function displayFormat(): string
    {
        return Locale::dateTimeFormat();
    }

    protected function nativeFormat(\DateTimeImmutable $dt): string
    {
        return $dt->format($dt->format('s') === '00' ? 'Y-m-d\TH:i' : 'Y-m-d\TH:i:s');
    }

    /**
     * Returns the CSS class unique to the UI component
     * @return string
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }
}
