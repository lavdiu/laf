<?php

namespace Laf\UI\Form\Input;

use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;
use Laf\Util\Util;

/**
 * A single checkbox for a boolean column.
 *
 * A hidden input with value 0 precedes the box, so the field is always posted
 * ("0" unchecked, "1" checked) and Form can tell "unchecked" from "not on the
 * form". The hidden input is omitted for a disabled box. VIEW mode shows Yes/No.
 */
class Checkbox extends Text implements FormElementInterface
{
    protected static string $yesLabel = 'Yes';

    protected static string $noLabel = 'No';

    /**
     * Texts shown in VIEW mode, process-wide (e.g. translations)
     */
    public static function setLabels(string $no, string $yes): void
    {
        static::$noLabel = $no;
        static::$yesLabel = $yes;
    }

    /**
     * The value as a bool: accepts true/false, 0/1, "0"/"1", "t"/"f", "yes"/"no", "on"/"off", null and "" (= false)
     */
    public function isChecked(): bool
    {
        $value = $this->getValue();
        if ($value === null || trim($value) === '') {
            return false;
        }
        $bool = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($bool !== null) {
            return $bool;
        }
        $s = strtolower(trim($value));
        if (in_array($s, ['t', 'y'], true)) {
            return true;
        }
        if (in_array($s, ['f', 'n'], true)) {
            return false;
        }
        return (bool)$value;
    }

    public function getDisplayValue(): ?string
    {
        return $this->isChecked() ? static::$yesLabel : static::$noLabel;
    }

    public function drawUpdateMode(): string
    {
        $this->setType(InputType::Checkbox);
        $this->addCssClass(static::getComponentCssControlClass());

        $attributes = $this->controlAttributes(['value', 'checked', 'placeholder', 'maxlength', 'minlength', 'pattern', 'inputmode', 'step', 'min', 'max', 'size', 'multiple', 'accept', 'autocomplete']);
        $attributes['value'] = '1';
        $classes = array_values(array_diff($this->controlClasses('form-check-input'), ['form-control']));

        $name = $this->getName();
        $hidden = '';
        if ($name !== null && $name !== '' && !$this->isDisabled()) {
            $hidden = "<input type='hidden' name='" . Util::e($name) . "' value='0' />\n\t\t\t\t";
        }

        $control = "<div class='form-check'>\n\t\t\t\t" . $hidden
            . "<input class='" . Util::e(join(' ', $classes)) . "' style='" . Util::e($this->controlStyle()) . "'"
            . $this->renderAttributes($attributes)
            . $this->describedBy()
            . ($this->isChecked() ? ' checked="checked"' : '')
            . " />\n\t\t\t\t</div>";

        return $this->renderWrapper($control);
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
