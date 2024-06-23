<?php

namespace Laf\UI\Form\Input;

use Laf\Filesystem\Document;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;

class File extends Text implements FormElementInterface, ComponentInterface
{

	public function drawViewMode()
	{
		$this->addCssClass(static::getComponentCssControlClass());

		$this->removeAttribute('class')
			->removeAttribute('style');
		$content = "(no file attached)";
		if (is_numeric($this->getValue()) && $this->getValue() > 0) {
			$document = new Document($this->getValue());
			if (!$document->fileFullSizeExists()) {
				$content = '(file removed)';
			} else if ($document->isImage())
				$content = "<a target='_blank' href='?module=document&submodule=view&id={$this->getValue()}'><img src='?module=document&submodule=thumbnail&id={$this->getValue()}' style='height:150px;' alt='image' style='{$this->getCssStyleForHtml()}' class='{$this->getCssClassesForHtml()}' /></a>";
			else
				$content = "<a href='?module=document&submodule=download&id={$this->getValue()}'>Download File</a>";

		}
		return "
        <div id='{$this->getId()}_container' style='{$this->getWrapperCssStyleForHtml()}' class='mb-3 {$this->getFormRowDisplayMode()} {$this->getWrapperCssClassesForHtml()} " . ($this->hasCssClass('d-none') || $this->isHidden() ? " d-none" : "") . "'>
            <label id='{$this->getId()}_label' for='{$this->getId()}' class='" . ($this->getFormRowDisplayMode() == 'row' ? "col-sm-2" : "") . " col-form-label'>{$this->getLabel()}" . ($this->isRequired() ? '*' : '') . " :</label>
            " . ($this->getFormRowDisplayMode() == 'row' ? "<div class='col-sm-10'>" : "") . "
                <div class='bg-light rounded p-2' id='{$this->getId()}'>
                {$content}
                </div>
            ".($this->getFormRowDisplayMode() == 'row' ? "</div>" : "") . "
        </div>";
	}

	/**
	 * Returns the CSS class unique to the UI component
	 * @return string
	 */
	public function getComponentCssControlClass(): string
	{
		return str_replace('\\', '-', static::class);
	}

	public function drawUpdateMode()
	{
		$this->addCssClass(static::getComponentCssControlClass());

		$this->setType(InputType::File);
		$attributes = [];
		foreach ($this->getAttributes() as $key => $value) {
			if ($value != '') {
				$attributes[$key] = $value;
			}
		}

		if ($this->getHeight()) {
			$this->addStyle('height', $this->getHeight() . 'px');
			unset($attributes['height']);
		}
		if ($this->getWidth()) {
			$this->addStyle('width', $this->getWidth() . 'px');
			unset($attributes['width']);
		}

		unset($attributes['value']);
		unset($attributes['placeholder']);
		unset($attributes['autocomplete']);

		$params = '';
		foreach ($attributes as $key => $value)
			$params .= "\n\t\t\t\t" . $key . '="' . $value . '" ';
		$html = "
        <div id='{$this->getId()}_container'  style='{$this->getWrapperCssStyleForHtml()}'  class='mb-3 {$this->getFormRowDisplayMode()} {$this->getWrapperCssClassesForHtml()} " . ($this->isHidden() || $this->hasCssClass('d-none') ? " d-none" : "") . "'>
            <label id='{$this->getId()}_label' for='{$this->getId()}' class='" . ($this->getFormRowDisplayMode() == 'row' ? "col-sm-2" : "") . " col-form-label'>{$this->getLabel()}" . ($this->isRequired() ? '*' : '') . " :</label>
            " . ($this->getFormRowDisplayMode() == 'row' ? "<div class='col-sm-10'>" : "") . "
            <input {$params} class='{$this->getCssClassesForHtml()}' style='{$this->getCssStyleForHtml()}' " . ((mb_strlen($this->getHint()??'') > 0) ? "aria-describedby='{$this->getId()}_hint'" : "") . "/>
            <input type='hidden' name='{$this->getField()->getNameRot13()}' value='1' />
                " . ((mb_strlen($this->getHint()??'') > 0) ? "\n\t\t\t\t<small id='{$this->getId()}_hint' class='form-text text-muted'>{$this->getHint()}</small>" : "") . "
            ".($this->getFormRowDisplayMode() == 'row' ? "</div>" : "") . "
        </div>";

		return $html;
	}
}
