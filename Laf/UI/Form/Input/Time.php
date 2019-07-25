<?php

namespace Laf\UI\Form\Input;

use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;

class Time extends Text implements FormElementInterface, ComponentInterface
{
	public function drawViewMode()
	{

		$date = \Datetime::createFromFormat('H:i:s', $this->getValue());
		if ($date === false) {
			$date = new \DateTime();
		}
		$this->setValue($date->format('H:i A'));
		return parent::drawViewMode();
	}

	public function drawUpdateMode()
	{
		$this->setType(InputType::Text);
		$this->addCssClass('time-picker');
		if ($this->getValue() == '') {
			return parent::drawUpdateMode();
		}
		$date = \Datetime::createFromFormat('H:i:s', $this->getValue());
		if ($date === false) {
			$date = new \DateTime();
		}
		$this->setValue($date->format('H:i A'));
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
