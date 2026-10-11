<?php

namespace Laf\UI\Form\Input;

use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;
use Laf\Util\Locale;

/**
 * Time input: text + "time-picker" class showing the locale time format
 * (seconds kept when not zero), or a native <input type="time"> (see TemporalInput).
 */
class Time extends TemporalInput implements FormElementInterface
{
    protected function pickerClass(): string
    {
        return 'time-picker';
    }

    protected function nativeType(): string
    {
        return InputType::Time;
    }

    protected function acceptedFormats(): array
    {
        return [Locale::ISO_TIME, 'H:i:s.u', 'H:i', 'g:i:s A', 'g:i:s a', 'g:i A', 'g:i a', 'H:i A', 'H:i a', Locale::timeFormat()];
    }

    protected function displayFormat(): string
    {
        return Locale::timeFormat();
    }

    protected function nativeFormat(\DateTimeImmutable $dt): string
    {
        return $dt->format($dt->format('s') === '00' ? 'H:i' : Locale::ISO_TIME);
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
