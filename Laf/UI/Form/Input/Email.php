<?php

namespace Laf\UI\Form;

use Laf\UI\ComponentInterface;
use Laf\UI\Form\Input\Text;

class Email extends Text implements FormElementInterface, ComponentInterface
{
    public function drawUpdateMode()
    {
        $this->setType(InputType::Email);
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
