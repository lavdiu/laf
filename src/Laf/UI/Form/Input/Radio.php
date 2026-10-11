<?php

namespace Laf\UI\Form\Input;

use Laf\UI\Form\FormElementInterface;

/**
 * One radio button per option (static list or referenced table), the current
 * value checked. VIEW mode shows the selected label like Select does.
 * setInline(true) puts the choices side by side.
 */
class Radio extends Select implements FormElementInterface
{
    public function drawUpdateMode(): string
    {
        return $this->renderChoiceList('radio', false);
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
