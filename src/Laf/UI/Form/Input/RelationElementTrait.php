<?php

declare(strict_types=1);

namespace Laf\UI\Form\Input;

use Laf\Database\ManyToMany;
use Laf\UI\ComponentInterface;

/**
 * The relation binding of the many-to-many pickers (ManyToManyCheckboxes, ManyToManyAutocomplete):
 * the value is the chosen keys joined with commas, so the multi-value machinery of Select works as is.
 */
trait RelationElementTrait
{
    private ?ManyToMany $relation = null;

    private ?int $maxItems = null;

    /** @var array<string, string>|null labels of the chosen keys, for the value they were read for */
    private ?array $relationLabels = null;

    private ?string $relationLabelsFor = null;

    /**
     * @return static
     */
    public function setRelation(ManyToMany $relation): ComponentInterface
    {
        $this->relation = $relation;
        $this->setName($relation->getName());
        $this->setId('m2m_' . $relation->getName());
        $this->setLabel($relation->getLabel());
        $this->setHint($relation->getHint());
        $this->setRequired($relation->isRequired());
        try {
            $ids = $relation->ids();
        } catch (\Throwable $ex) {
            $ids = [];
        }
        $this->setSelectedIds($ids);
        return $this;
    }

    public function getRelation(): ?ManyToMany
    {
        return $this->relation;
    }

    public function getRelationName(): string
    {
        return $this->relation !== null ? $this->relation->getName() : (string)$this->getName();
    }

    /**
     * @return array<int, string>
     */
    public function getSelectedIds(): array
    {
        return $this->getSelectedValues();
    }

    /**
     * @param array<int, int|string> $ids
     * @return static
     */
    public function setSelectedIds(array $ids): ComponentInterface
    {
        $this->setValue(implode(',', ManyToMany::cleanIds($ids)));
        return $this;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    /**
     * @return static
     */
    public function setMaxItems(?int $maxItems): ComponentInterface
    {
        $this->maxItems = $maxItems !== null && $maxItems > 0 ? $maxItems : null;
        return $this;
    }

    /** Several keys, always */
    protected function isMultiValued(): bool
    {
        return true;
    }

    /**
     * Label of a chosen key: read for every chosen key at once from the other table
     */
    protected function labelFor(string $value): string
    {
        if ($this->hasStaticOptions()) {
            return parent::labelFor($value);
        }
        $current = (string)$this->getValue();
        if ($this->relationLabels === null || $this->relationLabelsFor !== $current) {
            $this->relationLabelsFor = $current;
            try {
                $this->relationLabels = $this->relation !== null ? $this->relation->labels($this->getSelectedValues()) : [];
            } catch (\Throwable $ex) {
                $this->relationLabels = [];
            }
        }
        return $this->relationLabels[$value] ?? $value;
    }
}
