<?php

namespace Laf\UI\Form\Input;

use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;

class TextArea extends Text implements FormElementInterface, ComponentInterface
{
	public function drawUpdateMode()
	{
		$this->addCssClass(static::getComponentCssControlClass());

		if (!is_numeric($this->getCols()))
			$this->setCols(5);
		if (!is_numeric($this->getRows()))
			$this->setRows('5');
		if (!is_numeric($this->getHeight()))
			$this->setHeight('100');

		$attributes = $this->getAttributes();
		unset($attributes['value']);
		unset($attributes['type']);
		unset($attributes['class']);
		unset($attributes['style']);
		unset($attributes['max']);
		unset($attributes['min']);
		unset($attributes['height']);
		unset($attributes['width']);

		if ($this->getHeight())
			$this->addCssStyleItem('height', $this->getHeight() . 'px');
		if ($this->getWidth())
			$this->addCssStyleItem('width', $this->getWidth() . 'px');

		$this->addCssClass('form-control');

		$params = '';
		foreach ($attributes as $key => $value)
			$params .= "\n\t\t\t\t" . $key . '="' . $value . '" ';

		$html = "
        <div id='{$this->getId()}_container' style='{$this->getWrapperCssStyleForHtml()}' class='form-group {$this->getFormRowDisplayMode()} {$this->getWrapperCssClassesForHtml()} " . ($this->isHidden() || $this->hasCssClass('d-none') ? " d-none" : "") . "'>
            <label id='{$this->getId()}_label' for='{$this->getId()}' class='col-sm-2 col-form-label'>{$this->getLabel()}: " . ($this->isRequired() ? '*' : '') . "</label>
            <div class='col-sm-10'>
            <textarea 
                class='{$this->getCssClassesForHtml()}' 
                style='{$this->getCssStyleForHtml()}' 
                {$params}
                " . ((mb_strlen($this->getHint()) > 0) ? "aria-describedby='{$this->getId()}_hint'" : "")
			. ">{$this->getValue()}</textarea>
                " . ((mb_strlen($this->getHint()) > 0) ? "\n\t\t\t\t<small id='{$this->getId()}_hint' class='form-text text-muted'>{$this->getHint()}</small>" : "") . "
            </div>
        </div>";

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
