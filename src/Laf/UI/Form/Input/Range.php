<?php

declare(strict_types=1);

namespace Laf\UI\Form\Input;

use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;
use Laf\Util\Util;

/**
 * <input type="range"> (a slider, Bootstrap form-range) between the min and max
 * attributes (from the field's type or setMin()/setMax()); VIEW mode shows the number.
 */
class Range extends Text implements FormElementInterface
{
    public function drawUpdateMode(): string
    {
        $this->setType(InputType::Range);
        $this->addCssClass(static::getComponentCssControlClass());

        $classes = array_values(array_diff($this->controlClasses('form-range'), ['form-control']));
        $control = "<input class='" . Util::e(join(' ', $classes)) . "' style='" . Util::e($this->controlStyle()) . "'"
            . $this->renderAttributes($this->controlAttributes())
            . $this->describedBy() . " />";

        return $this->renderWrapper($control, ['suggestions' => false]);
    }

    /**
     * Returns the CSS class unique to the UI component
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }
}
