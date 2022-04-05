<?php

namespace Laf\UI\Form\Control;

use Laf\Database\Field\Field;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Traits\ComponentTrait;
use Laf\UI\Traits\FormElementTrait;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\InputType;


class Button implements FormElementInterface, ComponentInterface
{

    use ComponentTrait;
    use FormElementTrait;

    protected $wrapInDiv = true;

    public function __construct()
    {
        $this->setType(InputType::Button);
        $this->attributes['classl'] = [];
    }

    /**
     * @return bool
     */
    public function getWrapInDiv(): bool
    {
        return $this->wrapInDiv;
    }

    /**
     * @param bool $wrapInDiv
     * @return Button
     */
    public function setWrapInDiv(bool $wrapInDiv): Button
    {
        $this->wrapInDiv = $wrapInDiv;
        return $this;
    }


    /**
     * @param int $value
     * @return Button
     */
    public function setHeight(?int $value): Button
    {
        $this->addAttribute('height', $value);
        return $this;
    }

    /**
     * Draws the button
     * @return string
     */
    public function draw(): ?string
    {
        $html = "";

        /**
         * add a css class to track component name
         */
        $this->addCssClass(static::getComponentCssControlClass());

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

        $params = '';
        foreach ($attributes as $key => $value) {
            if (!is_array($value))
                $params .= "\n\t\t\t\t" . $key . '="' . $value . '" ';
        }

        if ($this->getWrapInDiv()) {
            $html .= "
        <div id='{$this->getId()}_container' class='form-group {$this->getFormRowDisplayMode()}" . ($this->isHidden() || $this->hasCssClass('d-none') ? " d-none" : "") . "'>
            <label id='{$this->getId()}_label' for='{$this->getId()}' class='col-sm-2 col-form-label'>{$this->getLabel()}" . ($this->isRequired() ? '*' : '') . ($this->getLabel() != '' ? ' *' : '') . "</label>
            <div class='col-sm-10'>
        ";
        }
        $html .= "\t<button {$params}
                    class='{$this->getCssClassesForHtml()}' 
                    style='{$this->getCssStyleForHtml()}'  
                    " . ((mb_strlen($this->getHint()??'') > 0) ? "aria-describedby='{$this->getId()}_hint'" : "") . "
                >{$this->getValueForHtml()}</button>";
        if ($this->getWrapInDiv()) {
            $html .= ((mb_strlen($this->getHint()??'') > 0) ? "\n\t\t\t\t<small id='{$this->getId()}_hint' class='form-Button Button-muted'>{$this->getHint()}</small>" : "") . "
            </div>
        </div>";
        }

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
}
