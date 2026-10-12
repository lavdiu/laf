<?php

namespace Laf\UI\Form\Input;

use Laf\UI\Form\FormElementInterface;
use Laf\Util\Util;

/**
 * <textarea> for text and JSON columns; 5 rows unless setRows() says otherwise.
 */
class TextArea extends Text implements FormElementInterface
{
    public function drawUpdateMode(): string
    {
        $this->addCssClass(static::getComponentCssControlClass());

        $attributes = $this->controlAttributes($this->inputOnlyAttributesExcept('placeholder', 'maxlength', 'minlength', 'autocomplete'));
        if (!is_numeric($attributes['rows'] ?? null)) {
            $attributes['rows'] = '5';
        }

        $control = "<textarea class='" . Util::e(join(' ', $this->controlClasses('form-control'))) . "' style='" . Util::e($this->controlStyle()) . "'"
            . $this->renderAttributes($attributes)
            . $this->describedBy() . ">" . Util::e($this->getValue()) . "</textarea>";

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
