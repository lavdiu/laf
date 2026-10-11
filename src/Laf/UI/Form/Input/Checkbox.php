<?php

namespace Laf\UI\Form\Input;

use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;

class Checkbox extends Text implements FormElementInterface, ComponentInterface
{
    /** The field's value as a bool: accepts true/false, 0/1, "0"/"1", "t"/"f", "yes"/"no", null (= false) */
    private function isChecked(): bool
    {
        $value = $this->getField() ? $this->getField()->getValue() : $this->getValue();
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return false;
        }
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool)$value;
    }

    public function drawUpdateMode()
    {
        $this->setType(InputType::Checkbox);
        $this->setValue('1');
        if ($this->isChecked()) {
            $this->setAttribute('checked', 'checked');
        } else {
            $this->removeAttribute('checked');
        }
        return parent::drawUpdateMode();
    }

    public function drawViewMode()
    {
        $this->setValue($this->isChecked() ? 'Yes' : 'No');
        return parent::drawViewMode();
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
