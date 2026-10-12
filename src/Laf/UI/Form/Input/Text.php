<?php

namespace Laf\UI\Form\Input;

use Laf\Database\Field\Field;
use Laf\Database\Field\TypeBlob;
use Laf\Database\Field\TypeEnum;
use Laf\Database\Field\TypeJson;
use Laf\Database\Field\TypeUuid;
use Laf\Database\Field\TypeVarchar;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\FormRowDisplayMode;
use Laf\UI\Form\InputType;
use Laf\UI\Traits\ComponentTrait;
use Laf\UI\Traits\FormElementTrait;
use Laf\Util\Util;

/**
 * Base class of every form input: a Bootstrap 5 wrapper (container, label,
 * control, hint, validation feedback) around an <input>.
 *
 * Rendering never changes the element: draw() in one mode followed by draw()
 * in another gives the same result as drawing the second mode alone. Subclasses
 * override drawUpdateMode() to render their control and getDisplayValue() to
 * decide what VIEW mode shows; both reuse renderWrapper().
 *
 * Markup contract kept for the apps and their JavaScript: container id
 * "{id}_container", label id "{id}_label", hint id "{id}_hint", input id = name
 * = rot13(column), classes form-control / form-control-plaintext, mb-3,
 * col-form-label and col-sm-2 / col-sm-10 in "row" display mode.
 */
class Text implements FormElementInterface
{
    use FormElementTrait;
    use ComponentTrait;

    /** Text rendered after every label */
    protected static string $labelSuffix = ' :';

    /** Text rendered after the label of a required field (before the suffix) */
    protected static string $requiredMarker = '*';

    /** Attributes that are meaningful on the read-only view box too */
    private const VIEW_ATTRIBUTES = ['title', 'dir', 'lang'];

    /** Attribute prefixes copied to the view box */
    private const VIEW_ATTRIBUTE_PREFIXES = ['data-', 'aria-'];

    /** Attributes that only make sense on an <input>, never on <select>, <textarea> or a view box */
    protected const INPUT_ONLY_ATTRIBUTES = ['type', 'value', 'placeholder', 'maxlength', 'minlength', 'pattern', 'inputmode', 'step', 'min', 'max', 'size', 'autocomplete', 'multiple', 'accept', 'checked'];

    private ?Field $field = null;

    private ?string $invalidValueErrorMessage = null;

    public function __construct()
    {
        $this->setType(InputType::Text);
    }

    public function __toString(): string
    {
        return (string)$this->draw();
    }

    /* ───────────── process-wide label options ───────────── */

    public static function setLabelSuffix(string $suffix): void
    {
        static::$labelSuffix = $suffix;
    }

    public static function getLabelSuffix(): string
    {
        return static::$labelSuffix;
    }

    public static function setRequiredMarker(string $marker): void
    {
        static::$requiredMarker = $marker;
    }

    public static function getRequiredMarker(): string
    {
        return static::$requiredMarker;
    }

    /* ───────────── drawing ───────────── */

    /**
     * @param string|null $drawMode
     * @return Text
     */
    public function setDrawMode(?string $drawMode): ComponentInterface
    {
        $this->drawMode = $drawMode;
        return $this;
    }

    public function draw(): ?string
    {
        /**
         * add a css class to track component name
         */
        $this->addCssClass(static::getComponentCssControlClass());

        switch ($this->getDrawMode()) {
            case DrawMode::INSERT:
                return $this->drawInsertMode();
            case DrawMode::UPDATE:
                return $this->drawUpdateMode();
            case DrawMode::VIEW:
            default:
                return $this->drawViewMode();
        }
    }

