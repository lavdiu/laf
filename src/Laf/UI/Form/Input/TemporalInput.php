<?php

namespace Laf\UI\Form\Input;

use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;
use Laf\Util\Locale;
use Laf\Util\Settings;
use Laf\Util\Util;

/**
 * Shared behaviour of the Date, DateTime and Time inputs.
 *
 * The stored value is canonical ('Y-m-d', 'Y-m-d H:i:s', 'H:i:s'); the input
 * shows it in the locale display format (Settings locale.date.format …) as a
 * text input carrying a picker class (date-picker, date-time-picker, time-picker)
 * for the app's JavaScript. Seconds are kept in the display when they are not
 * zero, so a value survives a round trip unchanged.
 *
 * setNativeInput(true), or Settings 'form.native_date_inputs' = true, renders
 * the browser's own control instead: <input type="date|datetime-local|time">
 * with ISO values. Both forms are accepted back by the field types' fromInput().
 */
abstract class TemporalInput extends Text implements FormElementInterface
{
    /** null = follow the Settings default */
    protected ?bool $nativeInput = null;

    /**
     * Render the browser's native control (true), the text + picker input (false), or follow Settings (null)
     */
    public function setNativeInput(?bool $native): self
    {
        $this->nativeInput = $native;
        return $this;
    }

    public function isNativeInput(): bool
    {
        if ($this->nativeInput !== null) {
            return $this->nativeInput;
        }
        return self::nativeInputsByDefault();
    }

    /** CSS class the picker JavaScript binds to */
    abstract protected function pickerClass(): string;

    /** HTML input type of the native control */
    abstract protected function nativeType(): string;

    /** Formats the stored or typed value may be in, most likely first */
    abstract protected function acceptedFormats(): array;

    /** Locale display format */
    abstract protected function displayFormat(): string;

    /** Value for the native control */
    abstract protected function nativeFormat(\DateTimeImmutable $dt): string;

    /**
     * VIEW mode: the value in the locale display format (unchanged when it does not parse)
     */
    public function getDisplayValue(): ?string
    {
        $dt = $this->parseValue();
        if ($dt === null) {
            return $this->getValue();
        }
        return $dt->format(static::withSeconds($this->displayFormat(), $dt));
    }

    /**
     * Value of the editable control: ISO for the native control, locale format otherwise
     */
    public function getEditValue(): ?string
    {
        $dt = $this->parseValue();
        if ($dt === null) {
            return $this->getValue();
        }
        return $this->isNativeInput() ? $this->nativeFormat($dt) : $dt->format(static::withSeconds($this->displayFormat(), $dt));
    }

    public function drawUpdateMode(): string
    {
        $this->addCssClass(static::getComponentCssControlClass());
        $native = $this->isNativeInput();

        $attributes = $this->controlAttributes();
        $attributes['type'] = $native ? $this->nativeType() : InputType::Text;
        $attributes['value'] = $this->getEditValue();
        if ($native && $this->nativeType() !== InputType::Date && empty($attributes['step'])) {
            $dt = $this->parseValue();
            if ($dt !== null && $dt->format('s') !== '00') {
                $attributes['step'] = '1';
            }
        }

        $control = "<input class='" . Util::e(join(' ', $this->controlClasses('form-control', $native ? '' : $this->pickerClass()))) . "' style='" . Util::e($this->controlStyle()) . "'"
            . $this->renderAttributes($attributes)
            . $this->describedBy() . " />";

        return $this->renderWrapper($control);
    }

    /**
     * The current value parsed with the accepted formats, null when empty or unparseable
     */
    protected function parseValue(): ?\DateTimeImmutable
    {
        $value = $this->getValue();
        if ($value === null || trim($value) === '') {
            return null;
        }
        return Locale::parseDate($value, $this->acceptedFormats());
    }

    /**
     * Adds ":s" after the minutes of a format that has none when the value carries seconds,
     * so 'H:i' shows 14:05:09 for a value with seconds and 14:05 otherwise.
     */
    protected static function withSeconds(string $format, \DateTimeInterface $dt): string
    {
        return $dt->format('s') === '00' ? $format : Locale::formatWithSeconds($format);
    }

    private static function nativeInputsByDefault(): bool
    {
        try {
            $value = Settings::get('form.native_date_inputs');
        } catch (\Throwable $ex) {
            return false;
        }
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true;
    }
}
