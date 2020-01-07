<?php

namespace Laf\UI\Form\Input;


use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;

class DateTime extends Text implements FormElementInterface, ComponentInterface
{
	public function drawUpdateMode()
	{
		$this->setType(InputType::Text);
		$this->addCssClass('date-time-picker');
		if ($this->getValue() == '') {
			return parent::drawUpdateMode();
		}
		$date = \Datetime::createFromFormat('Y-m-d H:i', $this->getField()->getValue());
		if ($date === false) {
			$date = new \DateTime();
		}
		$this->setValue($date->format('F d, Y H:i'));
		return parent::drawUpdateMode();
	}

	public function drawViewMode()
	{
		if ($this->getValue() == '') {
			return parent::drawViewMode();
		}
		$date = \Datetime::createFromFormat('Y-m-d H:i:s', $this->getField()->getValue());
		if ($date === false) {
			$date = new \DateTime();
		}
		$this->setValue($date->format('F d, Y H:i A'));
		$this->removeCssClass('date-time-picker');
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
