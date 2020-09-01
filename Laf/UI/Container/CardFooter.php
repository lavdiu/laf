<?php


namespace Laf\UI\Container;


use Laf\UI\ComponentInterface;
use Laf\UI\Traits\ComponentTrait;

class CardFooter extends Div implements ComponentInterface
{
	use ComponentTrait;


	/**
	 * @inheritDoc
	 */
	public function draw(): ?string
	{
		$this->addCssClass('card-footer');
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