    /**
     * Read-only presentation of the value: a bordered box instead of an input, no hint.
     */
    public function drawViewMode(): string
    {
        $this->addCssClass(static::getComponentCssControlClass());

        $classes = array_diff($this->getCssClasses(), ['form-control', 'is-invalid', 'form-select']);
        foreach (['form-control-plaintext', 'border', 'border-secondary', 'border-1', 'bg-light', 'rounded', 'p-2'] as $class) {
            if (!in_array($class, $classes, true)) {
                $classes[] = $class;
            }
        }
        $style = 'max-width:100%;overflow-wrap:break-word;word-break:break-all;' . $this->controlStyle();
        $value = $this->getDisplayValueForHtml();

        $control = "<div id='" . Util::e($this->getId()) . "' class='" . Util::e(join(' ', $classes)) . "' style='" . Util::e($style) . "'"
            . $this->renderAttributes($this->viewAttributes()) . ">"
            . ($value !== '' ? $value : '&nbsp;') . "</div>";

        return $this->renderWrapper($control, $this->viewWrapperOptions());
    }

    /**
     * renderWrapper() options for VIEW mode; Hidden adds 'hidden'.
     */
    protected function viewWrapperOptions(): array
    {
        return ['hint' => false, 'errors' => false, 'wrapperStyle' => 'min-width:0;'];
    }

    /**
     * Insert mode renders the same control as update mode.
     */
    protected function drawInsertMode(): string
    {
        return $this->drawUpdateMode();
    }

    /**
     * Editable presentation: the <input> with every attribute of the bag.
     */
    public function drawUpdateMode(): string
    {
        $this->addCssClass(static::getComponentCssControlClass());

        $control = "<input class='" . Util::e(join(' ', $this->controlClasses('form-control'))) . "' style='" . Util::e($this->controlStyle()) . "'"
            . $this->renderAttributes($this->controlAttributes())
            . $this->describedBy() . " />";

        return $this->renderWrapper($control);
    }

    /* ───────────── values ───────────── */

    /**
     * What VIEW mode shows. Subclasses map the stored value to something readable
     * (selected label, Yes/No, locale date); the base returns the value itself.
     */
    public function getDisplayValue(): ?string
    {
        return $this->getValue();
    }

    /**
     * The display value escaped for HTML with line breaks kept; raw when the field allows HTML.
     */
    public function getDisplayValueForHtml(): string
    {
        $value = $this->getDisplayValue();
        if ($value === null || $value === '') {
            return '';
        }
        $field = $this->getField();
        if ($field !== null && $field->allowHtml()) {
            return nl2br($value);
        }
        return nl2br(Util::e($value));
    }

    /**
     * Kept for compatibility: same as getDisplayValueForHtml().
     */
    public function getValueForHtml(): ?string
    {
        return $this->getDisplayValueForHtml();
    }

    /* ───────────── field binding ───────────── */

    public function getField(): ?Field
    {
        return $this->field;
    }

    public function setField(Field $field): ComponentInterface
    {
        $this->field = $field;
        $this->copyAttributesFromField();
        return $this;
    }

    /**
     * Copies attributes from Field to the input.
     * Field attributes are merged over the defaults the constructor set (the
     * "type" survives); explicit setters below win over the type's own attributes.
     * @return Text
     */
    protected function copyAttributesFromField()
    {
        $field = $this->getField();
        if ($field === null) {
            return $this;
        }

        $this->setAttributes(array_merge($this->getAttributes(), $field->getAttributes()));

        $this->setName($field->getNameRot13());
        $this->setId($field->getNameRot13());
        $this->setValue(static::valueToString($field->getValue()));

        if ($this->isStringField($field)) {
            if (is_numeric($field->getMaxLength()) && $field->getMaxLength() > 0) {
                $this->setMaxLength((int)$field->getMaxLength());
            }
            if (is_numeric($field->getMinLength()) && $field->getMinLength() > 0) {
                $this->setMinLength((int)$field->getMinLength());
            }
        }
        if (is_numeric($field->getMaxValue())) {
            $this->setMax($field->getMaxValue());
        }
        if (is_numeric($field->getMinValue())) {
            $this->setMin($field->getMinValue());
        }
        if ($field->isRequired()) {
            $this->setRequired(true);
        }
        $this->setLabel($field->getLabel());
        $this->setPlaceholder($field->getPlaceholder());
        $this->setHint($field->getHint());
        $this->setInvalidValueErrorMessage($field->getInvalidValueErrorMessage());

        /**
         * type-specific HTML attributes (step, min, max, pattern, inputmode…);
         * anything already set explicitly on the field wins
         */
        if ($field->hasType()) {
            foreach ($field->getType()->getInputAttributes($field) as $key => $value) {
                $current = $this->getAttribute($key);
                if ($current === null || $current === '') {
                    $this->addAttribute($key, (string)$value);
                }
            }
        }
        return $this;
    }

