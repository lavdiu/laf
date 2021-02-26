<?php

namespace Laf\UI\Form\Input;

use Laf\UI\Traits\ComponentTrait;
use Laf\UI\Traits\FormElementTrait;
use Laf\Database\Field\Field;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;


class Text implements FormElementInterface, ComponentInterface
{
    use FormElementTrait;
    use ComponentTrait;

    /**
     * @var Field
     */
    private $field = null;

    /**
     * @var string
     */
    private $invalidValueErrorMessage;

    public function __construct()
    {
        $this->setType(InputType::Text);
    }

    /**
     * @param string $drawMode
     * @return Text
     */
    public function setDrawMode(string $drawMode): ComponentInterface
    {
        $this->drawMode = $drawMode;
        return $this;
    }

    /**
     * @return string
     */
    public function draw(): ?string
    {
        /**
         * add a css class to track component name
         */
        $this->addCssClass(str_replace('\\', '-', static::class));

        switch ($this->getDrawMode()) {
            case DrawMode::VIEW:
                return static::drawViewMode();
                break;
            case DrawMode::INSERT:
                return static::drawinsertMode();
                break;
            case DrawMode::UPDATE:
                return static::drawUpdateMode();
                break;
            default:
                return static::drawViewMode();
                break;
        }
    }

    /**
     * Draws the input component in update mode
     * showing the current value
     * @return string
     */
    public function drawViewMode()
    {
        /**
         * 2 things are different from drawUpdateMode:
         * 1. class for inputs is form-control-plaintext
         * 2. hint is not displayed
         */

        $this->addCssClass(static::getComponentCssControlClass());
        $this->removeAttribute('class')
            ->removeAttribute('style');

        $attributes = $this->getAttributes();

        unset($attributes['name']);
        unset($attributes['id']);
        unset($attributes['value']);
        unset($attributes['maxlength']);
        unset($attributes['minlength']);
        unset($attributes['max']);
        unset($attributes['min']);
        unset($attributes['required']);
        unset($attributes['readonly']);
        unset($attributes['placeholder']);

        if ($this->getHeight()) {
            $this->addCssStyleItem('height', $this->getHeight() . 'px');
            unset($attributes['height']);
        }
        if ($this->getWidth()) {
            $this->addCssStyleItem('width', $this->getWidth() . 'px');
            unset($attributes['width']);
        }

        $this->addCssClass('form-control-plaintext');

        $params = '';
        foreach ($attributes as $key => $value)
            $params .= "\n\t\t\t\t" . $key . '="' . $value . '" ';


        $this->addCssClass('bg-light')
            ->addCssClass('rounded')
            ->addCssClass('p-2');
        $html = "
        <div id='{$this->getId()}_container' style='{$this->getWrapperCssStyleForHtml()}' class='form-group mb-2 {$this->getFormRowDisplayMode()} {$this->getWrapperCssClassesForHtml()}} " . ($this->isHidden() || $this->hasCssClass('d-none') ? " d-none" : "") . "'>
            <label id='{$this->getId()}_label' for='{$this->getId()}' class='col-sm-2 col-form-label'>{$this->getLabel()}: " . "</label>
            <div class='col-sm-10'>
                <div
                    id='{$this->getId()}'
                    class='{$this->getCssClassesForHtml()}' 
					style='{$this->getCssStyleForHtml()}'
					{$params}
                    >" . ($this->getValueForHtml() != "" ? $this->getValueForHtml() : "&nbsp;") . "</div>
            </div>
        </div>";

        return $html;
    }


    /**
     * @return string
     */
    public function getValueForHtml(): ?string
    {
        return nl2br(htmlentities($this->getAttribute('value')));
    }

    /**
     * Draws the input component in insert mode
     * @return string
     */
    protected function drawInsertMode()
    {
        #$this->setValue('');
        return static::drawUpdateMode();
    }


    /**
     * Draws the input for Update mode
     * @return string
     */
    public function drawUpdateMode()
    {

        $this->addCssClass(static::getComponentCssControlClass());

        $attributes = [];
        foreach ($this->getAttributes() as $key => $value) {
            if ($value != '') {
                $attributes[$key] = $value;
            }
        }

        if ($this->getHeight()) {
            $this->addCssStyleItem('height', $this->getHeight() . 'px');
            unset($attributes['height']);
        }
        if ($this->getWidth()) {
            $this->addCssStyleItem('width', $this->getWidth() . 'px');
            unset($attributes['width']);
        }

        unset($attributes['class']);
        unset($attributes['style']);
        $this->addCssClass('form-control');

        $params = '';
        foreach ($attributes as $key => $value)
            $params .= "\n\t\t\t\t" . $key . '="' . $value . '" ';

        $html = "
        <div id='{$this->getId()}_container' style='{$this->getWrapperCssStyleForHtml()}' class='form-group mb-2 {$this->getFormRowDisplayMode()} {$this->getWrapperCssClassesForHtml()}" . ($this->isHidden() || $this->hasCssClass('d-none') ? " d-none" : "") . "'>
            <label id='{$this->getId()}_label' for='{$this->getId()}' class='col-sm-2 col-form-label'>{$this->getLabel()}" . ($this->isRequired() ? '*' : '') . " :</label>
            <div class='col-sm-10'>
                <input 
                    class='{$this->getCssClassesForHtml()}' 
                    style='{$this->getCssStyleForHtml()}'
                    {$params} 
                    " . ((mb_strlen($this->getHint()) > 0) ? "aria-describedby='{$this->getId()}_hint'" : "") . "
                />
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

    /**
     * @return Field
     */
    public function getField(): ?Field
    {
        return $this->field;
    }

    /**
     * @param Field $field
     * @return ComponentInterface
     */
    public function setField(Field $field): ComponentInterface
    {
        $this->field = $field;
        $this->copyAttributesFromField();
        return $this;
    }

    /**
     * Copies attributes from Field to the input
     * @return Text
     */
    protected function copyAttributesFromField()
    {
        $field = $this->getField();
        /**
         * set this one first, because it could override other attributes if set below
         */
        $this->setAttributes($field->getAttributes());

        $this->setName($field->getNameRot13());
        $this->setId($field->getNameRot13());
        $this->setValue($field->getValue());
        if (is_numeric($field->getMaxLength())) $this->setMaxLength($field->getMaxLength());
        if (is_numeric($field->getMinLength())) $this->setMinLength($field->getMinLength());
        if (is_numeric($field->getMaxValue())) $this->setMax($field->getMaxValue());
        if (is_numeric($field->getMinValue())) $this->setMin($field->getMinValue());
        if ($field->isAutoIncrement())
            $this->setStep($field->getIncrementStep());
        if ($field->isRequired())
            $this->setRequired(true);
        $this->setLabel($field->getLabel());
        $this->setPlaceholder($field->getPlaceholder());
        $this->setHint($field->getHint());
        $this->setInvalidValueErrorMessage($field->getInvalidValueErrorMessage());
        return $this;
    }

    /**
     * @return string
     */
    public function getInvalidValueErrorMessage(): ?string
    {
        return $this->invalidValueErrorMessage;
    }

    /**
     * @param string $invalidValueErrorMessage
     * @return Text
     */
    public function setInvalidValueErrorMessage(?string $invalidValueErrorMessage): Text
    {
        $this->invalidValueErrorMessage = $invalidValueErrorMessage;
        return $this;
    }

}
