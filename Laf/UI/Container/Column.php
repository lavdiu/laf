<?php


namespace Laf\UI\Container;


use Laf\UI\ComponentInterface;

class Column extends GenericContainer implements ComponentInterface
{
	public function draw(): ?string
	{
		$this->addCssClass(static::getComponentCssControlClass())
			->addCssClass('col-sm');

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
