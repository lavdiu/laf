<?php

namespace Laf\UI\Form\Input;

use Laf\UI\Form\FormElementInterface;

/**
 * One checkbox per option (static list or referenced table) for a column that
 * stores several selections as a comma separated list (MySQL SET, varchar).
 * Posts name[]; Form joins the values with commas. An empty hidden value is
 * posted first so clearing every box is submitted too.
 */
class CheckboxCollection extends Select implements FormElementInterface
{
    public function drawUpdateMode(): string
    {
        return $this->renderChoiceList('checkbox', true);
    }

    protected function isMultiValued(): bool
    {
        return true;
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
