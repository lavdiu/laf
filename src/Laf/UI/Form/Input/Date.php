<?php

namespace Laf\UI\Form\Input;

use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;
use Laf\Util\Locale;

/**
 * Date input: text + "date-picker" class showing the locale date format,
 * or a native <input type="date"> (see TemporalInput).
 */
class Date extends TemporalInput implements FormElementInterface
{
    protected function pickerClass(): string
    {
        return 'date-picker';
    }

    protected function nativeType(): string
    {
        return InputType::Date;
    }

    protected function acceptedFormats(): array
    {
        return [Locale::ISO_DATE, 'Y-m-d H:i:s', 'Y-m-d H:i:s.u', 'Y-m-d\TH:i:s', Locale::dateFormat()];
    }

    protected function displayFormat(): string
    {
        return Locale::dateFormat();
    }

    protected function nativeFormat(\DateTimeImmutable $dt): string
    {
        return $dt->format(Locale::ISO_DATE);
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
