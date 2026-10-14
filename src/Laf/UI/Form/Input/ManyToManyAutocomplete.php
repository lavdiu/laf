<?php

declare(strict_types=1);

namespace Laf\UI\Form\Input;

use Laf\Database\Table;
use Laf\UI\Form\RelationElementInterface;
use Laf\Util\Util;

/**
 * The many-to-many picker for a big other table: the chosen rows are chips (a badge with a remove
 * button) in front of a search box that looks up the other table like Autocomplete. Each chip posts
 * its key as name[] (after an empty hidden value, so removing every chip is submitted too).
 * Backspace in the empty box removes the last chip; chosen rows are left out of the results.
 *
 *   $person->manyToMany('person_group')->getFormElement('Autocomplete')->setMaxItems(5);
 *
 * Events on the box: laf:autocomplete:add / laf:autocomplete:remove with detail {value, label}.
 */
class ManyToManyAutocomplete extends Autocomplete implements RelationElementInterface
{
    use RelationElementTrait;

    /** "linker_table.other_column" */
    public function getLookupKey(): string
    {
        $relation = $this->getRelation();
        return $relation !== null ? $relation->getName() . '.' . $relation->getOtherColumn() : (string)$this->getName();
    }

    /**
     * The other table of the relation, its key and its display column
     * @return array{0: ?Table, 1: string, 2: string}
     */
    protected function referencedColumns(): array
    {
        $relation = $this->getRelation();
        if ($relation === null) {
            return [null, '', ''];
        }
        try {
            return [$relation->otherMeta(), $relation->getOtherKeyColumn(), $relation->getOtherDisplayColumn()];
        } catch (\Throwable $ex) {
            return [null, '', ''];
        }
    }

    protected function createClass(): ?string
    {
        $relation = $this->getRelation();
        try {
            return $relation !== null ? $relation->getOtherClass() : null;
        } catch (\Throwable $ex) {
            return null;
        }
    }

    protected function selectionCriteria(): array
    {
        return [];
    }

    public function drawUpdateMode(): string
    {
        $this->addCssClass(static::getComponentCssControlClass());

        $id = (string)$this->getId();
        $postName = (string)$this->getName() . '[]';
        $disabled = $this->isDisabled() ? ' disabled="disabled"' : '';

        $chips = '';
        foreach ($this->getSelectedIds() as $value) {
            $label = $this->labelFor($value);
            $chips .= "\n\t\t\t\t\t<span class=\"badge bg-secondary laf-autocomplete-chip d-inline-flex align-items-center\" data-value=\"" . Util::attr($value) . "\">"
                . "<span class=\"laf-autocomplete-chip-label\">" . Util::e($label) . "</span>"
                . ($this->isDisabled() ? '' : "<button type=\"button\" class=\"btn-close btn-close-white ms-1 laf-autocomplete-remove\" style=\"font-size:.6em\" aria-label=\"" . Util::attr('Remove ' . $label) . "\"></button>")
                . "<input type=\"hidden\" name=\"" . Util::attr($postName) . "\" value=\"" . Util::attr($value) . "\"" . $disabled . " /></span>";
        }

        $attributes = $this->controlAttributes(['name', 'value', 'type', 'multiple', 'size', 'list', 'required']);
        $attributes['autocomplete'] = 'off';
        $attributes['role'] = 'combobox';
        $attributes['aria-autocomplete'] = 'list';
        $attributes['aria-expanded'] = 'false';
        $attributes['aria-controls'] = $id . '_listbox';

        $empty = "<input type=\"hidden\" class=\"laf-autocomplete-empty\" name=\"" . Util::attr($postName) . "\" value=\"\"" . $disabled . " />";
        $chipList = "<div class=\"laf-autocomplete-chips d-flex flex-wrap gap-1 mb-1\" id=\"" . Util::attr($id . '_chips') . "\" aria-live=\"polite\">" . $chips . "\n\t\t\t\t</div>";
        $input = "<input type=\"text\" class=\"" . Util::attr(join(' ', array_merge($this->controlClasses('form-control'), ['laf-autocomplete-input']))) . "\" style=\"" . Util::attr($this->controlStyle()) . "\""
            . " value=\"\""
            . $this->renderAttributes($attributes)
            . $this->describedBy() . " />";
        $menu = "<div class=\"dropdown-menu w-100 laf-autocomplete-menu\" id=\"" . Util::attr($id . '_listbox') . "\" role=\"listbox\" style=\"max-height:20rem;overflow-y:auto\"></div>";

        $data = [
            'data-laf-autocomplete' => $this->getLookupKey(),
            'data-multiple' => '1',
            'data-name' => $postName,
            'data-max-items' => $this->getMaxItems() !== null ? (string)$this->getMaxItems() : null,
            'data-min-chars' => (string)$this->getMinChars(),
            'data-url' => $this->getLookupUrl(),
            'data-no-results' => $this->getNoResultsText(),
        ] + $this->createAttributes();
        $box = "<div class=\"laf-autocomplete laf-autocomplete-multi position-relative\"" . $this->renderAttributes($data) . ">"
            . "\n\t\t\t\t" . $empty . "\n\t\t\t\t" . $chipList . "\n\t\t\t\t" . $input . "\n\t\t\t\t" . $menu . "\n\t\t\t\t</div>";

        return $this->renderWrapper($box, ['suggestions' => false]) . $this->drawScriptOnce();
    }

    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }
}
