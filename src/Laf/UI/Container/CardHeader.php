<?php


namespace Laf\UI\Container;


use Laf\UI\ComponentInterface;
use Laf\UI\Traits\ComponentTrait;

class CardHeader extends Div implements ComponentInterface
{
	use ComponentTrait;


	/**
	 * @inheritDoc
	 */
	public function draw(): ?string
	{
		$this->addCssClass('card-header');
		return parent::draw();

	}

	/**
	 * @return string
	 */
	public function getComponentCssControlClass(): string
	{
		return str_replace('\\', '-', static::class);
	}
}