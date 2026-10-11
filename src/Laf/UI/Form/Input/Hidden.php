<?php

namespace Laf\UI\Form\Input;

use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;
use Laf\Util\Util;

/**
 * <input type="hidden">, used for auto-increment primary keys. Never visible in any mode.
 */
class Hidden extends Text implements FormElementInterface
{
    public function drawUpdateMode(): string
    {
        $this->setType(InputType::Hidden);
        $this->addCssClass(static::getComponentCssControlClass());

        $attributes = $this->controlAttributes(['required', 'placeholder', 'maxlength', 'minlength', 'pattern', 'inputmode', 'step', 'min', 'max', 'hidden', 'autocomplete', 'checked', 'accept', 'multiple', 'size']);
        $classes = array_values(array_diff($this->getCssClasses(), ['form-control']));

        return "
        <div class='d-none'>
            <input class='" . Util::e(join(' ', $classes)) . "' style='" . Util::e($this->controlStyle()) . "'" . $this->renderAttributes($attributes) . " />
        </div>";
    }

    protected function viewWrapperOptions(): array
    {
        return ['hidden' => true] + parent::viewWrapperOptions();
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
