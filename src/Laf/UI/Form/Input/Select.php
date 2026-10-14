<?php

namespace Laf\UI\Form\Input;

use Laf\Database\Db;
use Laf\Database\Identifier;
use Laf\UI\Form\FormElementInterface;
use Laf\Util\Util;

/**
 * <select> whose options come from a static list (setOptions(), ENUM columns)
 * or from the table the field's foreign key references: "key => display field",
 * filtered by the field's db selection criteria, ordered by the display field.
 * Options are loaded once per element and per request.
 *
 * setMultiple(true) posts the field as an array (a hidden empty value first, so
 * clearing every selection is submitted too); Form joins it with commas.
 */
class Select extends Text implements FormElementInterface
{
    /**
     * @deprecated use inputOnlyAttributesExcept('size', 'autocomplete', 'multiple'); kept for subclasses
     * attributes that belong to <input> and must not be printed on a <select> or a choice list
     */
    protected const NON_SELECT_ATTRIBUTES = ['type', 'value', 'placeholder', 'maxlength', 'minlength', 'pattern', 'inputmode', 'step', 'min', 'max', 'accept', 'checked'];

    /**
     * @var array|null static option list (value => label); when set, the
     * foreign-key lookup is skipped. Used for ENUM columns and hand-built selects.
     */
    private ?array $options = null;

    /** @var array|null options read from the referenced table, cached per request */
    private ?array $loadedOptions = null;

    /** Whether the empty "no selection" option is rendered */
    private bool $emptyOption = true;

    /** Text of the empty option; null renders a blank option */
    private ?string $emptyOptionLabel = null;

    /** Radio / CheckboxCollection: choices side by side */
    private bool $inline = false;

    /**
     * Provide the options directly instead of reading them from the FK table
     * @param array $options value => label
     * @return static
     */
    public function setOptions(array $options): static
    {
        $this->options = $options;
        $this->loadedOptions = null;
        return $this;
    }

    public function hasStaticOptions(): bool
    {
        return $this->options !== null;
    }

    public function getStaticOptions(): ?array
    {
        return $this->options;
    }

