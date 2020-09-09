<?php

namespace Laf\UI\Form\Control;

use Laf\Database\Field\Field;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;


class SubmitButton extends Button implements FormElementInterface, ComponentInterface
{

	public function __construct()
	{
		parent::__construct();
		$this->setType(InputType::Submit);
	}

	/**
	 * @param Field $field
	 * @return mixed
	 * @throws \Exception
	 */
	public function setField(Field $field): ComponentInterface
	{
		throw new \Exception('Invalid call');
	}

	/**
	 * @return mixed
	 * @throws \Exception
	 */
	public function getField(): ?Field
	{
		throw new \Exception('Invalid call');
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