    public function getInvalidValueErrorMessage(): ?string
    {
        return $this->invalidValueErrorMessage;
    }

    public function setInvalidValueErrorMessage(?string $invalidValueErrorMessage): Text
    {
        $this->invalidValueErrorMessage = $invalidValueErrorMessage;
        return $this;
    }

    /**
     * Returns the CSS class unique to the UI component
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }

    /* ───────────── rendering helpers for subclasses ───────────── */

    /**
     * Wraps a rendered control in the standard container: label, column, hint and validation feedback.
     *
     * @param string $control the control HTML
     * @param array $options hint (bool), errors (bool), labelFor (string), wrapperStyle (string prefix), hidden (bool)
     */
    protected function renderWrapper(string $control, array $options = []): string
    {
        $showHint = $options['hint'] ?? true;
        $showErrors = $options['errors'] ?? true;
        $isRow = $this->getFormRowDisplayMode() === FormRowDisplayMode::ROW;
        $id = Util::e($this->getId());

        $hidden = ($options['hidden'] ?? false) || $this->isHidden() || $this->hasCssClass('d-none');
        $wrapperClasses = trim('mb-3 ' . (string)$this->getFormRowDisplayMode() . ' ' . $this->getWrapperCssClassesForHtml()) . ($hidden ? ' d-none' : '');
        $wrapperStyle = ($options['wrapperStyle'] ?? '') . $this->getWrapperCssStyleForHtml();

        $hint = '';
        if ($showHint && $this->hasHint()) {
            $hint = "\n\t\t\t\t<small id='{$id}_hint' class='form-text text-muted'>" . Util::e($this->getHint()) . "</small>";
        }
        $errors = '';
        if ($showErrors && $this->hasErrors()) {
            $errors = "\n\t\t\t\t<div id='{$id}_feedback' class='invalid-feedback d-block'>" . join('<br />', array_map([Util::class, 'e'], $this->getErrors())) . "</div>";
        }

        return "
        <div id='{$id}_container' style='" . Util::e($wrapperStyle) . "' class='" . Util::e($wrapperClasses) . "'>
            " . $this->renderLabel($isRow, $options['labelFor'] ?? null) . "
            " . ($isRow ? "<div class='col-sm-10'>" : '') . "
                {$control}{$errors}{$hint}
            " . ($isRow ? '</div>' : '') . "
        </div>";
    }

    /**
     * The <label> for the control.
     */
    protected function renderLabel(bool $isRow, ?string $for = null): string
    {
        $id = Util::e($this->getId());
        $text = Util::e($this->getLabel())
            . ($this->isRequired() ? Util::e(static::$requiredMarker) : '')
            . Util::e(static::$labelSuffix);
        return "<label id='{$id}_label' for='" . Util::e($for ?? $this->getId()) . "' class='" . ($isRow ? 'col-sm-2 ' : '') . "col-form-label'>{$text}</label>";
    }

