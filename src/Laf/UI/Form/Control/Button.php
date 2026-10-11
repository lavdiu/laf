<?php

namespace Laf\UI\Form\Control;

use Laf\Database\Field\Field;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\FormRowDisplayMode;
use Laf\UI\Form\InputType;
use Laf\UI\Traits\ComponentTrait;
use Laf\UI\Traits\FormElementTrait;
use Laf\Util\Util;

/**
 * <button>: the value is the caption. By default it is wrapped like an input
 * (container, label column); setWrapInDiv(false) renders the bare button, as
 * modal footers do. A button is not bound to a field: getField() is null.
 */
class Button implements FormElementInterface
{
    use ComponentTrait;
    use FormElementTrait;

    protected bool $wrapInDiv = true;

    public function __construct()
    {
        $this->setType(InputType::Button);
    }

    public function getWrapInDiv(): bool
    {
        return $this->wrapInDiv;
    }

    public function setWrapInDiv(bool $wrapInDiv): Button
    {
        $this->wrapInDiv = $wrapInDiv;
        return $this;
    }

    /**
     * Draws the button
     */
    public function draw(): ?string
    {
        /**
         * add a css class to track component name
         */
        $this->addCssClass(static::getComponentCssControlClass());

        $attributes = $this->getAttributes();
        unset($attributes['class'], $attributes['style'], $attributes['height'], $attributes['width'], $attributes['value']);

        $style = $this->getCssStyles();
        if ($this->getHeight()) {
            $style['height'] = $this->getHeight() . 'px';
        }
        if ($this->getWidth()) {
            $style['width'] = $this->getWidth() . 'px';
        }
        $styleItems = [];
        foreach ($style as $property => $value) {
            if ($value !== null && $value !== '') {
                $styleItems[] = $property . ':' . $value;
            }
        }

        $params = '';
        foreach ($attributes as $key => $value) {
            if (is_array($value)) {
                $value = join(' ', array_map('strval', $value));
            }
            if ($value === null || (string)$value === '') {
                continue;
            }
            $params .= "\n\t\t\t\t" . $key . '="' . Util::attr($value) . '"';
        }

        $id = Util::e($this->getId());
        $hasHint = mb_strlen($this->getHint() ?? '') > 0;

        $button = "\t<button" . $params . "
                    class='" . Util::e($this->getCssClassesForHtml()) . "'
                    style='" . Util::e(join(';', $styleItems)) . "'"
            . ($hasHint ? " aria-describedby='{$id}_hint'" : '') . "
                >" . $this->getValueForHtml() . "</button>";

        if (!$this->getWrapInDiv()) {
            return $button;
        }

        $isRow = $this->getFormRowDisplayMode() === FormRowDisplayMode::ROW;
        $hidden = ($this->isHidden() || $this->hasCssClass('d-none')) ? ' d-none' : '';
        $label = Util::e($this->getLabel()) . ($this->isRequired() ? '*' : '');
        $hint = $hasHint ? "\n\t\t\t\t<small id='{$id}_hint' class='form-text text-muted'>" . Util::e($this->getHint()) . "</small>" : '';

        return "
        <div id='{$id}_container' class='mb-3 " . Util::e((string)$this->getFormRowDisplayMode()) . $hidden . "'>
            <label id='{$id}_label' for='{$id}' class='" . ($isRow ? 'col-sm-2 ' : '') . "col-form-label'>{$label}</label>
            " . ($isRow ? "<div class='col-sm-10'>" : '') . "
        " . $button . $hint . "
            " . ($isRow ? '</div>' : '') . "
        </div>";
    }

    /**
     * Returns the CSS class unique to the UI component
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }

    /**
     * A button is not bound to a field
     * @throws \LogicException always
     */
    public function setField(Field $field): ComponentInterface
    {
        throw new \LogicException('Invalid call: a button is not bound to a field');
    }

    /**
     * A button is not bound to a field
     */
    public function getField(): ?Field
    {
        return null;
    }
}
