<?php


namespace Laf\UI\Container;

use Laf\UI\ComponentInterface;
use Laf\UI\Traits\ComponentTrait;

class GenericContainer implements ComponentInterface
{
	use ComponentTrait;

	/**
	 * @return string
	 */
	public function draw(): ?string
	{
		$this->addCssClass(static::getComponentCssControlClass());
		$this->addCssClass($this->getContainerType());
		$html = "\n\t<div
			style='{$this->getCssStyleForHtml()}' 
			class='{$this->getCssClassesForHtml()}'
		>\n";
		foreach ($this->getComponents() as $component) {
			$component->setDrawMode($this->getDrawMode());
			$component->setFormRowDisplayMode($this->getFormRowDisplayMode());
			$html .= "\n\t\t" . $component->draw();
		}
		$html .= "\n\t</div>";
		return $html;
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
