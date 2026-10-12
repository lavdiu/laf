<?php

namespace Laf\UI\Form\Input;

use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;
use Laf\Util\Util;

/**
 * <input type="password">: the stored value is never written into the page;
 * VIEW mode shows a mask when a value exists.
 */
class Password extends Text implements FormElementInterface
{
    public function getDisplayValue(): ?string
    {
        $value = $this->getValue();
        return ($value === null || $value === '') ? '' : '••••••';
    }

    public function drawUpdateMode(): string
    {
        $this->setType(InputType::Password);
        $this->addCssClass(static::getComponentCssControlClass());

        $control = "<input class='" . Util::e(join(' ', $this->controlClasses('form-control'))) . "' style='" . Util::e($this->controlStyle()) . "'"
            . $this->renderAttributes($this->controlAttributes(['value']))
            . $this->describedBy() . " />";

        return $this->renderWrapper($control, ['suggestions' => false]);
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
