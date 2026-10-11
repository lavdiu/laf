<?php

namespace Laf\UI\Form\Input;

use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;

class Search extends Text implements FormElementInterface
{
    public function drawUpdateMode(): string
    {
        $this->setType(InputType::Search);
        return parent::drawUpdateMode();
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