    /**
     * Attributes rendered as key="value" (double quoted, escaped), each on its own line.
     * Empty and null values are skipped; arrays are joined with spaces.
     */
    protected function renderAttributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $key => $value) {
            if (is_array($value)) {
                $value = join(' ', array_map('strval', $value));
            }
            if ($value === null || (string)$value === '') {
                continue;
            }
            if (!preg_match('/^[A-Za-z_:][A-Za-z0-9_:.\-]*$/', (string)$key)) {
                continue;
            }
            $html .= "\n\t\t\t\t" . $key . '="' . Util::attr($value) . '"';
        }
        return $html;
    }

    /**
     * The attribute bag without what is rendered separately (class, style) or turned into style (height, width).
     * @param string[] $without further attributes to drop
     */
    protected function controlAttributes(array $without = []): array
    {
        $attributes = $this->getAttributes();
        foreach (array_merge(['class', 'style', 'height', 'width'], $without) as $key) {
            unset($attributes[$key]);
        }
        return $attributes;
    }

    /**
     * Attributes that may appear on the read-only view box.
     */
    protected function viewAttributes(): array
    {
        $attributes = [];
        foreach ($this->getAttributes() as $key => $value) {
            $key = (string)$key;
            $keep = in_array($key, self::VIEW_ATTRIBUTES, true);
            foreach (self::VIEW_ATTRIBUTE_PREFIXES as $prefix) {
                if (strncmp($key, $prefix, strlen($prefix)) === 0) {
                    $keep = true;
                }
            }
            if ($keep) {
                $attributes[$key] = $value;
            }
        }
        return $attributes;
    }

    /**
     * The css classes of the control, plus the given defaults, plus is-invalid when there are errors.
     * @param string ...$defaults
     * @return string[]
     */
    protected function controlClasses(string ...$defaults): array
    {
        $classes = $this->getCssClasses();
        foreach ($defaults as $class) {
            if ($class !== '' && !in_array($class, $classes, true)) {
                $classes[] = $class;
            }
        }
        if ($this->hasErrors() && !in_array('is-invalid', $classes, true)) {
            $classes[] = 'is-invalid';
        }
        return array_values($classes);
    }

    /**
     * The inline style of the control: the style items plus height/width attributes as px.
     */
    protected function controlStyle(): string
    {
        $style = $this->getCssStyles();
        if ($this->getHeight()) {
            $style['height'] = $this->getHeight() . 'px';
        }
        if ($this->getWidth()) {
            $style['width'] = $this->getWidth() . 'px';
        }
        $items = [];
        foreach ($style as $property => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $items[] = $property . ':' . $value;
        }
        return join(';', $items);
    }

    /**
     * aria-describedby pointing at the hint, when there is one.
     */
    protected function describedBy(): string
    {
        return $this->hasHint() ? " aria-describedby='" . Util::e($this->getId()) . "_hint'" : '';
    }

    protected function hasHint(): bool
    {
        return mb_strlen($this->getHint() ?? '') > 0;
    }

    /**
     * The current value as a list, for multi-valued controls (comma separated storage).
     * @return string[]
     */
    protected function getSelectedValues(): array
    {
        $value = $this->getValue();
        if ($value === null || $value === '') {
            return [];
        }
        if ($this->isMultiValued()) {
            return array_values(array_filter(array_map('trim', explode(',', $value)), fn($v) => $v !== ''));
        }
        return [$value];
    }

    /**
     * Whether the value is a comma separated list of several selections.
     */
    protected function isMultiValued(): bool
    {
        return $this->isMultiple();
    }

    /**
     * A field value as the string an attribute can carry: bools as 1/0, arrays and objects as JSON.
     */
    public static function valueToString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return (string)$value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? null : $encoded;
    }

    /**
     * maxlength/minlength only make sense for character columns.
     */
    private function isStringField(Field $field): bool
    {
        if (!$field->hasType()) {
            return true;
        }
        $type = $field->getType();
        // TypeChar and TypeText extend TypeVarchar
        return $type instanceof TypeVarchar
            || $type instanceof TypeJson
            || $type instanceof TypeUuid
            || $type instanceof TypeEnum
            || $type instanceof TypeBlob;
    }
}
