<?php

namespace Laf\UI\Form\Input;


use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;

class Date extends Text implements FormElementInterface, ComponentInterface
{
	public function drawUpdateMode()
	{

		$this->setType(InputType::Date);
		$this->addCssClass('date-picker');
		if ($this->getValue() == '') {
			return parent::drawUpdateMode();
		}
		$date = \Datetime::createFromFormat('Y-m-d', $this->getValue());
		if ($date === false) {
			$date = new \DateTime();
		}
		$this->setValue($date->format('F d, Y'));
		return parent::drawUpdateMode();
	}

	public function drawViewMode()
	{
		$this->removeCssClass('date-picker');
		if ($this->getValue() == '') {
			return parent::drawViewMode();
		}
		$date = \Datetime::createFromFormat('Y-m-d', $this->getValue());
		if ($date === false) {
			$date = new \DateTime();
		}
		$this->setValue($date->format('F d, Y'));
		$this->addCssClass('form-control-plaintext');
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
