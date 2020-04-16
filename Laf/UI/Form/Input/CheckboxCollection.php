<?php

namespace Laf\UI\Form\Input;


#@TODO implement
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;

class CheckboxCollection extends Text implements FormElementInterface, ComponentInterface
{
     public function drawUpdateMode()
    {
        $this->setType(InputType::Checkbox);
        if ($this->getValue()) {
            $this->getField()->getFormElement()->setAttribute('checked', 'checked');
        }

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
