<?php

declare(strict_types=1);

namespace Laf\UI\Form\Input;

use Laf\Util\Util;

/**
 * The many-to-many picker as two lists: "Available" rows of the other table on one side, the chosen ones on
 * the other, moved across with Add / Remove / Add all / Remove all, a double click, Enter (available) or
 * Delete (selected). A filter box narrows the available list; with setAllowCreate(true) a "New entry" box under
 * it adds a row to the other table and moves it across (same create request as the autocomplete).
 * The chosen keys post as name[] (after an empty hidden value), in the order of the selected list.
 * Side by side from the md breakpoint, stacked on phones.
 *
 *   column comment on the linker column: {"form":{"element":"DualList","create":true}}
 *   $layout->element('person_group', 'DualList');
 *   $person->manyToMany('person_group')->getFormElement('DualList')->setSize(10);
 *
 * Every row of the other table is listed: meant for tables of up to a few hundred rows (chips search instead).
 */
class ManyToManyDualList extends ManyToManyAutocomplete
{
    /** @var array<string, string> */
    private array $texts = [
        'available' => 'Available',
        'selected' => 'Selected',
        'filter' => 'Filter',
        'add' => 'Add',
        'addAll' => 'Add all',
        'remove' => 'Remove',
        'removeAll' => 'Remove all',
        'newEntry' => 'New entry',
        'create' => 'Add new',
    ];

    /** @var array<string, string>|null */
    private ?array $relationOptions = null;

