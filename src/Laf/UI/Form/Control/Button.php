<?php

namespace Laf\UI\Form\Control;

use Laf\Database\Field\Field;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\FormRowDisplayMode;
use Laf\UI\Form\Input\Text;
use Laf\UI\Form\InputType;
use Laf\UI\Traits\ComponentTrait;
use Laf\UI\Traits\FormElementTrait;
use Laf\UI\Traits\HtmlAttributeRenderingTrait;
use Laf\Util\Util;

/**
 * <button>: the value is the caption, setIcon() puts an icon (css class) before
 * it. By default it is wrapped like an input (container, label column);
 * setWrapInDiv(false) renders the bare button, as modal footers do. A button is
 * not bound to a field: getField() is null.
 */
class Button implements FormElementInterface
{
    use ComponentTrait;
    use FormElementTrait;
    use HtmlAttributeRenderingTrait;

    protected bool $wrapInDiv = true;

    protected string $icon = '';

    public function __construct()
    {
        $this->setType(InputType::Button);
    }

    public function getWrapInDiv(): bool
    {
        return $this->wrapInDiv;
    }

    public function setWrapInDiv(bool $wrapInDiv): static
    {
        $this->wrapInDiv = $wrapInDiv;
        return $this;
    }

    /**
     * Css class(es) of an icon drawn before the caption, e.g. 'fa fa-save'; '' for none
     */
    public function setIcon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function getIcon(): string
    {
        return $this->icon;
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

        $id = Util::e($this->getId());
        $hasHint = mb_strlen($this->getHint() ?? '') > 0;
        $caption = ($this->icon !== '' ? "<i class='" . Util::e($this->icon) . "'></i> " : '') . $this->getValueForHtml();

        $button = "\t<button" . $this->renderAttributes($this->controlAttributes(['value'])) . "
                    class='" . Util::e($this->getCssClassesForHtml()) . "'
                    style='" . Util::e($this->controlStyle()) . "'"
            . ($hasHint ? " aria-describedby='{$id}_hint'" : '') . "
                >" . $caption . "</button>";

        if (!$this->getWrapInDiv()) {
            return $button;
        }

        $isRow = $this->getFormRowDisplayMode() === FormRowDisplayMode::ROW;
        $hidden = ($this->isHidden() || $this->hasCssClass('d-none')) ? ' d-none' : '';
        $label = $this->getLabel();
        $text = '';
        if ($label !== null && $label !== '') {
            // the caption carries the action, so no label suffix; the marker follows the inputs' setting
            $text = Util::e($label) . ($this->isRequired() ? Util::e(Text::getRequiredMarker()) : '');
        }
        $for = $id !== '' ? " for='{$id}'" : '';
        $hint = $hasHint ? "\n\t\t\t\t<small id='{$id}_hint' class='form-text text-muted'>" . Util::e($this->getHint()) . "</small>" : '';

        return "
        <div id='{$id}_container' class='mb-3 " . Util::e((string)$this->getFormRowDisplayMode()) . $hidden . "'>
            <label id='{$id}_label'{$for} class='" . ($isRow ? 'col-sm-2 ' : '') . "col-form-label'>{$text}</label>
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
