<?php

namespace Laf\UI\Form\Control;

use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;

class SubmitButton extends Button implements FormElementInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->setType(InputType::Submit);
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