    /**
     * Replace captions (available, selected, filter, add, addAll, remove, removeAll, newEntry, create)
     * @param array<string, string> $texts
     */
    public function setTexts(array $texts): static
    {
        foreach ($texts as $key => $text) {
            if (array_key_exists((string)$key, $this->texts)) {
                $this->texts[(string)$key] = (string)$text;
            }
        }
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getTexts(): array
    {
        return $this->texts;
    }

    /**
     * Every row of the other table, ordered by label (setOptions() replaces them)
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        if ($this->hasStaticOptions()) {
            return (array)$this->getStaticOptions();
        }
        if ($this->relationOptions === null) {
            $relation = $this->getRelation();
            $this->relationOptions = $relation !== null ? $relation->options() : [];
        }
        return $this->relationOptions;
    }

    protected function labelFor(string $value): string
    {
        $options = $this->getOptions();
        return isset($options[$value]) ? (string)$options[$value] : $value;
    }

    public function drawUpdateMode(): string
    {
        $this->addCssClass(static::getComponentCssControlClass());

        $id = (string)$this->getId();
        $postName = (string)$this->getName() . '[]';
        $disabled = $this->isDisabled() ? ' disabled="disabled"' : '';
        $size = max(3, (int)($this->getSize() ?? 8));
        $t = fn(string $key): string => Util::e($this->texts[$key]);

        $chosen = $this->getSelectedIds();
        $options = $this->getOptions();
        $position = array_flip(array_map('strval', array_keys($options)));

        $available = '';
        foreach ($options as $value => $label) {
            $value = (string)$value;
            if (!in_array($value, $chosen, true)) {
                $available .= "\n\t\t\t\t\t\t<option value=\"" . Util::attr($value) . "\" data-position=\"" . $position[$value] . "\">" . Util::e((string)$label) . "</option>";
            }
        }
        $selected = '';
        $hidden = '';
        foreach ($chosen as $value) {
            $selected .= "\n\t\t\t\t\t\t<option value=\"" . Util::attr($value) . "\" data-position=\"" . ($position[$value] ?? count($position)) . "\">" . Util::e($this->labelFor($value)) . "</option>";
            $hidden .= "<input type=\"hidden\" name=\"" . Util::attr($postName) . "\" value=\"" . Util::attr($value) . "\"" . $disabled . " />";
        }

        $selectClasses = join(' ', array_values(array_diff($this->controlClasses('form-select'), ['form-control'])));
        $button = fn(string $class, string $key): string => "<button type=\"button\" class=\"btn btn-outline-secondary btn-sm laf-duallist-{$class}\"" . $disabled . ">" . $t($key) . "</button>";

        $create = '';
        if ($this->isCreateAllowed() && !$this->isDisabled()) {
            $create = "\n\t\t\t\t\t<div class=\"input-group input-group-sm mt-1\">"
                . "<input type=\"text\" class=\"form-control laf-duallist-new\" id=\"" . Util::attr($id . '_new') . "\" placeholder=\"" . $t('newEntry') . "\" aria-label=\"" . $t('newEntry') . "\" maxlength=\"" . self::MAX_TERM_LENGTH . "\" />"
                . "<button type=\"button\" class=\"btn btn-outline-primary laf-duallist-create\">" . $t('create') . "</button></div>"
                . "\n\t\t\t\t\t<div class=\"small text-danger laf-duallist-error\" role=\"alert\"></div>";
        }

        $data = [
            'data-laf-autocomplete' => $this->getLookupKey(),
            'data-name' => $postName,
            'data-max-items' => $this->getMaxItems() !== null ? (string)$this->getMaxItems() : null,
            'data-url' => $this->getLookupUrl(),
        ] + $this->createAttributes();

        $box = "<div class=\"laf-duallist\" id=\"" . Util::attr($id . '_box') . "\"" . $this->renderAttributes($data) . ">"
            . "\n\t\t\t\t<input type=\"hidden\" class=\"laf-duallist-empty\" name=\"" . Util::attr($postName) . "\" value=\"\"" . $disabled . " />"
            . "\n\t\t\t\t<div class=\"laf-duallist-values\">" . $hidden . "</div>"
            . "\n\t\t\t\t<div class=\"row g-2 align-items-center\">"
            . "\n\t\t\t\t\t<div class=\"col-md\">"
            . "\n\t\t\t\t\t<label class=\"form-label small text-muted mb-1\" for=\"" . Util::attr($id . '_available') . "\">" . $t('available') . "</label>"
            . "\n\t\t\t\t\t<input type=\"search\" class=\"form-control form-control-sm mb-1 laf-duallist-filter\" placeholder=\"" . $t('filter') . "\" aria-label=\"" . $t('filter') . "\" aria-controls=\"" . Util::attr($id . '_available') . "\"" . $disabled . " />"
            . "\n\t\t\t\t\t<select multiple size=\"" . $size . "\" id=\"" . Util::attr($id . '_available') . "\" class=\"form-select laf-duallist-available\"" . $disabled . ">" . $available . "\n\t\t\t\t\t</select>"
            . $create
            . "\n\t\t\t\t\t</div>"
            . "\n\t\t\t\t\t<div class=\"col-md-auto d-flex flex-wrap flex-md-column gap-1 justify-content-center\">"
            . $button('add', 'add') . $button('add-all', 'addAll') . $button('remove', 'remove') . $button('remove-all', 'removeAll')
            . "</div>"
            . "\n\t\t\t\t\t<div class=\"col-md\">"
            . "\n\t\t\t\t\t<label class=\"form-label small text-muted mb-1\" for=\"" . Util::attr($id) . "\">" . $t('selected') . "</label>"
            . "\n\t\t\t\t\t<select multiple size=\"" . $size . "\" id=\"" . Util::attr($id) . "\" class=\"" . Util::attr($selectClasses . ' laf-duallist-selected') . "\"" . $disabled
            . $this->describedBy() . ">" . $selected . "\n\t\t\t\t\t</select>"
            . "\n\t\t\t\t\t</div>"
            . "\n\t\t\t\t</div>"
            . "\n\t\t\t\t</div>";

        return $this->renderWrapper($box, ['suggestions' => false, 'addons' => false]) . $this->drawScriptOnce();
    }

    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }
}
