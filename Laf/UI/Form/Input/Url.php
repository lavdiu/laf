<?php

namespace Laf\UI\Form;

use Laf\UI\ComponentInterface;
use Laf\UI\Form\Input\Text;

class Url extends Text implements FormElementInterface, ComponentInterface
{

	/**
	 * @return string
	 */
	public function __toString()
	{
		return $this->draw();
	}

	public function draw(): ?string
	{
		$this->addAttribute('type', InputType::Url);
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