    /**
     * Returns all options: the static list, or the referenced table's rows (cached).
     * @return array value => label
     * @throws \Exception
     */
    public function getOptions(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }
        if ($this->loadedOptions === null) {
            $this->loadedOptions = $this->loadOptionsFromReferencedTable();
        }
        return $this->loadedOptions;
    }

    /**
     * Forget the options read from the referenced table so the next draw reloads them.
     */
    public function clearOptionsCache(): static
    {
        $this->loadedOptions = null;
        return $this;
    }

    /**
     * Render (default) or omit the empty first option.
     */
    public function setEmptyOption(bool $render): static
    {
        $this->emptyOption = $render;
        return $this;
    }

    public function hasEmptyOption(): bool
    {
        return $this->emptyOption;
    }

    /**
     * Text of the empty option, e.g. '-- choose --'; null renders a blank option.
     */
    public function setEmptyOptionLabel(?string $label): static
    {
        $this->emptyOptionLabel = $label;
        return $this;
    }

    public function getEmptyOptionLabel(): ?string
    {
        return $this->emptyOptionLabel;
    }

    /**
     * Several selections only on a column that stores a list: a foreign key holds one key
     * (several keys come from a linker table, see ManyToManyCheckboxes / ManyToManyAutocomplete)
     */
    public function isMultiple(): bool
    {
        return parent::isMultiple() && !$this->isForeignKeyBound();
    }

    /**
     * Whether the element is bound to a foreign-key column
     */
    protected function isForeignKeyBound(): bool
    {
        $field = $this->getField();
        return $field !== null && $field->hasTable() && $field->isForeignKey();
    }

    public function setInline(bool $inline): static
    {
        $this->inline = $inline;
        return $this;
    }

    public function isInline(): bool
    {
        return $this->inline;
    }

    /**
     * VIEW mode shows the label(s) of the selected value(s)
     */
    public function getDisplayValue(): ?string
    {
        return $this->getSelectedLabel();
    }

    /**
     * Returns the label from the FK table, or from the static option list;
     * several labels joined with ", " for multi-valued controls.
     */
    public function getSelectedLabel(): string
    {
        $value = $this->getValue();
        if ($value === null || $value === '') {
            return '';
        }
        if (!$this->isMultiValued()) {
            return $this->labelFor($value);
        }
        return join(', ', array_map([$this, 'labelFor'], $this->getSelectedValues()));
    }

    /**
     * Label of one option value; the value itself when it is not an option.
     */
    protected function labelFor(string $value): string
    {
        if ($this->options !== null) {
            return isset($this->options[$value]) ? (string)$this->options[$value] : $value;
        }
        $field = $this->getField();
        if ($field !== null && $field->hasTable() && $field->isForeignKey() && static::valueToString($field->getValue()) === $value) {
            $label = static::valueToString($field->getReferencedValue());
            return $label === null ? $value : $label;
        }
        $options = $this->getOptions();
        return isset($options[$value]) ? (string)$options[$value] : $value;
    }

    public function drawUpdateMode(): string
    {
        $this->addCssClass(static::getComponentCssControlClass());

        $attributes = $this->controlAttributes($this->inputOnlyAttributesExcept('size', 'autocomplete', 'multiple'));
        $hidden = '';
        if ($this->isMultiple() && isset($attributes['name'])) {
            $attributes['name'] = $attributes['name'] . '[]';
            if (!$this->isDisabled()) {
                /**
                 * a multiple select with nothing selected posts nothing: the empty value
                 * tells Form the field was on the form and is now empty
                 */
                $hidden = "<input type='hidden' name='" . Util::e($attributes['name']) . "' value='' />\n\t\t\t\t";
            }
        }

        $control = $hidden . "<select class='" . Util::e(join(' ', $this->controlClasses('form-control', 'form-select'))) . "' style='" . Util::e($this->controlStyle()) . "'"
            . $this->renderAttributes($attributes)
            . $this->describedBy() . ">"
            . $this->renderOptions()
            . "\n\t\t\t\t</select>";

        return $this->renderWrapper($control);
    }

    /**
     * The <option> list with the current value(s) selected.
     */
    protected function renderOptions(): string
    {
        $selected = $this->getSelectedValues();
        $html = '';
        if ($this->emptyOption && !$this->isMultiple()) {
            $label = ($this->emptyOptionLabel === null || $this->emptyOptionLabel === '') ? '&nbsp;' : Util::e($this->emptyOptionLabel);
            $html .= "<option value=''>{$label}</option>";
        }
        foreach ($this->getOptions() as $ok => $ov) {
            $okHtml = Util::e($ok);
            $ovHtml = Util::e($ov);
            $sel = in_array((string)$ok, $selected, true) ? " selected='selected'" : '';
            $html .= "\n\t\t\t\t<option value='{$okHtml}'{$sel}>{$ovHtml}</option>";
        }
        return $html;
    }

    /**
     * One radio / checkbox per option, Bootstrap "form-check" markup, used by Radio and CheckboxCollection.
     *
     * "required" stays on radios (one checked satisfies it) and is dropped from a checkbox
     * list, where the browser would demand every box ticked.
     *
     * @param string $inputType 'radio' or 'checkbox'
     * @param bool $asList post as name[] and start with an empty hidden value so "nothing selected" is submitted too
     */
    protected function renderChoiceList(string $inputType, bool $asList): string
    {
        $this->addCssClass(static::getComponentCssControlClass());

        $drop = array_merge(static::INPUT_ONLY_ATTRIBUTES, ['id', 'name']);
        if ($asList) {
            $drop[] = 'required';
        }
        $attributes = $this->controlAttributes($drop);
        $classes = join(' ', array_values(array_diff($this->controlClasses('form-check-input'), ['form-control', 'form-select'])));
        $style = $this->controlStyle();
        $selected = $this->getSelectedValues();
        $id = (string)$this->getId();
        $name = (string)$this->getName() . ($asList ? '[]' : '');

        $items = '';
        if ($asList && !$this->isDisabled() && $name !== '[]') {
            $items .= "\n\t\t\t\t<input type='hidden' name='" . Util::e($name) . "' value='' />";
        }
        $n = 0;
        $firstId = null;
        foreach ($this->getOptions() as $ok => $ov) {
            $n++;
            $optionId = $id . '_' . $n;
            if ($firstId === null) {
                $firstId = $optionId;
            }
            $checked = in_array((string)$ok, $selected, true) ? ' checked="checked"' : '';
            $items .= "\n\t\t\t\t<div class='form-check" . ($this->inline ? ' form-check-inline' : '') . "'>"
                . "\n\t\t\t\t\t<input type='" . Util::e($inputType) . "' id='" . Util::e($optionId) . "' name='" . Util::e($name) . "' value='" . Util::e($ok) . "' class='" . Util::e($classes) . "' style='" . Util::e($style) . "'"
                . $this->renderAttributes($attributes) . $checked . " />"
                . "\n\t\t\t\t\t<label class='form-check-label' for='" . Util::e($optionId) . "'>" . Util::e($ov) . "</label>"
                . "\n\t\t\t\t</div>";
        }

        $control = "<div id='" . Util::e($id) . "' class='laf-choice-list'" . $this->describedBy() . ">" . $items . "\n\t\t\t\t</div>";
        return $this->renderWrapper($control, ['labelFor' => $firstId]);
    }

    /**
     * Reads "key => display" rows from the table the foreign key references.
     * Identifiers are quoted for the configured engine (MySQL backticks, PostgreSQL double quotes).
     * @return array
     * @throws \Exception
     */
    protected function loadOptionsFromReferencedTable(): array
    {
        $field = $this->getField();
        if ($field === null || !$field->hasTable()) {
            return [];
        }
        $fk = $field->getTable()->getForeignKey($field->getName());
        if ($fk === null) {
            return [];
        }
        $meta = $fk->getReferencedTableMeta();
        if ($meta === null) {
            return [];
        }
        $displayField = $meta->getDisplayField();
        if ($displayField === null) {
            return [];
        }
        $display = $displayField->getName();
        $key = $fk->resolveReferencedFieldName();
        $table = (string)$fk->getReferencingTable();

        $where = '';
        $params = [];
        if ($field->hasDbSelectionCriteria()) {
            $i = 0;
            foreach ($field->getDbSelectionCriteria() as $column => $value) {
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string)$column)) {
                    continue;
                }
                $placeholder = ':crit' . $i++;
                $where .= ' AND ' . Identifier::quote((string)$column) . ' = ' . $placeholder;
                $params[$placeholder] = $value;
            }
        }

        $sql = 'SELECT ' . Identifier::quote($key) . ', ' . Identifier::quote($display)
            . ' FROM ' . Identifier::quote($table)
            . ' WHERE 1=1' . $where
            . ' ORDER BY ' . Identifier::quote($display) . ' ASC';

        $options = [];
        foreach (Db::getInstance()->fetchAllNum($sql, $params) as $row) {
            $options[static::valueToString($row[0]) ?? ''] = static::valueToString($row[1]) ?? '';
        }
        return $options;
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
