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
use Laf\UI\Traits\HtmlAttributeRenderingTrait;
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
 * Extras every input inherits: setPrefix()/setSuffix() (an input group with
 * text addons around the control in edit modes; the *Html variants take raw
 * markup), setSuggestions() (a <datalist> the browser offers while typing).
 *
 * Markup contract kept for the apps and their JavaScript: container id
 * "{id}_container", label id "{id}_label", hint id "{id}_hint", feedback id
 * "{id}_feedback", input id = name = rot13(column), classes form-control /
 * form-control-plaintext, mb-3, col-form-label and col-sm-2 / col-sm-10 in
 * "row" display mode.
 */
class Text implements FormElementInterface
{
    use FormElementTrait;
    use ComponentTrait;
    use HtmlAttributeRenderingTrait;

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

    /** HTML of the addon before the control (null = none) */
    private ?string $prefixHtml = null;

    /** HTML of the addon after the control (null = none) */
    private ?string $suffixHtml = null;

    /** @var string[] values of the <datalist> offered while typing */
    private array $suggestions = [];

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
     * @return static
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
        return ['hint' => false, 'errors' => false, 'addons' => false, 'suggestions' => false, 'wrapperStyle' => 'min-width:0;'];
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
            . $this->renderAttributes($this->withSuggestionList($this->controlAttributes()))
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
    public function getValueForHtml(): string
    {
        return $this->getDisplayValueForHtml();
    }

    /* ───────────── addons and suggestions ───────────── */

    /**
     * Text shown in an addon before the control (an input group), e.g. a currency sign; null removes it.
     */
    public function setPrefix(?string $text): static
    {
        $this->prefixHtml = ($text === null || $text === '') ? null : Util::e($text);
        return $this;
    }

    /**
     * Raw HTML of the addon before the control (a button, an icon); not escaped.
     */
    public function setPrefixHtml(?string $html): static
    {
        $this->prefixHtml = ($html === null || $html === '') ? null : $html;
        return $this;
    }

    /**
     * The HTML of the prefix addon, null when there is none
     */
    public function getPrefix(): ?string
    {
        return $this->prefixHtml;
    }

    /**
     * Text shown in an addon after the control, e.g. a unit; null removes it.
     */
    public function setSuffix(?string $text): static
    {
        $this->suffixHtml = ($text === null || $text === '') ? null : Util::e($text);
        return $this;
    }

    /**
     * Raw HTML of the addon after the control; not escaped.
     */
    public function setSuffixHtml(?string $html): static
    {
        $this->suffixHtml = ($html === null || $html === '') ? null : $html;
        return $this;
    }

    /**
     * The HTML of the suffix addon, null when there is none
     */
    public function getSuffix(): ?string
    {
        return $this->suffixHtml;
    }

    public function hasAddons(): bool
    {
        return $this->prefixHtml !== null || $this->suffixHtml !== null;
    }

    /**
     * Values the browser offers while typing (a <datalist>); an empty list removes it.
     * @param array<int|string, scalar|null> $values
     */
    public function setSuggestions(array $values): static
    {
        $clean = [];
        foreach ($values as $value) {
            $value = (string)static::valueToString($value);
            if ($value !== '' && !in_array($value, $clean, true)) {
                $clean[] = $value;
            }
        }
        $this->suggestions = $clean;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    public function hasSuggestions(): bool
    {
        return $this->suggestions !== [];
    }

    /**
     * Id of the <datalist> element
     */
    public function getSuggestionListId(): string
    {
        return (string)$this->getId() . '_list';
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
     * @return static
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

    public function setInvalidValueErrorMessage(?string $invalidValueErrorMessage): static
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
     * Wraps a rendered control in the standard container: label, column, addons, hint and validation feedback.
     *
     * @param string $control the control HTML
     * @param array $options hint (bool), errors (bool), addons (bool), suggestions (bool), labelFor (string),
     *                       wrapperStyle (string prefix), hidden (bool)
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

        if (($options['addons'] ?? true) && $this->hasAddons()) {
            $control = $this->renderInputGroup($control);
        }
        if (($options['suggestions'] ?? true) && $this->hasSuggestions()) {
            $control .= $this->renderSuggestionList();
        }

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
     * The control inside a Bootstrap input group with the prefix / suffix addons.
     */
    protected function renderInputGroup(string $control): string
    {
        $html = "<div class='input-group'>";
        if ($this->prefixHtml !== null) {
            $html .= "<span class='input-group-text'>" . $this->prefixHtml . "</span>";
        }
        $html .= $control;
        if ($this->suffixHtml !== null) {
            $html .= "<span class='input-group-text'>" . $this->suffixHtml . "</span>";
        }
        return $html . "</div>";
    }

    /**
     * The <datalist> of the suggestions (empty string without suggestions).
     */
    protected function renderSuggestionList(): string
    {
        if (!$this->hasSuggestions()) {
            return '';
        }
        $html = "\n\t\t\t\t<datalist id='" . Util::e($this->getSuggestionListId()) . "'>";
        foreach ($this->suggestions as $value) {
            $html .= "<option value='" . Util::e($value) . "'></option>";
        }
        return $html . "</datalist>";
    }

    /**
     * The attributes with "list" pointing at the datalist when there are suggestions.
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function withSuggestionList(array $attributes): array
    {
        if ($this->hasSuggestions() && empty($attributes['list'])) {
            $attributes['list'] = $this->getSuggestionListId();
        }
        return $attributes;
    }

    /**
     * The <label> for the control: text, required marker and suffix; empty when the element has no label.
     */
    protected function renderLabel(bool $isRow, ?string $for = null): string
    {
        $id = Util::e($this->getId());
        $label = $this->getLabel();
        $text = '';
        if ($label !== null && $label !== '') {
            $text = Util::e($label)
                . ($this->isRequired() ? Util::e(static::$requiredMarker) : '')
                . Util::e(static::$labelSuffix);
        }
        return "<label id='{$id}_label' for='" . Util::e($for ?? $this->getId()) . "' class='" . ($isRow ? 'col-sm-2 ' : '') . "col-form-label'>{$text}</label>";
    }

    /**
     * The input-only attributes (INPUT_ONLY_ATTRIBUTES) minus the ones a control keeps:
     * the drop list for controlAttributes() of a <select>, <textarea> or a special input.
     * @return string[]
     */
    protected function inputOnlyAttributesExcept(string ...$keep): array
    {
        return array_values(array_diff(static::INPUT_ONLY_ATTRIBUTES, $keep));
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
     * aria-describedby pointing at the hint and the validation feedback (when present),
     * plus aria-invalid when the element has errors.
     */
    protected function describedBy(): string
    {
        $id = Util::e($this->getId());
        $ids = [];
        if ($this->hasHint()) {
            $ids[] = $id . '_hint';
        }
        if ($this->hasErrors()) {
            $ids[] = $id . '_feedback';
        }
        $html = $ids === [] ? '' : " aria-describedby='" . join(' ', $ids) . "'";
        if ($this->hasErrors()) {
            $html .= " aria-invalid='true'";
        }
        return $html;
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
