<?php

declare(strict_types=1);

namespace Laf\UI\Form\Input;

use Laf\UI\Form\RelationElementInterface;

/**
 * One checkbox per row of the other table of a many-to-many relation (the groups of a person):
 * the picker for a small table. Posts the ticked keys as name[] after an empty hidden value, so
 * unticking every box is submitted too. VIEW mode lists the chosen labels.
 *
 *   $person->manyToMany('person_group')->getFormElement('CheckboxCollection')->setInline(true);
 */
class ManyToManyCheckboxes extends CheckboxCollection implements RelationElementInterface
{
    use RelationElementTrait;

    /** @var array<string, string>|null */
    private ?array $relationOptions = null;

    /**
     * Every row of the other table (setOptions() replaces them)
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

    public function clearOptionsCache(): static
    {
        $this->relationOptions = null;
        return parent::clearOptionsCache();
    }

    protected function labelFor(string $value): string
    {
        $options = $this->getOptions();
        return isset($options[$value]) ? (string)$options[$value] : $value;
    }

    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }
}
