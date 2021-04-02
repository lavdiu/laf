<?php

namespace Laf\UI\Form\Input;

use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;

class Hidden extends Text implements FormElementInterface, ComponentInterface
{
	public function drawUpdateMode()
	{
		$this->addCssClass(static::getComponentCssControlClass());

		$this->setType(InputType::Hidden);
		$this->setHidden(true);
		$attributes = $this->getAttributes();
		$this->removeCssClass('form-control');

		unset($attributes['required']);
		unset($attributes['placeholder']);
		unset($attributes['class']);
		unset($attributes['style']);
		unset($attributes['maxlength']);
		unset($attributes['minlength']);
		$params = '';
		foreach ($attributes as $key => $value)
			$params .= "\n\t\t\t\t" . $key . '="' . $value . '" ';

		$html = "
        <div class='d-none'>
            <input 
                {$params}
                class='{$this->getCssClassesForHtml()}' 
				style='{$this->getCssStyleForHtml()}'
                />
        </div>";

		return $html;
	}

	public function drawViewMode()
	{
		$this->addCssClass(static::getComponentCssControlClass());
		$this->setHidden(true);
		return parent::drawViewMode();
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
